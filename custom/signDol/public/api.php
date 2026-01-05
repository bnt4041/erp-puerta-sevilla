<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/public/api.php
 * \ingroup docsig
 * \brief   API pública para el proceso de firma (verificación, OTP, firma)
 */

// Configuración para página pública
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

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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
    jsonError('Server configuration error', 500);
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/signDol/class/docsigenvelope.class.php');
dol_include_once('/signDol/class/docsigsigner.class.php');
dol_include_once('/signDol/class/docsigotpmanager.class.php');
dol_include_once('/signDol/lib/docsig.lib.php');

// Load translations
$langs->loadLangs(array('docsig@signDol', 'other'));

// Get action
$action = GETPOST('action', 'aZ09');
$token = GETPOST('token', 'aZ09');

// IP address for logging
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

// Validate token first
$signer = null;
$envelope = null;

if (empty($token)) {
    jsonError($langs->trans('ErrorInvalidToken'), 400);
}

// Find signer by token
$tokenHash = docsig_hash_token($token);
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."docsig_signer WHERE token_hash = '".$db->escape($tokenHash)."'";
$resql = $db->query($sql);

if (!$resql || $db->num_rows($resql) == 0) {
    jsonError($langs->trans('ErrorInvalidToken'), 400);
}

$obj = $db->fetch_object($resql);
$signer = new DocSigSigner($db);
if ($signer->fetch($obj->rowid) <= 0) {
    jsonError($langs->trans('ErrorInvalidToken'), 400);
}

// Check token validity
if ($signer->token_expire && $signer->token_expire < dol_now()) {
    jsonError($langs->trans('ErrorTokenExpired'), 400);
}

if ($signer->status == DocSigSigner::STATUS_SIGNED) {
    jsonError($langs->trans('ErrorAlreadySigned'), 400);
}

// Check if blocked
$otpManager = new DocSigOTPManager($db);
if ($otpManager->isSignerBlocked($signer->id)) {
    jsonError($langs->trans('ErrorSignerBlocked'), 403);
}

// Get envelope
$envelope = new DocSigEnvelope($db);
if ($envelope->fetch($signer->fk_envelope) <= 0) {
    jsonError($langs->trans('ErrorEnvelopeNotFound'), 400);
}

// Check envelope status
if ($envelope->status == DocSigEnvelope::STATUS_CANCELED) {
    jsonError($langs->trans('ErrorEnvelopeCanceled'), 400);
}
if ($envelope->status == DocSigEnvelope::STATUS_EXPIRED || $envelope->expire_date < dol_now()) {
    jsonError($langs->trans('ErrorEnvelopeExpired'), 400);
}

// Route actions
switch ($action) {
    case 'get_status':
        handleGetStatus($signer, $envelope, $otpManager);
        break;

    case 'verify_identity':
        handleVerifyIdentity($signer, $envelope, $ipAddress, $userAgent);
        break;

    case 'send_otp':
        handleSendOTP($signer, $envelope, $otpManager, $ipAddress);
        break;

    case 'verify_otp':
        handleVerifyOTP($signer, $envelope, $otpManager, $ipAddress);
        break;

    case 'submit_signature':
        handleSubmitSignature($signer, $envelope, $ipAddress, $userAgent);
        break;

    default:
        jsonError('Invalid action', 400);
}

// ==================== HANDLERS ====================

/**
 * Obtiene el estado actual del proceso de firma
 */
function handleGetStatus($signer, $envelope, $otpManager)
{
    global $langs;

    $activeOTP = $otpManager->getActiveOTP($signer->id);
    
    $response = array(
        'success' => true,
        'signer' => array(
            'id' => $signer->id,
            'firstname' => $signer->firstname,
            'lastname' => $signer->lastname,
            'email' => maskEmail($signer->email),
            'phone' => maskPhone($signer->phone),
            'status' => $signer->status,
            'has_dni' => !empty($signer->dni),
            'has_email' => !empty($signer->email),
            'has_phone' => !empty($signer->phone)
        ),
        'envelope' => array(
            'ref' => $envelope->ref,
            'expire_date' => dol_print_date($envelope->expire_date, 'dayhour'),
            'status' => $envelope->status
        ),
        'step' => determineCurrentStep($signer, $activeOTP),
        'otp_active' => $activeOTP !== null,
        'otp_expires_at' => $activeOTP ? dol_print_date($activeOTP['expires_at'], 'dayhour') : null,
        'otp_attempts_remaining' => $activeOTP ? ($activeOTP['max_attempts'] - $activeOTP['attempts']) : null
    );

    jsonSuccess($response);
}

/**
 * Verifica la identidad del firmante (DNI + email/teléfono)
 */
function handleVerifyIdentity($signer, $envelope, $ipAddress, $userAgent)
{
    global $langs, $db;

    $dni = strtoupper(trim(GETPOST('dni', 'alphanohtml')));
    $method = GETPOST('method', 'aZ09'); // 'email' o 'phone'
    $value = trim(GETPOST('value', 'alphanohtml')); // email o teléfono según método

    // Validar que se proporcionen los datos
    if (empty($dni)) {
        jsonError($langs->trans('ErrorDNIRequired'), 400);
    }

    if (empty($method) || !in_array($method, array('email', 'phone'))) {
        jsonError($langs->trans('ErrorInvalidMethod'), 400);
    }

    if (empty($value)) {
        jsonError($langs->trans('ErrorValueRequired'), 400);
    }

    // Verificar DNI
    $signerDNI = strtoupper(trim($signer->dni));
    if (!empty($signerDNI) && $dni !== $signerDNI) {
        // Log intento fallido
        $envelope->logEvent('IDENTITY_FAILED', 'DNI mismatch', $ipAddress, $userAgent, $signer->id);
        jsonError($langs->trans('ErrorDNINotMatch'), 400);
    }

    // Verificar email o teléfono
    if ($method === 'email') {
        $signerEmail = strtolower(trim($signer->email));
        $inputEmail = strtolower(trim($value));
        
        if ($signerEmail !== $inputEmail) {
            $envelope->logEvent('IDENTITY_FAILED', 'Email mismatch', $ipAddress, $userAgent, $signer->id);
            jsonError($langs->trans('ErrorEmailNotMatch'), 400);
        }
    } else {
        // Normalizar teléfonos para comparar
        $signerPhone = preg_replace('/[^0-9]/', '', $signer->phone);
        $inputPhone = preg_replace('/[^0-9]/', '', $value);
        
        // Comparar últimos 9 dígitos (sin código país)
        $signerPhone9 = substr($signerPhone, -9);
        $inputPhone9 = substr($inputPhone, -9);
        
        if ($signerPhone9 !== $inputPhone9) {
            $envelope->logEvent('IDENTITY_FAILED', 'Phone mismatch', $ipAddress, $userAgent, $signer->id);
            jsonError($langs->trans('ErrorPhoneNotMatch'), 400);
        }
    }

    // Si el firmante no tenía DNI, guardarlo ahora
    if (empty($signer->dni)) {
        $signer->dni = $dni;
        global $user;
        if (empty($user) || empty($user->id)) {
            $user = new User($db);
            $user->id = 0;
        }
        $signer->update($user);
    }

    // Identidad verificada - log evento
    $envelope->logEvent('IDENTITY_VERIFIED', 'Identity verified via ' . $method, $ipAddress, $userAgent, $signer->id);

    jsonSuccess(array(
        'verified' => true,
        'method' => $method,
        'message' => $langs->trans('IdentityVerified')
    ));
}

/**
 * Envía código OTP al firmante
 */
function handleSendOTP($signer, $envelope, $otpManager, $ipAddress)
{
    global $langs, $conf;

    $channel = GETPOST('channel', 'aZ09'); // 'email' o 'whatsapp'
    
    if (empty($channel)) {
        $channel = 'email';
    }

    // Determinar destino
    $destination = '';
    if ($channel === 'whatsapp' || $channel === 'phone') {
        $destination = $signer->phone;
        if (empty($destination)) {
            jsonError($langs->trans('ErrorNoPhoneNumber'), 400);
        }
    } else {
        $destination = $signer->email;
        if (empty($destination)) {
            jsonError($langs->trans('ErrorNoEmailAddress'), 400);
        }
    }

    // Generar OTP
    $result = $otpManager->generateOTP($signer->id, $channel, $destination, $ipAddress);

    if (!$result['success']) {
        if ($result['error'] === 'rate_limit_exceeded') {
            jsonError($langs->trans('ErrorRateLimitExceeded'), 429);
        }
        jsonError($langs->trans('ErrorGeneratingOTP'), 500);
    }

    // Enviar OTP
    $signerData = array(
        'firstname' => $signer->firstname,
        'lastname' => $signer->lastname,
        'email' => $signer->email,
        'phone' => $signer->phone,
        'fk_contact' => $signer->fk_contact
    );

    $sendResult = $otpManager->sendOTP($signer->id, $result['code'], $channel, $signerData);

    if (!$sendResult['success']) {
        jsonError($langs->trans('ErrorSendingOTP') . ': ' . $sendResult['error'], 500);
    }

    // Log evento
    $envelope->logEvent('OTP_SENT', 'OTP sent via ' . $sendResult['channel'] . ' to ' . maskDestination($destination, $channel), $ipAddress, '', $signer->id);

    jsonSuccess(array(
        'sent' => true,
        'channel' => $sendResult['channel'],
        'destination' => maskDestination($destination, $channel),
        'expires_in_minutes' => $result['expiration_minutes'],
        'message' => $langs->trans('OTPSentTo', maskDestination($destination, $channel))
    ));
}

/**
 * Verifica el código OTP
 */
function handleVerifyOTP($signer, $envelope, $otpManager, $ipAddress)
{
    global $langs;

    $code = GETPOST('code', 'alphanohtml');

    if (empty($code)) {
        jsonError($langs->trans('ErrorCodeRequired'), 400);
    }

    // Limpiar código (solo dígitos)
    $code = preg_replace('/[^0-9]/', '', $code);

    // Verificar OTP
    $result = $otpManager->verifyOTP($signer->id, $code, $ipAddress);

    if ($result['success']) {
        // Log evento
        $envelope->logEvent('OTP_VERIFIED', 'OTP verified successfully', $ipAddress, '', $signer->id);

        jsonSuccess(array(
            'verified' => true,
            'message' => $langs->trans('OTPVerified')
        ));
    } else {
        // Log intento fallido
        $envelope->logEvent('OTP_FAILED', 'OTP verification failed: ' . $result['error'], $ipAddress, '', $signer->id);

        $errorMsg = $langs->trans('ErrorOTPInvalid');
        
        if ($result['error'] === 'otp_expired') {
            $errorMsg = $langs->trans('ErrorOTPExpired');
        } elseif ($result['blocked']) {
            $errorMsg = $langs->trans('ErrorOTPBlocked');
        } elseif ($result['attempts_remaining'] > 0) {
            $errorMsg = $langs->trans('OTPInvalid', $result['attempts_remaining']);
        }

        jsonError($errorMsg, 400, array(
            'attempts_remaining' => $result['attempts_remaining'],
            'blocked' => $result['blocked']
        ));
    }
}

/**
 * Procesa la firma del documento
 */
function handleSubmitSignature($signer, $envelope, $ipAddress, $userAgent)
{
    global $langs, $db, $user;

    // Verificar que OTP fue verificado
    $otpManager = new DocSigOTPManager($db);
    
    // Obtener el último OTP verificado
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_otp";
    $sql .= " WHERE fk_signer = ".(int)$signer->id;
    $sql .= " AND status = ".DocSigOTPManager::STATUS_VERIFIED;
    $sql .= " ORDER BY verified_at DESC LIMIT 1";
    
    $resql = $db->query($sql);
    if (!$resql || $db->num_rows($resql) == 0) {
        jsonError($langs->trans('ErrorOTPNotVerified'), 403);
    }

    // Obtener datos de la firma
    $signatureData = GETPOST('signature_data', 'restricthtml');
    $acceptTerms = GETPOSTINT('accept_terms');

    if (empty($signatureData)) {
        jsonError($langs->trans('ErrorSignatureRequired'), 400);
    }

    if (!$acceptTerms) {
        jsonError($langs->trans('ErrorAcceptTermsRequired'), 400);
    }

    // Validar que es una imagen base64 válida
    if (strpos($signatureData, 'data:image/') !== 0) {
        jsonError($langs->trans('ErrorInvalidSignatureFormat'), 400);
    }

    // Crear usuario dummy para operaciones internas
    if (empty($user) || empty($user->id)) {
        $user = new User($db);
        $user->id = 0;
    }

    // Registrar la firma
    $result = $signer->recordSignature($signatureData, $ipAddress, $userAgent);

    if ($result > 0) {
        // Log evento
        $envelope->logEvent('SIGNER_SIGNED', 'Document signed by ' . $signer->getFullName(), $ipAddress, $userAgent, $signer->id);

        // Verificar si todos han firmado
        $envelope->checkCompletion();

        // Recargar envelope para obtener estado actualizado
        $envelope->fetch($envelope->id);

        $response = array(
            'success' => true,
            'message' => $langs->trans('SignatureRecordedSuccessfully'),
            'envelope_status' => $envelope->status,
            'completed' => ($envelope->status == DocSigEnvelope::STATUS_COMPLETED)
        );

        // Si el envelope está completado, incluir URLs de descarga
        if ($envelope->status == DocSigEnvelope::STATUS_COMPLETED) {
            $response['signed_document_url'] = !empty($envelope->signed_file_path) ? 
                dol_buildpath('/signDol/public/download.php', 1) . '?type=signed&token=' . GETPOST('token', 'aZ09') : null;
            $response['certificate_url'] = !empty($envelope->compliance_cert_path) ?
                dol_buildpath('/signDol/public/download.php', 1) . '?type=cert&token=' . GETPOST('token', 'aZ09') : null;
        }

        jsonSuccess($response);
    } else {
        jsonError($langs->trans('ErrorRecordingSignature'), 500);
    }
}

// ==================== HELPER FUNCTIONS ====================

/**
 * Determina el paso actual del proceso
 */
function determineCurrentStep($signer, $activeOTP)
{
    // Si ya firmó
    if ($signer->status == DocSigSigner::STATUS_SIGNED) {
        return 'completed';
    }

    // Si hay OTP verificado reciente (última hora)
    global $db;
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_otp";
    $sql .= " WHERE fk_signer = ".(int)$signer->id;
    $sql .= " AND status = ".DocSigOTPManager::STATUS_VERIFIED;
    $sql .= " AND verified_at >= '".$db->idate(dol_time_plus_duree(dol_now(), -1, 'h'))."'";
    $sql .= " ORDER BY verified_at DESC LIMIT 1";
    
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        return 'signature'; // Listo para firmar
    }

    // Si hay OTP activo pendiente
    if ($activeOTP) {
        return 'otp_verify'; // Verificar OTP
    }

    // Por defecto: verificar identidad
    return 'identity';
}

/**
 * Enmascara un email para mostrar parcialmente
 */
function maskEmail($email)
{
    if (empty($email)) return '';
    
    $parts = explode('@', $email);
    if (count($parts) != 2) return $email;
    
    $name = $parts[0];
    $domain = $parts[1];
    
    if (strlen($name) <= 3) {
        $masked = $name[0] . '***';
    } else {
        $masked = substr($name, 0, 2) . str_repeat('*', strlen($name) - 3) . substr($name, -1);
    }
    
    return $masked . '@' . $domain;
}

/**
 * Enmascara un teléfono para mostrar parcialmente
 */
function maskPhone($phone)
{
    if (empty($phone)) return '';
    
    $clean = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($clean) < 6) return $phone;
    
    return substr($clean, 0, 3) . str_repeat('*', strlen($clean) - 5) . substr($clean, -2);
}

/**
 * Enmascara destino según canal
 */
function maskDestination($destination, $channel)
{
    if ($channel === 'email') {
        return maskEmail($destination);
    }
    return maskPhone($destination);
}

/**
 * Respuesta JSON exitosa
 */
function jsonSuccess($data)
{
    echo json_encode(array_merge(array('success' => true), $data));
    exit;
}

/**
 * Respuesta JSON de error
 */
function jsonError($message, $code = 400, $extra = array())
{
    http_response_code($code);
    echo json_encode(array_merge(array(
        'success' => false,
        'error' => $message
    ), $extra));
    exit;
}
