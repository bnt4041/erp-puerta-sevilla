<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/ajax/envelope_cancel.php
 * \ingroup docsig
 * \brief   AJAX endpoint para cancelar envelope
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

// Security check
if (!isModEnabled('docsig')) {
    http_response_code(403);
    print json_encode(array('success' => false, 'error' => 'Module not enabled'));
    exit;
}

if (!$user->hasRight('docsig', 'envelope', 'delete')) {
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
$id = GETPOSTINT('id');
$reason = GETPOST('reason', 'restricthtml');

$response = array('success' => false);

try {
    if (empty($id)) {
        throw new Exception($langs->trans('ErrorFieldRequired', 'id'));
    }

    $envelope = new DocSigEnvelope($db);
    if ($envelope->fetch($id) <= 0) {
        throw new Exception($langs->trans('ErrorRecordNotFound'));
    }

    // Verificar que se puede cancelar
    if ($envelope->status >= DocSigEnvelope::STATUS_COMPLETED) {
        throw new Exception($langs->trans('ErrorCannotCancelCompletedEnvelope'));
    }

    // Cancelar
    $result = $envelope->cancel($user, $reason);
    if ($result < 0) {
        throw new Exception($langs->trans('ErrorCancelingEnvelope').': '.implode(', ', $envelope->errors));
    }

    $response = array(
        'success' => true,
        'message' => $langs->trans('EnvelopeCanceledSuccessfully'),
    );

} catch (Exception $e) {
    $response = array(
        'success' => false,
        'error' => $e->getMessage(),
    );
}

print json_encode($response);
