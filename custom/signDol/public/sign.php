<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/public/sign.php
 * \ingroup docsig
 * \brief   Página pública de firma con flujo completo de autenticación
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
dol_include_once('/signDol/class/docsigotpmanager.class.php');
dol_include_once('/signDol/lib/docsig.lib.php');

// Load translations
$langs->loadLangs(array('docsig@signDol', 'other'));

// Get token parameter
$token = GETPOST('token', 'aZ09');

// Validate token and get signer
$signer = null;
$envelope = null;
$error = '';
$signerData = array();

if (empty($token)) {
    $error = $langs->trans('ErrorInvalidToken');
} else {
    // Find signer by token hash
    $tokenHash = docsig_hash_token($token);
    
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."docsig_signer WHERE token_hash = '".$db->escape($tokenHash)."'";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $signer = new DocSigSigner($db);
        $signer->fetch($obj->rowid);

        // Check token expiry
        if ($signer->token_expire && $signer->token_expire < dol_now()) {
            $error = $langs->trans('ErrorTokenExpired');
        }
        // Check if already signed
        elseif ($signer->status == DocSigSigner::STATUS_SIGNED) {
            $error = $langs->trans('ErrorAlreadySigned');
        }
        // Check if blocked
        else {
            $otpManager = new DocSigOTPManager($db);
            if ($otpManager->isSignerBlocked($signer->id)) {
                $error = $langs->trans('ErrorSignerBlocked');
            }
        }

        // Get envelope if no error
        if (empty($error)) {
            $envelope = new DocSigEnvelope($db);
            if ($envelope->fetch($signer->fk_envelope) > 0) {
                // Check envelope status
                if ($envelope->status == DocSigEnvelope::STATUS_CANCELED) {
                    $error = $langs->trans('ErrorEnvelopeCanceled');
                } elseif ($envelope->status == DocSigEnvelope::STATUS_EXPIRED) {
                    $error = $langs->trans('ErrorEnvelopeExpired');
                } elseif ($envelope->expire_date < dol_now()) {
                    $error = $langs->trans('ErrorEnvelopeExpired');
                }
            } else {
                $error = $langs->trans('ErrorEnvelopeNotFound');
            }
        }

        // Prepare signer data
        if (empty($error) && $signer) {
            $signerData = array(
                'id' => $signer->id,
                'firstname' => $signer->firstname,
                'lastname' => $signer->lastname,
                'fullname' => $signer->getFullName(),
                'email' => $signer->email,
                'phone' => $signer->phone,
                'dni' => $signer->dni ?? '',
                'has_email' => !empty($signer->email),
                'has_phone' => !empty($signer->phone),
                'has_dni' => !empty($signer->dni)
            );
        }
    } else {
        $error = $langs->trans('ErrorInvalidToken');
    }
}

// Log page view event
if (empty($error) && $envelope) {
    $envelope->logEvent('PAGE_OPENED', 'Signing page opened', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $signer->id);
}

// Helper functions
function maskEmail($email) {
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

function maskPhone($phone) {
    if (empty($phone)) return '';
    $clean = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($clean) < 6) return $phone;
    return substr($clean, 0, 3) . str_repeat('*', strlen($clean) - 5) . substr($clean, -2);
}

/*
 * View
 */

$title = $langs->trans('SignDocument');
$companyName = getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'DocSig');

?>
<!DOCTYPE html>
<html lang="<?php echo $langs->defaultlang; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo $companyName; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd6;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --gray-800: #343a40;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .sign-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            overflow: hidden;
        }
        
        .sign-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px 32px;
            color: white;
        }
        
        .sign-header h1 {
            font-size: 1.5em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sign-header p {
            opacity: 0.9;
            font-size: 0.95em;
        }
        
        .sign-body {
            padding: 32px;
        }
        
        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 32px;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 3px;
            background: var(--gray-200);
            z-index: 0;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        
        .step-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--gray-200);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1em;
            margin-bottom: 8px;
            transition: all 0.3s;
        }
        
        .step.active .step-icon {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .step.completed .step-icon {
            background: var(--success);
            color: white;
        }
        
        .step-label {
            font-size: 0.8em;
            color: var(--gray-600);
            text-align: center;
        }
        
        .step.active .step-label {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* Form Sections */
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section-title {
            font-size: 1.2em;
            color: var(--gray-800);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-800);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        
        .form-group .hint {
            font-size: 0.85em;
            color: var(--gray-600);
            margin-top: 6px;
        }
        
        /* OTP Input */
        .otp-input-container {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 24px 0;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5em;
            font-weight: 600;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .otp-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        
        /* Signature Pad */
        .signature-pad-container {
            border: 2px dashed var(--gray-300);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background: var(--gray-100);
            margin: 20px 0;
        }
        
        .signature-pad {
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            background: white;
            touch-action: none;
            cursor: crosshair;
        }
        
        .signature-pad-actions {
            margin-top: 12px;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-800);
        }
        
        .btn-secondary:hover:not(:disabled) {
            background: var(--gray-300);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .btn-lg {
            padding: 16px 32px;
            font-size: 1.1em;
        }
        
        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert i {
            font-size: 1.2em;
            margin-top: 2px;
        }
        
        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        
        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        
        /* Document Info */
        .document-info {
            background: var(--gray-100);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .document-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .document-info-row:last-child {
            border-bottom: none;
        }
        
        .document-info-label {
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .document-info-value {
            color: var(--gray-800);
        }
        
        /* PDF Preview */
        .pdf-preview {
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .pdf-preview iframe {
            width: 100%;
            height: 350px;
            border: none;
        }
        
        /* Terms */
        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 20px 0;
            padding: 16px;
            background: var(--gray-100);
            border-radius: 10px;
        }
        
        .terms-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            cursor: pointer;
        }
        
        .terms-checkbox label {
            font-size: 0.9em;
            color: var(--gray-600);
            line-height: 1.5;
            cursor: pointer;
        }
        
        /* Success Page */
        .success-container {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        
        .success-icon i {
            font-size: 3em;
            color: white;
        }
        
        .success-container h2 {
            color: var(--gray-800);
            margin-bottom: 12px;
        }
        
        .success-container p {
            color: var(--gray-600);
            max-width: 400px;
            margin: 0 auto 24px;
        }
        
        /* Timer */
        .otp-timer {
            text-align: center;
            margin: 16px 0;
            color: var(--gray-600);
        }
        
        .otp-timer.expired {
            color: var(--danger);
        }
        
        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 600px) {
            body {
                padding: 0;
            }
            
            .sign-container {
                border-radius: 0;
                min-height: 100vh;
            }
            
            .sign-header {
                padding: 20px;
            }
            
            .sign-body {
                padding: 20px;
            }
            
            .progress-steps {
                margin-bottom: 24px;
            }
            
            .step-label {
                font-size: 0.7em;
            }
            
            .otp-input {
                width: 42px;
                height: 50px;
                font-size: 1.2em;
            }
        }
    </style>
</head>
<body>

<div class="loading-overlay" id="loading">
    <div class="loading-spinner"></div>
</div>

<div class="sign-container">
<?php if ($error): ?>
    <div class="sign-header">
        <h1><i class="fas fa-exclamation-triangle"></i> Error</h1>
    </div>
    <div class="sign-body">
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            <div><?php echo dol_escape_htmltag($error); ?></div>
        </div>
    </div>
<?php else: ?>
    <div class="sign-header">
        <h1><i class="fas fa-file-signature"></i> <?php echo $langs->trans('SignDocument'); ?></h1>
        <p><?php echo $langs->trans('SignDocumentDescription', dol_escape_htmltag($signerData['fullname'])); ?></p>
    </div>

    <div class="sign-body">
        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="step active" data-step="identity">
                <div class="step-icon"><i class="fas fa-user-check"></i></div>
                <span class="step-label"><?php echo $langs->trans('Identity'); ?></span>
            </div>
            <div class="step" data-step="otp">
                <div class="step-icon"><i class="fas fa-mobile-alt"></i></div>
                <span class="step-label"><?php echo $langs->trans('Verification'); ?></span>
            </div>
            <div class="step" data-step="signature">
                <div class="step-icon"><i class="fas fa-signature"></i></div>
                <span class="step-label"><?php echo $langs->trans('Signature'); ?></span>
            </div>
            <div class="step" data-step="completed">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <span class="step-label"><?php echo $langs->trans('Completed'); ?></span>
            </div>
        </div>

        <!-- Step 1: Identity Verification -->
        <div class="form-section active" id="section-identity">
            <h3 class="section-title">
                <i class="fas fa-id-card"></i>
                <?php echo $langs->trans('VerifyYourIdentity'); ?>
            </h3>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div><?php echo $langs->trans('IdentityVerificationInfo'); ?></div>
            </div>

            <div class="document-info">
                <div class="document-info-row">
                    <span class="document-info-label"><?php echo $langs->trans('Document'); ?></span>
                    <span class="document-info-value"><?php echo basename($envelope->file_path); ?></span>
                </div>
                <div class="document-info-row">
                    <span class="document-info-label"><?php echo $langs->trans('Reference'); ?></span>
                    <span class="document-info-value"><?php echo $envelope->ref; ?></span>
                </div>
                <div class="document-info-row">
                    <span class="document-info-label"><?php echo $langs->trans('ExpireDate'); ?></span>
                    <span class="document-info-value"><?php echo dol_print_date($envelope->expire_date, 'dayhour'); ?></span>
            </div>
            </div>

            <!-- Document Preview Toggle -->
            <div class="document-preview-toggle">
                <button type="button" class="btn btn-secondary btn-block" id="toggle-document-preview">
                    <i class="fas fa-eye"></i>
                    <?php echo $langs->trans('ViewDocument'); ?>
                </button>
            </div>
            <div class="document-preview-container" id="document-preview-container" style="display: none; margin-bottom: 20px;">
                <iframe id="identity-pdf-frame" src="<?php echo dol_buildpath('/signDol/public/viewpdf.php', 1).'?token='.urlencode($token); ?>" style="width: 100%; height: 400px; border: 1px solid var(--gray-300); border-radius: 8px;"></iframe>
            </div>

            <form id="identity-form">
                <div class="form-group">
                    <label for="dni"><?php echo $langs->trans('DNI'); ?> *</label>
                    <input type="text" id="dni" name="dni" placeholder="12345678A" required 
                           pattern="[0-9]{8}[A-Za-z]" maxlength="9"
                           style="text-transform: uppercase;">
                    <div class="hint"><?php echo $langs->trans('EnterYourDNI'); ?><?php if ($signerData['has_dni']) { echo ' '.$langs->trans('DNIWillBeVerified'); } ?></div>
                </div>

                <div class="form-group">
                    <label for="auth-method"><?php echo $langs->trans('VerificationMethod'); ?></label>
                    <select id="auth-method" name="method">
                        <?php if ($signerData['has_email']): ?>
                        <option value="email"><?php echo $langs->trans('Email'); ?> (<?php echo maskEmail($signerData['email']); ?>)</option>
                        <?php endif; ?>
                        <?php if ($signerData['has_phone']): ?>
                        <option value="phone"><?php echo $langs->trans('WhatsApp'); ?> (<?php echo maskPhone($signerData['phone']); ?>)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group" id="auth-value-group">
                    <label for="auth-value" id="auth-value-label"><?php echo $langs->trans('Email'); ?></label>
                    <input type="text" id="auth-value" name="value" required>
                    <div class="hint" id="auth-value-hint"><?php echo $langs->trans('EnterYourEmail'); ?></div>
                </div>

                <div id="identity-error" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <div></div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-arrow-right"></i>
                    <?php echo $langs->trans('Continue'); ?>
                </button>
            </form>
        </div>

        <!-- Step 2: OTP Verification -->
        <div class="form-section" id="section-otp">
            <h3 class="section-title">
                <i class="fas fa-shield-alt"></i>
                <?php echo $langs->trans('EnterVerificationCode'); ?>
            </h3>
            
            <div class="alert alert-info" id="otp-sent-message">
                <i class="fas fa-envelope"></i>
                <div><?php echo $langs->trans('OTPSentMessage'); ?></div>
            </div>

            <form id="otp-form">
                <div class="otp-input-container">
                    <input type="text" class="otp-input" maxlength="1" data-index="0" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" data-index="1" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" data-index="2" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" data-index="3" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" data-index="4" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" data-index="5" inputmode="numeric">
                </div>
                <input type="hidden" id="otp-code" name="code">

                <div class="otp-timer" id="otp-timer">
                    <i class="fas fa-clock"></i>
                    <span id="timer-text"><?php echo $langs->trans('CodeExpiresIn'); ?> <strong id="timer-countdown">10:00</strong></span>
                </div>

                <div id="otp-error" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <div></div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="verify-otp-btn" disabled>
                    <i class="fas fa-check"></i>
                    <?php echo $langs->trans('VerifyCode'); ?>
                </button>

                <div style="text-align: center; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" id="resend-otp-btn">
                        <i class="fas fa-redo"></i>
                        <?php echo $langs->trans('ResendCode'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Step 3: Signature -->
        <div class="form-section" id="section-signature">
            <h3 class="section-title">
                <i class="fas fa-pen-fancy"></i>
                <?php echo $langs->trans('SignTheDocument'); ?>
            </h3>

            <!-- PDF Preview -->
            <div class="pdf-preview">
                <iframe id="pdf-frame" src="<?php echo dol_buildpath('/signDol/public/viewpdf.php', 1).'?token='.urlencode($token); ?>"></iframe>
            </div>

            <form id="signature-form">
                <div class="signature-pad-container">
                    <p style="margin-bottom: 12px; color: var(--gray-600);">
                        <i class="fas fa-hand-point-down"></i>
                        <?php echo $langs->trans('DrawYourSignatureBelow'); ?>
                    </p>
                    <canvas id="signature-pad" class="signature-pad" width="600" height="200"></canvas>
                    <div class="signature-pad-actions">
                        <button type="button" class="btn btn-secondary" id="clear-signature">
                            <i class="fas fa-eraser"></i>
                            <?php echo $langs->trans('Clear'); ?>
                        </button>
                    </div>
                </div>

                <div class="terms-checkbox">
                    <input type="checkbox" id="accept-terms" name="accept_terms" value="1">
                    <label for="accept-terms">
                        <?php echo $langs->trans('AcceptSignatureTerms'); ?>
                    </label>
                </div>

                <input type="hidden" id="signature-data" name="signature_data">

                <div id="signature-error" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <div></div>
                </div>

                <button type="submit" class="btn btn-success btn-block btn-lg" id="submit-signature-btn" disabled>
                    <i class="fas fa-file-signature"></i>
                    <?php echo $langs->trans('SignNow'); ?>
                </button>
            </form>
        </div>

        <!-- Step 4: Completed -->
        <div class="form-section" id="section-completed">
            <div class="success-container">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h2><?php echo $langs->trans('SignatureRecordedSuccessfully'); ?></h2>
                <p><?php echo $langs->trans('SignatureRecordedDescription'); ?></p>
                
                <div id="download-links" style="display: none; margin-top: 24px;">
                    <a href="#" id="download-signed" class="btn btn-primary" style="margin: 8px;">
                        <i class="fas fa-file-pdf"></i>
                        <?php echo $langs->trans('DownloadSignedDocument'); ?>
                    </a>
                    <a href="#" id="download-cert" class="btn btn-secondary" style="margin: 8px;">
                        <i class="fas fa-certificate"></i>
                        <?php echo $langs->trans('DownloadCertificate'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<?php if (empty($error)): ?>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration
    const token = '<?php echo dol_escape_js($token); ?>';
    const apiUrl = '<?php echo dol_buildpath('/signDol/public/api.php', 1); ?>';
    const hasDni = <?php echo $signerData['has_dni'] ? 'true' : 'false'; ?>;
    
    // State
    let currentStep = 'identity';
    let otpExpiresAt = null;
    let timerInterval = null;
    let signaturePad = null;
    let authMethod = 'email';
    
    // Elements
    const loading = document.getElementById('loading');
    const steps = document.querySelectorAll('.step');
    const sections = document.querySelectorAll('.form-section');
    
    // Toggle document preview
    const togglePreviewBtn = document.getElementById('toggle-document-preview');
    const previewContainer = document.getElementById('document-preview-container');
    if (togglePreviewBtn && previewContainer) {
        togglePreviewBtn.addEventListener('click', function() {
            if (previewContainer.style.display === 'none') {
                previewContainer.style.display = 'block';
                this.innerHTML = '<i class="fas fa-eye-slash"></i> <?php echo $langs->trans("HideDocument"); ?>';   
            } else {
                previewContainer.style.display = 'none';
                this.innerHTML = '<i class="fas fa-eye"></i> <?php echo $langs->trans("ViewDocument"); ?>'; 
            }
        });
    }
    
    // Initialize Signature Pad
    const canvas = document.getElementById('signature-pad');
    if (canvas) {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        });
        
        // Resize canvas
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const container = canvas.parentElement;
            const width = Math.min(600, container.offsetWidth - 44);
            canvas.width = width * ratio;
            canvas.height = 200 * ratio;
            canvas.style.width = width + 'px';
            canvas.style.height = '200px';
            canvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear();
        }
        
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    }
    
    // Auth method change
    const authMethodSelect = document.getElementById('auth-method');
    const authValueLabel = document.getElementById('auth-value-label');
    const authValueInput = document.getElementById('auth-value');
    const authValueHint = document.getElementById('auth-value-hint');
    
    if (authMethodSelect) {
        authMethodSelect.addEventListener('change', function() {
            authMethod = this.value;
            if (authMethod === 'email') {
                authValueLabel.textContent = '<?php echo $langs->trans('Email'); ?>';
                authValueInput.type = 'email';
                authValueInput.placeholder = 'ejemplo@email.com';
                authValueHint.textContent = '<?php echo $langs->trans('EnterYourEmail'); ?>';
            } else {
                authValueLabel.textContent = '<?php echo $langs->trans('Phone'); ?>';
                authValueInput.type = 'tel';
                authValueInput.placeholder = '612345678';
                authValueHint.textContent = '<?php echo $langs->trans('EnterYourPhone'); ?>';
            }
        });
        authMethodSelect.dispatchEvent(new Event('change'));
    }
    
    // Go to step
    function goToStep(step) {
        currentStep = step;
        
        // Update progress
        steps.forEach(s => {
            const stepName = s.dataset.step;
            s.classList.remove('active', 'completed');
            
            const stepOrder = ['identity', 'otp', 'signature', 'completed'];
            const currentIndex = stepOrder.indexOf(step);
            const stepIndex = stepOrder.indexOf(stepName);
            
            if (stepIndex < currentIndex) {
                s.classList.add('completed');
            } else if (stepIndex === currentIndex) {
                s.classList.add('active');
            }
        });
        
        // Show section
        sections.forEach(s => s.classList.remove('active'));
        document.getElementById('section-' + step).classList.add('active');
    }
    
    // Show loading
    function showLoading() {
        loading.classList.add('active');
    }
    
    function hideLoading() {
        loading.classList.remove('active');
    }
    
    // Show error
    function showError(containerId, message) {
        const container = document.getElementById(containerId);
        if (container) {
            container.querySelector('div').textContent = message;
            container.style.display = 'flex';
        }
    }
    
    function hideError(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.display = 'none';
        }
    }
    
    // API Call
    async function apiCall(action, data = {}) {
        showLoading();
        
        const formData = new FormData();
        formData.append('token', token);
        formData.append('action', action);
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            hideLoading();
            return result;
        } catch (error) {
            hideLoading();
            return { success: false, error: '<?php echo $langs->trans('NetworkError'); ?>' };
        }
    }
    
    // Identity Form
    const identityForm = document.getElementById('identity-form');
    if (identityForm) {
        identityForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError('identity-error');
            
            const dniInput = document.getElementById('dni');
            const dni = dniInput ? dniInput.value.trim().toUpperCase() : '';
            const method = document.getElementById('auth-method').value;
            const value = document.getElementById('auth-value').value.trim();
            
            const result = await apiCall('verify_identity', { dni, method, value });
            
            if (result.success) {
                // Send OTP
                const channel = method === 'email' ? 'email' : 'whatsapp';
                const otpResult = await apiCall('send_otp', { channel: channel });
                
                if (otpResult.success) {
                    // Update OTP sent message
                    const msgEl = document.getElementById('otp-sent-message').querySelector('div');
                    if (channel === 'whatsapp') {
                        msgEl.innerHTML = '<strong>WhatsApp:</strong> ' + otpResult.destination;
                    } else {
                        msgEl.innerHTML = '<strong>Email:</strong> ' + otpResult.destination;
                    }
                    
                    // Start timer (10 minutes)
                    otpExpiresAt = Date.now() + (otpResult.expires_in_minutes || 10) * 60 * 1000;
                    startOTPTimer();
                    
                    goToStep('otp');
                } else {
                    showError('identity-error', otpResult.error);
                }
            } else {
                showError('identity-error', result.error);
            }
        });
    }
    
    // OTP Timer
    function startOTPTimer() {
        if (timerInterval) clearInterval(timerInterval);
        
        const timerEl = document.getElementById('timer-countdown');
        const timerContainer = document.getElementById('otp-timer');
        
        timerInterval = setInterval(function() {
            const remaining = otpExpiresAt - Date.now();
            
            if (remaining <= 0) {
                clearInterval(timerInterval);
                timerContainer.classList.add('expired');
                timerEl.textContent = '<?php echo $langs->trans('Expired'); ?>';
                return;
            }
            
            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);
            timerEl.textContent = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
        }, 1000);
    }
    
    // OTP Inputs
    const otpInputs = document.querySelectorAll('.otp-input');
    const otpCodeInput = document.getElementById('otp-code');
    const verifyOtpBtn = document.getElementById('verify-otp-btn');
    
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            const value = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = value;
            
            if (value && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
            
            updateOTPCode();
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                otpInputs[index - 1].focus();
            }
        });
        
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const digits = paste.replace(/[^0-9]/g, '').split('');
            
            digits.forEach((digit, i) => {
                if (otpInputs[index + i]) {
                    otpInputs[index + i].value = digit;
                }
            });
            
            updateOTPCode();
        });
    });
    
    function updateOTPCode() {
        let code = '';
        otpInputs.forEach(input => code += input.value);
        otpCodeInput.value = code;
        verifyOtpBtn.disabled = code.length !== 6;
    }
    
    // OTP Form
    const otpForm = document.getElementById('otp-form');
    if (otpForm) {
        otpForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError('otp-error');
            
            const code = otpCodeInput.value;
            const result = await apiCall('verify_otp', { code });
            
            if (result.success) {
                if (timerInterval) clearInterval(timerInterval);
                goToStep('signature');
            } else {
                showError('otp-error', result.error);
                
                if (result.blocked) {
                    // Disable form
                    verifyOtpBtn.disabled = true;
                    document.getElementById('resend-otp-btn').disabled = true;
                }
            }
        });
    }
    
    // Resend OTP
    const resendBtn = document.getElementById('resend-otp-btn');
    if (resendBtn) {
        resendBtn.addEventListener('click', async function() {
            hideError('otp-error');
            
            const method = document.getElementById('auth-method').value;
            const channel = method === 'email' ? 'email' : 'whatsapp';
            const result = await apiCall('send_otp', { channel: channel });
            
            if (result.success) {
                // Clear OTP inputs
                otpInputs.forEach(input => input.value = '');
                updateOTPCode();
                
                // Reset timer
                otpExpiresAt = Date.now() + (result.expires_in_minutes || 10) * 60 * 1000;
                document.getElementById('otp-timer').classList.remove('expired');
                startOTPTimer();
                
                // Update message
                const msgEl = document.getElementById('otp-sent-message').querySelector('div');
                if (channel === 'whatsapp') {
                    msgEl.innerHTML = '<strong>WhatsApp:</strong> ' + result.destination;
                } else {
                    msgEl.innerHTML = '<strong>Email:</strong> ' + result.destination;
                }
            } else {
                showError('otp-error', result.error);
            }
        });
    }
    
    // Signature form
    const signatureForm = document.getElementById('signature-form');
    const acceptTerms = document.getElementById('accept-terms');
    const submitSignatureBtn = document.getElementById('submit-signature-btn');
    const clearSignatureBtn = document.getElementById('clear-signature');
    
    function updateSignatureButton() {
        const hasSignature = signaturePad && !signaturePad.isEmpty();
        const termsAccepted = acceptTerms && acceptTerms.checked;
        submitSignatureBtn.disabled = !(hasSignature && termsAccepted);
    }
    
    if (acceptTerms) {
        acceptTerms.addEventListener('change', updateSignatureButton);
    }
    
    if (canvas) {
        canvas.addEventListener('mouseup', updateSignatureButton);
        canvas.addEventListener('touchend', updateSignatureButton);
    }
    
    if (clearSignatureBtn) {
        clearSignatureBtn.addEventListener('click', function() {
            signaturePad.clear();
            updateSignatureButton();
        });
    }
    
    if (signatureForm) {
        signatureForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError('signature-error');
            
            if (signaturePad.isEmpty()) {
                showError('signature-error', '<?php echo $langs->trans('ErrorSignatureRequired'); ?>');
                return;
            }
            
            const signatureData = signaturePad.toDataURL('image/png');
            
            const result = await apiCall('submit_signature', {
                signature_data: signatureData,
                accept_terms: 1
            });
            
            if (result.success) {
                // Show download links if completed
                if (result.completed && result.signed_document_url) {
                    const downloadLinks = document.getElementById('download-links');
                    downloadLinks.style.display = 'block';
                    
                    if (result.signed_document_url) {
                        document.getElementById('download-signed').href = result.signed_document_url;
                    }
                    if (result.certificate_url) {
                        document.getElementById('download-cert').href = result.certificate_url;
                    }
                }
                
                goToStep('completed');
            } else {
                showError('signature-error', result.error);
            }
        });
    }
    
    // Check initial status
    async function checkStatus() {
        const result = await apiCall('get_status');
        
        if (result.success) {
            // Go to appropriate step based on status
            if (result.step === 'completed') {
                goToStep('completed');
            } else if (result.step === 'signature') {
                goToStep('signature');
            } else if (result.step === 'otp_verify' && result.otp_active) {
                otpExpiresAt = new Date(result.otp_expires_at).getTime();
                startOTPTimer();
                goToStep('otp');
            }
        }
    }
    
    checkStatus();
});
</script>
<?php endif; ?>

</body>
</html>
