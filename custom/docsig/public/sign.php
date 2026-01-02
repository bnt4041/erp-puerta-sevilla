<?php
/* Copyright (C) 2026 Document Signature Module
 * Public signature page
 */

// Minimal Dolibarr load for public pages
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = include '../../../main.inc.php';
if (!$res) die("Main include failed");

require_once __DIR__.'/../class/docsignature.class.php';
require_once __DIR__.'/../class/docsigenvelope.class.php';
require_once __DIR__.'/../class/docsigaudittrail.class.php';

// No authentication required for public page
$user->getrights(); // Load rights

$token = GETPOST('token', 'alpha');
$action = GETPOST('action', 'aZ09');

if (empty($token)) {
	header('HTTP/1.0 400 Bad Request');
	die('Invalid token');
}

// Load signature
$signature = new DocsigSignature($db);
$result = $signature->fetch(null, $token);

if ($result <= 0) {
	header('HTTP/1.0 404 Not Found');
	die('Signature request not found');
}

// Load envelope
$envelope = new DocsigEnvelope($db);
$envelope->fetch($signature->fk_envelope);

// Check if valid
if (!$signature->isTokenValid()) {
	$expired = true;
} else {
	$expired = false;
}

// Process actions
$message = '';
$error = '';

/*
 * Step 1: Validate DNI + Email
 */
if ($action == 'validate_identity') {
	$dni = GETPOST('dni', 'alpha');
	$email = GETPOST('email', 'email');

	$dni = trim(strtoupper($dni));
	$email = strtolower(trim($email));

	// Validate DNI matches
	if (!empty($signature->signer_dni) && $signature->signer_dni != $dni) {
		$error = 'DNI does not match';
	}
	// Validate email matches
	elseif ($signature->signer_email != $email) {
		$error = 'Email does not match';
	}
	else {
		// Generate and send OTP
		$result = $signature->generateOTP();
		
		if ($result > 0) {
			$message = 'Verification code sent to your email';
			$signature->status = DocsigSignature::STATUS_OPENED;
			$signature->link_opened_count++;
			if (!$signature->first_opened_date) {
				$signature->first_opened_date = dol_now();
			}
			$signature->update();

			// Audit
			$envelope->addAuditEvent('link_opened', array(
				'email' => $email,
				'dni' => $dni,
			), null, $signature->id);

			$envelope->addAuditEvent('otp_sent', array(), null, $signature->id);
		} else {
			$error = $signature->error ? $signature->error : 'Failed to send verification code';
		}
	}
}

/*
 * Step 2: Validate OTP
 */
elseif ($action == 'validate_otp') {
	$otp = GETPOST('otp', 'alpha');

	$result = $signature->validateOTP($otp);
	
	if ($result > 0) {
		$message = 'Identity verified. You can now sign the document.';
		
		// Audit
		$envelope->addAuditEvent('otp_validated', array(), null, $signature->id);
	} else {
		$error = $signature->error ? $signature->error : 'Invalid verification code';
		
		// Audit
		$envelope->addAuditEvent('otp_failed', array(
			'attempts' => $signature->otp_attempts,
		), null, $signature->id);
	}
}

/*
 * Step 3: Submit signature
 */
elseif ($action == 'sign') {
	// Check authenticated
	if ($signature->status < DocsigSignature::STATUS_AUTHENTICATED) {
		$error = 'Not authenticated';
	} else {
		$signatureImage = GETPOST('signature_image', 'alpha');
		$signatureName = GETPOST('signature_name', 'restricthtml');

		if (empty($signatureImage)) {
			$error = 'Please provide your signature';
		} else {
			// Save signature
			$signature->signature_image = $signatureImage;
			$signature->signature_date = dol_now();
			$signature->signature_ip = getUserRemoteIP();
			$signature->signature_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
			$signature->status = DocsigSignature::STATUS_SIGNED;
			$signature->update();

			// Update envelope
			$envelope->nb_signed++;
			$envelope->last_activity = dol_now();
			
			if ($envelope->nb_signed >= $envelope->nb_signers) {
				$envelope->status = DocsigEnvelope::STATUS_COMPLETED;
			} elseif ($envelope->status == DocsigEnvelope::STATUS_SENT) {
				$envelope->status = DocsigEnvelope::STATUS_IN_PROGRESS;
			}
			
			$envelope->update($user);

			// Audit
			$envelope->addAuditEvent('signature_completed', array(
				'name' => $signatureName,
			), null, $signature->id);

			// Process PDF signing (in background or immediately)
			require_once __DIR__.'/../class/docsigpdfsigner.class.php';
			
			$inputPDF = DOL_DATA_ROOT.'/'.$envelope->document_path;
			$outputPDF = preg_replace('/\.pdf$/i', '_signed.pdf', $inputPDF);

			// Collect all signatures
			$sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_signature";
			$sql .= " WHERE fk_envelope = ".(int)$envelope->id;
			$sql .= " AND status = ".DocsigSignature::STATUS_SIGNED;
			
			$allSignatures = array();
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$allSignatures[] = array(
						'name' => $obj->signer_name,
						'email' => $obj->signer_email,
						'image' => $obj->signature_image,
						'date' => $db->jdate($obj->signature_date),
					);
				}
			}

			// Sign PDF
			$signer = new DocsigPDFSigner($db);
			$result = $signer->signPDF($inputPDF, $outputPDF, $allSignatures);

			if ($result > 0) {
				// Update envelope with signed document
				$envelope->signed_document_path = str_replace(DOL_DATA_ROOT.'/', '', $outputPDF);
				$envelope->signed_document_hash = DocsigPDFSigner::calculateHash($outputPDF);
				$envelope->update($user);

				$envelope->addAuditEvent('document_signed', array(
					'hash' => $envelope->signed_document_hash,
				), null);

				// Generate certificate if all signed
				if ($envelope->status == DocsigEnvelope::STATUS_COMPLETED) {
					require_once __DIR__.'/../class/docsigcertificate.class.php';
					
					$certificate = new DocsigCertificate($db);
					$certResult = $certificate->generateCertificate($envelope);
					
					if ($certResult > 0) {
						$envelope->addAuditEvent('certificate_generated', array(
							'path' => $envelope->certificate_path,
						), null);
					}
				}
			}

			$message = 'Document signed successfully!';
		}
	}
}

/*
 * Resend OTP
 */
elseif ($action == 'resend_otp') {
	$result = $signature->generateOTP();
	
	if ($result > 0) {
		$message = 'Verification code resent';
		$envelope->addAuditEvent('otp_resent', array(), null, $signature->id);
	} else {
		$error = $signature->error;
	}
}

// Determine current step
$step = 'identity'; // identity, otp, sign, complete

if ($signature->status == DocsigSignature::STATUS_SIGNED) {
	$step = 'complete';
} elseif ($signature->status >= DocsigSignature::STATUS_AUTHENTICATED) {
	$step = 'sign';
} elseif ($signature->status == DocsigSignature::STATUS_OPENED && !empty($signature->otp_code)) {
	$step = 'otp';
}

// HTML output
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Document Signature - <?php echo dol_escape_htmltag($envelope->ref); ?></title>
	<link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/theme/<?php echo $conf->theme; ?>/style.css.php">
	<link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/custom/docsig/css/sign.css">
	<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
</head>
<body class="docsig-public">
	<div class="docsig-container">
		<div class="docsig-header">
			<h1><i class="fa fa-file-signature"></i> Document Signature</h1>
		</div>

		<?php if ($expired): ?>
			<div class="docsig-error">
				<h2>Link Expired</h2>
				<p>This signature request has expired or been cancelled.</p>
			</div>
		<?php else: ?>

			<?php if ($message): ?>
				<div class="docsig-message success"><?php echo $message; ?></div>
			<?php endif; ?>

			<?php if ($error): ?>
				<div class="docsig-message error"><?php echo $error; ?></div>
			<?php endif; ?>

			<div class="docsig-info">
				<h3><?php echo dol_escape_htmltag($envelope->document_name); ?></h3>
				<p><strong>Reference:</strong> <?php echo $envelope->ref; ?></p>
				<p><strong>Signer:</strong> <?php echo dol_escape_htmltag($signature->signer_name); ?></p>
				<?php if ($envelope->expiration_date): ?>
					<p><strong>Valid until:</strong> <?php echo dol_print_date($envelope->expiration_date, 'dayhour'); ?></p>
				<?php endif; ?>
			</div>

			<?php if ($step == 'identity'): ?>
				<!-- Step 1: Identity verification -->
				<form method="post" class="docsig-form">
					<input type="hidden" name="token" value="<?php echo dol_escape_htmltag($token); ?>">
					<input type="hidden" name="action" value="validate_identity">
					
					<h3>Step 1: Verify Your Identity</h3>
					
					<div class="form-group">
						<label for="dni">DNI / Document ID *</label>
						<input type="text" id="dni" name="dni" required class="form-control" placeholder="12345678A">
						<small>Enter your identification document number</small>
					</div>

					<div class="form-group">
						<label for="email">Email *</label>
						<input type="email" id="email" name="email" required class="form-control" 
							value="<?php echo dol_escape_htmltag($signature->signer_email); ?>" readonly>
					</div>

					<button type="submit" class="btn btn-primary">Send Verification Code</button>
				</form>

			<?php elseif ($step == 'otp'): ?>
				<!-- Step 2: OTP verification -->
				<form method="post" class="docsig-form">
					<input type="hidden" name="token" value="<?php echo dol_escape_htmltag($token); ?>">
					<input type="hidden" name="action" value="validate_otp">
					
					<h3>Step 2: Enter Verification Code</h3>
					
					<p>A 6-digit verification code has been sent to your email.</p>

					<div class="form-group">
						<label for="otp">Verification Code *</label>
						<input type="text" id="otp" name="otp" required class="form-control" 
							maxlength="6" pattern="[0-9]{6}" placeholder="123456" autofocus>
						<small>Code expires in <?php echo !empty($conf->global->DOCSIG_OTP_EXPIRY_MINUTES) ? $conf->global->DOCSIG_OTP_EXPIRY_MINUTES : 10; ?> minutes</small>
					</div>

					<div class="form-actions">
						<button type="submit" class="btn btn-primary">Verify Code</button>
						<a href="?token=<?php echo urlencode($token); ?>&action=resend_otp" class="btn btn-secondary">Resend Code</a>
					</div>

					<p class="attempts-info">Attempts remaining: <?php echo max(0, 5 - $signature->otp_attempts); ?></p>
				</form>

			<?php elseif ($step == 'sign'): ?>
				<!-- Step 3: Sign document -->
				<form method="post" id="sign-form" class="docsig-form">
					<input type="hidden" name="token" value="<?php echo dol_escape_htmltag($token); ?>">
					<input type="hidden" name="action" value="sign">
					<input type="hidden" name="signature_image" id="signature_image">
					
					<h3>Step 3: Sign the Document</h3>

					<div class="form-group">
						<label for="signature_name">Full Name *</label>
						<input type="text" id="signature_name" name="signature_name" required class="form-control"
							value="<?php echo dol_escape_htmltag($signature->signer_name); ?>">
					</div>

					<div class="form-group">
						<label>Draw Your Signature *</label>
						<div class="signature-pad-wrapper">
							<canvas id="signature-pad" class="signature-pad"></canvas>
						</div>
						<button type="button" id="clear-signature" class="btn btn-secondary btn-sm">Clear</button>
					</div>

					<div class="form-check">
						<input type="checkbox" id="agree" name="agree" required class="form-check-input">
						<label for="agree" class="form-check-label">
							I agree to electronically sign this document and accept that it has the same legal validity as a handwritten signature.
						</label>
					</div>

					<button type="submit" class="btn btn-success btn-lg">Sign Document</button>
				</form>

				<script>
				document.addEventListener('DOMContentLoaded', function() {
					const canvas = document.getElementById('signature-pad');
					const signaturePad = new SignaturePad(canvas);

					// Resize canvas
					function resizeCanvas() {
						const ratio = Math.max(window.devicePixelRatio || 1, 1);
						canvas.width = canvas.offsetWidth * ratio;
						canvas.height = canvas.offsetHeight * ratio;
						canvas.getContext("2d").scale(ratio, ratio);
						signaturePad.clear();
					}
					window.addEventListener('resize', resizeCanvas);
					resizeCanvas();

					// Clear button
					document.getElementById('clear-signature').addEventListener('click', function() {
						signaturePad.clear();
					});

					// Form submit
					document.getElementById('sign-form').addEventListener('submit', function(e) {
						if (signaturePad.isEmpty()) {
							e.preventDefault();
							alert('Please provide your signature');
							return false;
						}

						const dataURL = signaturePad.toDataURL();
						document.getElementById('signature_image').value = dataURL;
					});
				});
				</script>

			<?php elseif ($step == 'complete'): ?>
				<!-- Step 4: Complete -->
				<div class="docsig-success">
					<div class="success-icon">✓</div>
					<h2>Document Signed Successfully!</h2>
					<p>Your signature has been recorded on <?php echo dol_print_date($signature->signature_date, 'dayhour'); ?></p>
					
					<?php if ($envelope->status == DocsigEnvelope::STATUS_COMPLETED && $envelope->signed_document_path): ?>
						<p>You can download the signed document below:</p>
						<a href="<?php echo DOL_URL_ROOT; ?>/document.php?modulepart=docsig&file=<?php echo urlencode($envelope->signed_document_path); ?>" 
							class="btn btn-primary" target="_blank">
							<i class="fa fa-download"></i> Download Signed Document
						</a>
					<?php else: ?>
						<p>The document is waiting for other signers. You will receive a copy once all signatures are collected.</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		<?php endif; ?>

		<div class="docsig-footer">
			<p>Powered by Docsig - Secure Document Signatures</p>
		</div>
	</div>
</body>
</html>
