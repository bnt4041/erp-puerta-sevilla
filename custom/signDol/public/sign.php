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
 * \brief   Página pública de firma de documentos
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

// Load translations
$langs->loadLangs(array('docsig@signDol', 'other'));

// Get token parameter
$token = GETPOST('token', 'aZ09');
$action = GETPOST('action', 'aZ09');

// Validate token and get signer
$signer = null;
$envelope = null;
$error = '';
$success = false;

if (empty($token)) {
    $error = $langs->trans('ErrorInvalidToken');
} else {
    // Find signer by token hash
    dol_include_once('/signDol/lib/docsig.lib.php');
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
        // Get envelope
        else {
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
    } else {
        $error = $langs->trans('ErrorInvalidToken');
    }
}

// Process signature
if ($action == 'sign' && empty($error) && $signer && $envelope) {
    $signatureData = GETPOST('signature_data', 'restricthtml');
    $acceptTerms = GETPOSTINT('accept_terms');

    if (empty($signatureData)) {
        $error = $langs->trans('ErrorSignatureRequired');
    } elseif (!$acceptTerms) {
        $error = $langs->trans('ErrorAcceptTermsRequired');
    } else {
        // Record signature
        $result = $signer->recordSignature($signatureData, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        if ($result > 0) {
            // Log event
            $envelope->logEvent('SIGNER_SIGNED', $langs->trans('SignerSignedDocument', $signer->getFullName()));

            // Check if all signers have signed
            $envelope->checkCompletion();

            $success = true;
        } else {
            $error = $langs->trans('ErrorRecordingSignature');
        }
    }
}

/*
 * View
 */

$title = $langs->trans('SignDocument');

// Custom header for public page
?>
<!DOCTYPE html>
<html lang="<?php echo $langs->defaultlang; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo dol_buildpath('/signDol/css/public.css', 1); ?>">
    <style>
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
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
        }
        .sign-header {
            background: #f8f9fa;
            padding: 24px;
            border-bottom: 1px solid #e9ecef;
        }
        .sign-header h1 {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 8px;
        }
        .sign-header p {
            color: #666;
            font-size: 0.9em;
        }
        .sign-body {
            padding: 24px;
        }
        .sign-section {
            margin-bottom: 24px;
        }
        .sign-section h2 {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sign-section h2 .icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #667eea;
            color: white;
            border-radius: 50%;
            font-size: 0.8em;
        }
        .document-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
        }
        .document-info table {
            width: 100%;
        }
        .document-info td {
            padding: 6px 0;
        }
        .document-info td:first-child {
            font-weight: 500;
            color: #666;
            width: 140px;
        }
        .pdf-preview {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .pdf-preview iframe {
            width: 100%;
            height: 400px;
            border: none;
        }
        .signature-pad-container {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            background: #fafafa;
        }
        .signature-pad {
            border: 1px solid #ccc;
            border-radius: 4px;
            background: white;
            touch-action: none;
        }
        .signature-pad-actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-secondary {
            background: #e9ecef;
            color: #495057;
        }
        .btn-secondary:hover {
            background: #dee2e6;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a6fd6;
        }
        .btn-primary:disabled {
            background: #adb5bd;
            cursor: not-allowed;
        }
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 16px 0;
        }
        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
        }
        .checkbox-group label {
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
        }
        .checkbox-group a {
            color: #667eea;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid #e9ecef;
        }
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px;
        }
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .success-container {
            text-align: center;
            padding: 60px 20px;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .success-icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        .success-container h2 {
            color: #333;
            margin-bottom: 12px;
        }
        .success-container p {
            color: #666;
            max-width: 400px;
            margin: 0 auto;
        }
        @media (max-width: 600px) {
            .sign-container {
                border-radius: 0;
            }
            .sign-header, .sign-body {
                padding: 16px;
            }
            .pdf-preview iframe {
                height: 300px;
            }
        }
    </style>
</head>
<body>

<div class="sign-container">
<?php if ($error): ?>
    <div class="alert alert-danger">
        <strong><?php echo $langs->trans('Error'); ?>:</strong> <?php echo $error; ?>
    </div>
<?php elseif ($success): ?>
    <div class="success-container">
        <div class="success-icon">
            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
        <h2><?php echo $langs->trans('SignatureRecordedSuccessfully'); ?></h2>
        <p><?php echo $langs->trans('SignatureRecordedDescription'); ?></p>
    </div>
<?php else: ?>
    <div class="sign-header">
        <h1><?php echo img_picto('', 'fa-file-signature', 'class="pictofixedwidth"').$langs->trans('SignDocument'); ?></h1>
        <p><?php echo $langs->trans('SignDocumentDescription', $signer->getFullName()); ?></p>
    </div>

    <form method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>" id="sign-form">
        <input type="hidden" name="token" value="<?php echo dol_escape_htmltag($token); ?>">
        <input type="hidden" name="action" value="sign">
        <input type="hidden" name="signature_data" id="signature_data" value="">

        <div class="sign-body">
            <!-- Document Info -->
            <div class="sign-section">
                <h2>
                    <span class="icon">1</span>
                    <?php echo $langs->trans('DocumentInformation'); ?>
                </h2>
                <div class="document-info">
                    <table>
                        <tr>
                            <td><?php echo $langs->trans('Document'); ?>:</td>
                            <td><?php echo basename($envelope->file_path); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('Reference'); ?>:</td>
                            <td><?php echo $envelope->ref; ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('CreatedBy'); ?>:</td>
                            <td>
                                <?php
                                $usertmp = new User($db);
                                if ($usertmp->fetch($envelope->fk_user_creat) > 0) {
                                    echo $usertmp->getFullName($langs);
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('ExpireDate'); ?>:</td>
                            <td><?php echo dol_print_date($envelope->expire_date, 'dayhour'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- PDF Preview -->
            <div class="sign-section">
                <h2>
                    <span class="icon">2</span>
                    <?php echo $langs->trans('ReviewDocument'); ?>
                </h2>
                <div class="pdf-preview">
                    <iframe src="<?php echo dol_buildpath('/signDol/public/viewpdf.php', 1).'?token='.$token; ?>"></iframe>
                </div>
            </div>

            <!-- Signature -->
            <div class="sign-section">
                <h2>
                    <span class="icon">3</span>
                    <?php echo $langs->trans('DrawYourSignature'); ?>
                </h2>
                <div class="signature-pad-container">
                    <canvas id="signature-pad" class="signature-pad" width="600" height="200"></canvas>
                    <div class="signature-pad-actions">
                        <button type="button" class="btn btn-secondary" id="clear-signature">
                            <?php echo $langs->trans('Clear'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Terms -->
            <div class="checkbox-group">
                <input type="checkbox" name="accept_terms" id="accept_terms" value="1">
                <label for="accept_terms">
                    <?php echo $langs->trans('AcceptSignatureTerms'); ?>
                </label>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submit-signature" disabled>
                    <?php echo $langs->trans('SignNow'); ?>
                </button>
            </div>
        </div>
    </form>
<?php endif; ?>
</div>

<?php if (!$error && !$success): ?>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('signature-pad');
    var signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)'
    });

    // Resize canvas
    function resizeCanvas() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        var container = canvas.parentElement;
        canvas.width = Math.min(600, container.offsetWidth - 32) * ratio;
        canvas.height = 200 * ratio;
        canvas.style.width = Math.min(600, container.offsetWidth - 32) + 'px';
        canvas.style.height = '200px';
        canvas.getContext('2d').scale(ratio, ratio);
        signaturePad.clear();
    }

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    // Clear button
    document.getElementById('clear-signature').addEventListener('click', function() {
        signaturePad.clear();
        updateSubmitButton();
    });

    // Enable/disable submit button
    var acceptTerms = document.getElementById('accept_terms');
    var submitBtn = document.getElementById('submit-signature');

    function updateSubmitButton() {
        var hasSignature = !signaturePad.isEmpty();
        var termsAccepted = acceptTerms.checked;
        submitBtn.disabled = !(hasSignature && termsAccepted);
    }

    acceptTerms.addEventListener('change', updateSubmitButton);
    canvas.addEventListener('mouseup', updateSubmitButton);
    canvas.addEventListener('touchend', updateSubmitButton);

    // Form submit
    document.getElementById('sign-form').addEventListener('submit', function(e) {
        if (signaturePad.isEmpty()) {
            e.preventDefault();
            alert('<?php echo $langs->trans('ErrorSignatureRequired'); ?>');
            return false;
        }

        document.getElementById('signature_data').value = signaturePad.toDataURL('image/png');
        submitBtn.disabled = true;
        submitBtn.textContent = '<?php echo $langs->trans('Processing'); ?>...';
    });
});
</script>
<?php endif; ?>

</body>
</html>
