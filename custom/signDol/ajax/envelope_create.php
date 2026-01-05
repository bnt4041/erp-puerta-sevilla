<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/ajax/envelope_create.php
 * \ingroup docsig
 * \brief   AJAX endpoint para crear envelope de firma
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
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
$element = GETPOST('element', 'aZ09');
$objectId = GETPOSTINT('object_id');
$pdfFile = GETPOST('pdf_file', 'alpha');
$signatureMode = GETPOST('signature_mode', 'aZ09');
$customMessage = GETPOST('custom_message', 'restricthtml');

// Obtener firmantes seleccionados y sus datos
$signersSelected = GETPOST('signers_selected', 'array');
$signersData = GETPOST('signers', 'array');

$response = array('success' => false);

try {
    // Validaciones
    if (empty($element) || empty($objectId)) {
        throw new Exception($langs->trans('ErrorFieldRequired', 'element/object_id'));
    }

    if (empty($pdfFile) || !file_exists($pdfFile)) {
        throw new Exception($langs->trans('ErrorFileNotFound'));
    }

    if (empty($signersSelected) || !is_array($signersSelected)) {
        throw new Exception($langs->trans('ErrorNoSignersSelected'));
    }

    if (!in_array($signatureMode, array('parallel', 'sequential'))) {
        $signatureMode = 'parallel';
    }

    // Procesar firmantes seleccionados
    $processedSigners = array();
    foreach ($signersSelected as $signerId) {
        if (!isset($signersData[$signerId])) {
            continue;
        }
        $data = $signersData[$signerId];
        
        // Validar email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception($langs->trans('ErrorInvalidEmail').': '.$data['name']);
        }
        
        $processedSigners[$signerId] = array(
            'name' => $data['name'] ?? '',
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'type' => $data['type'] ?? 'contact',
            'contact_id' => (int)($data['contact_id'] ?? 0),
            'save_contact' => (int)($data['save_contact'] ?? 0),
            'dni' => $data['dni'] ?? '',
        );
    }

    if (empty($processedSigners)) {
        throw new Exception($langs->trans('ErrorNoSignersSelected'));
    }

    $db->begin();

    // Crear contactos nuevos en socpeople si se solicitó guardar
    foreach ($processedSigners as $signerId => &$signerInfo) {
        if ($signerInfo['type'] === 'new' && $signerInfo['save_contact'] == 1) {
            // Crear nuevo contacto en socpeople
            $newContact = new Contact($db);
            
            // Separar nombre en firstname/lastname
            $nameParts = explode(' ', $signerInfo['name'], 2);
            $newContact->firstname = $nameParts[0] ?? '';
            $newContact->lastname = $nameParts[1] ?? $nameParts[0];
            
            $newContact->email = $signerInfo['email'];
            $newContact->phone_mobile = $signerInfo['phone'];
            $newContact->statut = 1; // Activo
            $newContact->fk_soc = 0; // Sin tercero asociado (según requisito)
            
            $contactId = $newContact->create($user);
            if ($contactId > 0) {
                // Guardar DNI si se proporcionó
                if (!empty($signerInfo['dni'])) {
                    $newContact->array_options['options_docsig_dni'] = $signerInfo['dni'];
                    $newContact->updateExtraField('docsig_dni');
                }
                
                // Actualizar el contact_id para el firmante
                $signerInfo['contact_id'] = $contactId;
                dol_syslog('DocSig: Created new contact #'.$contactId.' for signer: '.$signerInfo['email'], LOG_INFO);
            } else {
                dol_syslog('DocSig: Error creating contact: '.implode(', ', $newContact->errors), LOG_WARNING);
            }
        }
    }
    unset($signerInfo); // Romper referencia

    // Crear envelope
    $envelope = new DocSigEnvelope($db);
    $envelope->element_type = $element;
    $envelope->fk_object = $objectId;
    $envelope->file_path = $pdfFile;
    $envelope->signature_mode = $signatureMode;
    $envelope->status = DocSigEnvelope::STATUS_DRAFT;

    $envelopeId = $envelope->create($user);
    if ($envelopeId <= 0) {
        throw new Exception($langs->trans('ErrorCreatingEnvelope').': '.implode(', ', $envelope->errors));
    }

    // Crear firmantes
    $signerTokens = array();
    $order = 1;
    foreach ($processedSigners as $signerInfo) {
        $signer = new DocSigSigner($db);
        $signer->fk_envelope = $envelopeId;
        $signer->fk_contact = $signerInfo['contact_id'];
        $signer->email = $signerInfo['email'];
        $signer->phone = $signerInfo['phone'];
        
        // Separar nombre
        $nameParts = explode(' ', $signerInfo['name'], 2);
        $signer->firstname = $nameParts[0] ?? '';
        $signer->lastname = $nameParts[1] ?? $nameParts[0];
        
        $signer->sign_order = $order;
        $signer->status = 0; // Pending

        $signerId = $signer->create($user);
        if ($signerId <= 0) {
            throw new Exception($langs->trans('ErrorCreatingSigner').': '.implode(', ', $signer->errors));
        }

        $signerTokens[] = array(
            'signer' => $signer,
            'token' => $signer->plain_token,
        );

        $order++;
    }

    // Cambiar estado a enviado
    $envelope->setSent($user);

    // Enviar notificaciones
    $notificationService = new DocSigNotificationService($db);

    foreach ($signerTokens as $signerInfo) {
        $signer = $signerInfo['signer'];
        $token = $signerInfo['token'];

        // En modo secuencial, solo enviar al primero
        if ($signatureMode === 'sequential' && $signer->sign_order > 1) {
            continue;
        }

        // Generar URL de firma
        $signUrl = docsig_get_public_sign_url($token);

        // Enviar email
        $result = $notificationService->sendSignatureRequest($envelope, $signer, $signUrl, $customMessage);
        if (!$result['success']) {
            dol_syslog('DocSig: Error sending notification to '.$signer->email.': '.$result['error'], LOG_WARNING);
        }
    }

    $db->commit();

    $response = array(
        'success' => true,
        'envelope_id' => $envelopeId,
        'envelope_ref' => $envelope->ref,
        'message' => $langs->trans('EnvelopeCreatedSuccessfully'),
        'redirect' => dol_buildpath('/signDol/card.php', 1).'?id='.$envelopeId,
    );

} catch (Exception $e) {
    $db->rollback();
    $response = array(
        'success' => false,
        'error' => $e->getMessage(),
    );
}

print json_encode($response);
