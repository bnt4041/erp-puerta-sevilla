<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/public/viewpdf.php
 * \ingroup docsig
 * \brief   Visor de PDF público para firmantes
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOIPCHECK')) {
    define('NOIPCHECK', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
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
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/signDol/class/docsigenvelope.class.php');
dol_include_once('/signDol/class/docsigsigner.class.php');

dol_include_once('/signDol/lib/docsig.lib.php');

// Get token
$token = GETPOST('token', 'aZ09');

if (empty($token)) {
    http_response_code(403);
    die('Invalid token');
}

// Calculate token hash
$tokenHash = docsig_hash_token($token);

// Find signer by token hash
$sql = "SELECT s.rowid, s.fk_envelope, s.token_expires as token_expire, e.file_path, e.status as envelope_status";
$sql .= " FROM ".MAIN_DB_PREFIX."docsig_signer as s";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."docsig_envelope as e ON s.fk_envelope = e.rowid";
$sql .= " WHERE s.token_hash = '".$db->escape($tokenHash)."'";

$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    http_response_code(403);
    die('Access denied');
}

$obj = $db->fetch_object($resql);

// Check token expiry
if ($obj->token_expire && strtotime($obj->token_expire) < time()) {
    http_response_code(403);
    die('Token expired');
}

// Check envelope status
if ($obj->envelope_status == 4 || $obj->envelope_status == 5) { // Canceled or expired
    http_response_code(403);
    die('Envelope not available');
}

// Check file exists
$filePath = $obj->file_path;
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Log view event
$envelope = new DocSigEnvelope($db);
$envelope->fetch($obj->fk_envelope);
$envelope->logEvent('DOCUMENT_VIEWED', 'Document viewed by signer (token: '.substr($token, 0, 8).'...)');

// Serve PDF
$filename = basename($filePath);
$filesize = filesize($filePath);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
header('Content-Length: '.$filesize);
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clear output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($filePath);
exit;
