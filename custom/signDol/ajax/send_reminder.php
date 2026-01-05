<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/ajax/send_reminder.php
 * \ingroup docsig
 * \brief   AJAX endpoint para reenviar recordatorio a firmante
 */

if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
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

dol_include_once('/signDol/class/docsigenvelope.class.php');
dol_include_once('/signDol/class/docsigsigner.class.php');
dol_include_once('/signDol/class/docsignotification.class.php');
dol_include_once('/signDol/lib/docsig.lib.php');

// Security check
if (!isModEnabled('docsig')) {
    http_response_code(403);
    print json_encode(array('success' => false, 'error' => 'Module not enabled'));
    exit;
}

if (!$user->hasRight('docsig', 'envelope', 'write')) {
    http_response_code(403);
    print json_encode(array('success' => false, 'error' => 'Permission denied'));
    exit;
}

// CSRF Token check
$token = GETPOST('token', 'alpha');
if (!$token || $token != $_SESSION['newtoken']) {
    http_response_code(403);
    print json_encode(array('success' => false, 'error' => 'Invalid CSRF token'));
    exit;
}

$langs->loadLangs(array('docsig@signDol'));

header('Content-Type: application/json; charset=UTF-8');

// Get parameters
$signerId = GETPOSTINT('signer_id');

$response = array('success' => false);

try {
    if (empty($signerId)) {
        throw new Exception($langs->trans('ErrorFieldRequired', 'signer_id'));
    }

    $signer = new DocSigSigner($db);
    if ($signer->fetch($signerId) <= 0) {
        throw new Exception($langs->trans('ErrorRecordNotFound'));
    }

    // Verificar que el firmante está pendiente
    if ($signer->status != 0) {
        throw new Exception($langs->trans('ErrorSignerAlreadySigned'));
    }

    // Obtener envelope
    $envelope = new DocSigEnvelope($db);
    if ($envelope->fetch($signer->fk_envelope) <= 0) {
        throw new Exception($langs->trans('ErrorRecordNotFound'));
    }

    // Verificar que el envelope está activo
    if ($envelope->status >= DocSigEnvelope::STATUS_COMPLETED) {
        throw new Exception($langs->trans('ErrorEnvelopeNotActive'));
    }

    // Regenerar token
    $newToken = $signer->regenerateToken();

    // Generar URL de firma
    $signUrl = docsig_get_public_sign_url($newToken);

    // Enviar recordatorio
    $notificationService = new DocSigNotificationService($db);
    $result = $notificationService->sendReminder($envelope, $signer, $signUrl);

    if (!$result['success']) {
        throw new Exception($langs->trans('ErrorSendingReminder').': '.$result['error']);
    }

    // Log event
    $envelope->logEvent('REMINDER_SENT', 'Reminder sent to '.$signer->email);

    $response = array(
        'success' => true,
        'message' => $langs->trans('ReminderSentSuccessfully'),
    );

} catch (Exception $e) {
    $response = array(
        'success' => false,
        'error' => $e->getMessage(),
    );
}

print json_encode($response);
