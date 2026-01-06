<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/public/download.php
 * \ingroup docsig
 * \brief   Descarga tokenizada de documentos firmados
 */

if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    http_response_code(500);
    die("Include of main fails");
}

dol_include_once('/signDol/class/docsigenvelope.class.php');
dol_include_once('/signDol/class/docsigsigner.class.php');

$langs->loadLangs(array('docsig@signDol'));

// Get parameters
$token = GETPOST('token', 'aZ09');
$type = GETPOST('type', 'alpha'); // 'signed' or 'cert'
$downloadToken = GETPOST('dl', 'aZ09'); // Download-specific token

// Validate token
if (empty($token)) {
    http_response_code(400);
    die($langs->trans('ErrorInvalidToken'));
}

// Check if public download is enabled
if (!getDolGlobalInt('DOCSIG_PUBLIC_DOWNLOAD_ENABLED', 1)) {
    http_response_code(403);
    die($langs->trans('ErrorInvalidToken'));
}

// Find signer by token
// - token puede ser el token en claro (URL pública) => se compara por hash
// - o puede ser directamente token_hash (backend) => se compara directo
$hashedToken = hash('sha256', $token);
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."docsig_signer";
$sql .= " WHERE token_hash = '".$db->escape($hashedToken)."'";
$sql .= " OR token_hash = '".$db->escape($token)."'";
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    http_response_code(404);
    die($langs->trans('ErrorInvalidToken'));
}

$obj = $db->fetch_object($resql);
$signer = new DocSigSigner($db);
if ($signer->fetch($obj->rowid) <= 0) {
    http_response_code(404);
    die($langs->trans('ErrorRecordNotFound'));
}

// Load envelope
$envelope = new DocSigEnvelope($db);
if ($envelope->fetch($signer->fk_envelope) <= 0) {
    http_response_code(404);
    die($langs->trans('ErrorEnvelopeNotFound'));
}

// Verify envelope is completed
if ($envelope->status != DocSigEnvelope::STATUS_COMPLETED) {
    http_response_code(403);
    die($langs->trans('ErrorDocumentNotCompleted'));
}

// If download token provided, validate it
if (!empty($downloadToken)) {
    $valid = docsig_validate_download_token($downloadToken, $signer->id, $envelope->id);
    if (!$valid) {
        http_response_code(403);
        die($langs->trans('ErrorDownloadLinkExpired'));
    }
} else {
    // Check if signer token is still valid (not expired beyond download window)
    $downloadExpirationHours = getDolGlobalInt('DOCSIG_DOWNLOAD_EXPIRATION_HOURS', 168); // Default 7 days
    if ((int) $downloadExpirationHours > 0) {
        $completedTs = 0;

        // completed_at suele ser timestamp (jdate) cuando viene del objeto
        if (!empty($envelope->completed_at)) {
            $completedTs = is_numeric($envelope->completed_at) ? (int) $envelope->completed_at : (int) strtotime($envelope->completed_at);
        }
        if ($completedTs <= 0 && !empty($envelope->date_modification)) {
            $completedTs = is_numeric($envelope->date_modification) ? (int) $envelope->date_modification : (int) strtotime($envelope->date_modification);
        }
        if ($completedTs <= 0 && !empty($envelope->date_creation)) {
            $completedTs = is_numeric($envelope->date_creation) ? (int) $envelope->date_creation : (int) strtotime($envelope->date_creation);
        }

        if ($completedTs > 0) {
            $expirationTime = $completedTs + ((int) $downloadExpirationHours * 3600);
            if (dol_now() > $expirationTime) {
                http_response_code(403);
                die($langs->trans('ErrorDownloadLinkExpired'));
            }
        }
    }
}

// Determine which file to serve
if ($type === 'cert') {
    $filePath = $envelope->compliance_cert_path;
    $fileName = 'certificate_'.$envelope->ref.'.pdf';
    $mimeType = 'application/pdf';
} else {
    $filePath = $envelope->signed_file_path;
    $fileName = 'signed_'.$envelope->ref.'.pdf';
    $mimeType = 'application/pdf';
}

// Verify file exists
if (empty($filePath) || !file_exists($filePath)) {
    http_response_code(404);
    die($langs->trans('ErrorFileNotFound'));
}

// Log download event
$envelope->logEvent('DOCUMENT_DOWNLOAD', 'Downloaded by signer '.$signer->email.' (type: '.($type ?: 'signed').')');

// Send file
header('Content-Type: '.$mimeType);
header('Content-Disposition: attachment; filename="'.$fileName.'"');
header('Content-Length: '.filesize($filePath));
header('Cache-Control: private, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($filePath);
exit;

/**
 * Validate a download-specific token
 * 
 * @param string $downloadToken Download token
 * @param int $signerId Signer ID
 * @param int $envelopeId Envelope ID
 * @return bool True if valid
 */
function docsig_validate_download_token($downloadToken, $signerId, $envelopeId)
{
    global $db;
    
    // Download tokens are stored in a separate table with expiration
    $sql = "SELECT rowid, expires_at FROM ".MAIN_DB_PREFIX."docsig_download_token";
    $sql .= " WHERE token_hash = '".$db->escape(hash('sha256', $downloadToken))."'";
    $sql .= " AND fk_signer = ".(int)$signerId;
    $sql .= " AND fk_envelope = ".(int)$envelopeId;
    $sql .= " AND used = 0";
    
    $resql = $db->query($sql);
    if (!$resql || $db->num_rows($resql) == 0) {
        return false;
    }
    
    $obj = $db->fetch_object($resql);
    
    // Check expiration
    if (strtotime($obj->expires_at) < dol_now()) {
        return false;
    }
    
    // Mark as used (optional - can be one-time use)
    // $db->query("UPDATE ".MAIN_DB_PREFIX."docsig_download_token SET used = 1 WHERE rowid = ".(int)$obj->rowid);
    
    return true;
}
