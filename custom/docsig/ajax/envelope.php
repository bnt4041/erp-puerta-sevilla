<?php
/* Copyright (C) 2026 Document Signature Module
 * AJAX endpoint: Create envelope and request signatures
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = include '../../main.inc.php';
if (!$res && file_exists("../../../main.inc.php")) $res = include '../../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/../class/docsigenvelope.class.php';
require_once __DIR__.'/../class/docsignature.class.php';
require_once __DIR__.'/../class/docsignotification.class.php';
require_once __DIR__.'/../class/docsigpdfsigner.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/actioncomm.class.php';

// Globals from Dolibarr
global $db, $conf, $user, $langs;

function docsig_json_response($payload, $httpCode = 200)
{
	if (!headers_sent()) {
		http_response_code((int) $httpCode);
		header('Content-Type: application/json');
	}
	echo json_encode($payload);
	exit;
}

// Parse JSON input (may be empty for GET actions)
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = array();

$action = GETPOST('action', 'aZ09');
if (empty($action) && !empty($input['action'])) $action = (string) $input['action'];

$elementType = GETPOST('element_type', 'alpha');
if (empty($elementType) && !empty($input['element_type'])) $elementType = (string) $input['element_type'];

$elementId = (int) GETPOST('element_id', 'int');
if (empty($elementId) && !empty($input['element_id'])) $elementId = (int) $input['element_id'];

// Require a logged-in user for all actions in this endpoint
if (empty($user) || empty($user->id)) {
	docsig_json_response(array('success' => false, 'error' => 'Not authenticated'), 401);
}

// Check user permissions (read is enough for status, write required for create/cancel/contact)
$needsWrite = in_array($action, array('create_envelope', 'cancel_envelope', 'create_contact'), true);
if ($needsWrite && empty($user->rights->docsig->envelope->write)) {
	docsig_json_response(array('success' => false, 'error' => 'Access forbidden: missing permission docsig->envelope->write'), 403);
}
if (!$needsWrite && empty($user->rights->docsig->envelope->read)) {
	docsig_json_response(array('success' => false, 'error' => 'Access forbidden: missing permission docsig->envelope->read'), 403);
}

$response = array('success' => false);

/*
 * Create envelope
 */
if ($action == 'create_envelope') {
	if (empty($elementType) || empty($elementId)) {
		docsig_json_response(array('success' => false, 'error' => 'Missing element_type or element_id'), 400);
	}

	$db->begin();

	try {
		$documentPath = $input['document_path'];
		$documentName = $input['document_name'];
		$signatureMode = $input['signature_mode'] ?? 'parallel';
		$expirationDays = $input['expiration_days'] ?? (!empty($conf->global->DOCSIG_EXPIRATION_DAYS) ? $conf->global->DOCSIG_EXPIRATION_DAYS : 30);
		$customMessage = $input['custom_message'] ?? '';
		$signers = $input['signers'] ?? array();

		// Validate document exists
		$fullPath = DOL_DATA_ROOT.'/'.$documentPath;
		if (!file_exists($fullPath)) {
			throw new Exception('Document not found');
		}

		// Calculate hash
		$documentHash = DocsigPDFSigner::calculateHash($fullPath);

		// Create envelope
		$envelope = new DocsigEnvelope($db);
		$envelope->element_type = $elementType;
		$envelope->element_id = $elementId;
		$envelope->document_path = $documentPath;
		$envelope->document_hash = $documentHash;
		$envelope->document_name = $documentName;
		$envelope->signature_mode = $signatureMode;
		$envelope->expiration_date = dol_now() + ($expirationDays * 24 * 3600);
		$envelope->custom_message = $customMessage;
		$envelope->status = DocsigEnvelope::STATUS_DRAFT;

		$envelopeId = $envelope->create($user);
		
		if ($envelopeId < 0) {
			throw new Exception('Failed to create envelope');
		}

		// Add signers
		$signatureIds = array();
		foreach ($signers as $index => $signer) {
			$signature = new DocsigSignature($db);
			$signature->fk_envelope = $envelopeId;
			$signature->fk_socpeople = $signer['id'];
			$signature->signer_name = $signer['name'];
			$signature->signer_email = $signer['email'];
			$signature->signer_dni = (!empty($signer['dni']) ? $signer['dni'] : (!empty($signer['tva_intra']) ? $signer['tva_intra'] : ''));
			$signature->signer_order = $signatureMode == 'ordered' ? $index : 0;
			$signature->status = DocsigSignature::STATUS_PENDING;

			$sigId = $signature->create($user);
			
			if ($sigId < 0) {
				throw new Exception('Failed to create signature for '.$signer['name']);
			}

			$signatureIds[] = $sigId;
		}

		// Update envelope status to sent
		$envelope->status = DocsigEnvelope::STATUS_SENT;
		$envelope->update($user);

		// Send notifications
		foreach ($signatureIds as $sigId) {
			$signature = new DocsigSignature($db);
			$signature->fetch($sigId);

			$signUrl = dol_buildpath('/custom/docsig/public/sign.php', 2).'?token='.$signature->token_plain;
			
			DocsigNotification::sendSignatureRequest($signature, $signUrl, $customMessage);
		}

		// Audit trail
		$envelope->addAuditEvent('envelope_sent', array(
			'signers_count' => count($signers),
			'mode' => $signatureMode,
		), $user);

		// Agenda event "Firma"
		$tpSocid = 0;
		$mapsoc = array(
			'contract' => array('table' => 'contrat', 'socid' => 'fk_soc'),
			'order' => array('table' => 'commande', 'socid' => 'socid'),
			'propal' => array('table' => 'propal', 'socid' => 'fk_soc'),
			'invoice' => array('table' => 'facture', 'socid' => 'socid'),
		);
		if (!empty($mapsoc[$elementType])) {
			$cfgsoc = $mapsoc[$elementType];
			$sqlsoc = "SELECT ".$cfgsoc['socid']." as socid FROM ".MAIN_DB_PREFIX.$cfgsoc['table']." WHERE rowid = ".((int) $elementId);
			$resqlsoc = $db->query($sqlsoc);
			if ($resqlsoc) { $objsoc = $db->fetch_object($resqlsoc); if ($objsoc) $tpSocid = (int) $objsoc->socid; }
		}
		$actioncomm = new ActionComm($db);
		$actioncomm->type_code = 'AC_DOCSIG_SIGNATURE';
		$actioncomm->label = 'Solicitud de firma enviada';
		$actioncomm->note_private = 'Sobre '.$envelope->ref.' enviado a '.count($signatureIds).' firmantes';
		$actioncomm->datep = dol_now();
		if ($tpSocid) $actioncomm->fk_soc = $tpSocid;
		$actioncomm->userownerid = $user->id;
		$actioncomm->create($user);

		$db->commit();

		$response = array(
			'success' => true,
			'envelope_id' => $envelopeId,
			'ref' => $envelope->ref,
			'message' => 'Signature request sent to '.count($signers).' signer(s)',
		);

	} catch (Exception $e) {
		$db->rollback();
		$response = array(
			'success' => false,
			'error' => $e->getMessage(),
		);
	}
}

/*
 * Get envelope status
 */
elseif ($action == 'get_envelope_status') {
	$envelopeId = (int) GETPOST('envelope_id', 'int');

	// Support lookup by element_type + element_id (used by the frontend)
	if (empty($envelopeId) && !empty($elementType) && !empty($elementId)) {
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."docsig_envelope";
		$sql .= " WHERE entity IN (".getEntity('docsigenvelope').")";
		$sql .= " AND element_type = '".$db->escape($elementType)."'";
		$sql .= " AND element_id = ".((int) $elementId);
		$sql .= " ORDER BY rowid DESC";
		$sql .= " LIMIT 1";
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) $envelopeId = (int) $obj->rowid;
		}
	}

	$envelope = new DocsigEnvelope($db);
	if ($envelope->fetch($envelopeId) > 0) {
		// Get signatures
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_signature";
		$sql .= " WHERE fk_envelope = ".(int)$envelopeId;
		$sql .= " ORDER BY signer_order ASC, rowid ASC";

		$signatures = array();
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$tmpSig = new DocsigSignature($db);
				$tmpSig->status = (int) $obj->status;
				$signatures[] = array(
					'id' => $obj->rowid,
					'name' => $obj->signer_name,
					'email' => $obj->signer_email,
					'status' => $obj->status,
					'status_label' => $tmpSig->getLibStatut(1),
					'signed_date' => $db->jdate($obj->signature_date),
					'token' => $obj->token_plain,
				);
			}
		}

		$response = array(
			'success' => true,
			'envelope' => array(
				'id' => $envelope->id,
				'ref' => $envelope->ref,
				'status' => $envelope->status,
				'status_label' => $envelope->getLibStatut(1),
				'document_name' => $envelope->document_name,
				'signed_document_path' => $envelope->signed_document_path,
				'certificate_path' => $envelope->certificate_path,
				'nb_signers' => $envelope->nb_signers,
				'nb_signed' => $envelope->nb_signed,
			),
			'signatures' => $signatures,
		);
	} else {
		$response = array(
			'success' => false,
			'error' => 'Envelope not found',
		);
	}
}

/* Init context: default thirdparty and contacts */
elseif ($action == 'init_context') {
	if (empty($elementType) || empty($elementId)) {
		$response = array('success' => false, 'error' => 'Missing element_type or element_id');
	} else {
		$map = array(
			'contract' => array('table' => 'contrat', 'pk' => 'rowid', 'socid' => 'fk_soc', 'element' => 'contrat'),
			'order' => array('table' => 'commande', 'pk' => 'rowid', 'socid' => 'socid', 'element' => 'commande'),
			'propal' => array('table' => 'propal', 'pk' => 'rowid', 'socid' => 'fk_soc', 'element' => 'propal'),
			'invoice' => array('table' => 'facture', 'pk' => 'rowid', 'socid' => 'socid', 'element' => 'facture'),
		);
		if (empty($map[$elementType])) {
			$response = array('success' => false, 'error' => 'Unsupported element_type');
		} else {
			$cfg = $map[$elementType];
			$socid = 0; $thirdparty = null; $defaultSigners = array(); $contactsOfThirdparty = array();
			$sql = "SELECT ".$cfg['socid']." as socid FROM ".MAIN_DB_PREFIX.$cfg['table']." WHERE ".$cfg['pk']." = ".((int)$elementId);
			$resql = $db->query($sql);
			if ($resql) { $obj = $db->fetch_object($resql); if ($obj) $socid = (int) $obj->socid; }
			if ($socid > 0) {
				$sql = "SELECT s.rowid, s.nom, s.email, s.tva_intra FROM ".MAIN_DB_PREFIX."societe s WHERE s.rowid = ".$socid;
				$resql = $db->query($sql);
				if ($resql) { $tp = $db->fetch_object($resql); if ($tp) { $thirdparty = array('id' => (int)$tp->rowid, 'name' => $tp->nom, 'email' => $tp->email, 'tva_intra' => $tp->tva_intra); } }
				$sql = "SELECT sp.rowid, sp.lastname, sp.firstname, sp.email, sp.phone_pro, sp.phone_mobile, ef.options_docsig_dni as dni FROM ".MAIN_DB_PREFIX."element_contact ec JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = ec.fk_socpeople LEFT JOIN ".MAIN_DB_PREFIX."socpeople_extrafields ef ON ef.fk_object = sp.rowid WHERE ec.element = '".$db->escape($cfg['element'])."' AND ec.fk_element = ".((int)$elementId);
				$resql = $db->query($sql);
				if ($resql) { while ($c = $db->fetch_object($resql)) { $defaultSigners[] = array('id' => (int)$c->rowid, 'name' => trim(($c->firstname ? $c->firstname.' ' : '').$c->lastname), 'email' => $c->email, 'dni' => $c->dni); } }
				$sql = "SELECT sp.rowid, sp.lastname, sp.firstname, sp.email, sp.phone_pro, sp.phone_mobile, ef.options_docsig_dni as dni FROM ".MAIN_DB_PREFIX."socpeople sp LEFT JOIN ".MAIN_DB_PREFIX."socpeople_extrafields ef ON ef.fk_object = sp.rowid WHERE sp.fk_soc = ".$socid;
				$resql = $db->query($sql);
				if ($resql) { while ($c = $db->fetch_object($resql)) { $contactsOfThirdparty[] = array('id' => (int)$c->rowid, 'name' => trim(($c->firstname ? $c->firstname.' ' : '').$c->lastname), 'email' => $c->email, 'phone' => ($c->phone_pro ?: $c->phone_mobile), 'dni' => $c->dni); } }
			}
			$response = array('success' => true, 'socid' => $socid, 'thirdparty' => $thirdparty, 'default_signers' => $defaultSigners, 'contacts' => $contactsOfThirdparty);
		}
	}
}

/* Search contacts */
elseif ($action == 'search_contacts') {
	$q = trim((string) GETPOST('q', 'restricthtml'));
	$limit = (int) GETPOST('limit', 'int'); if ($limit <= 0 || $limit > 50) $limit = 20;
	$contacts = array();
	if ($q !== '') {
		$sql = "SELECT sp.rowid, sp.lastname, sp.firstname, sp.email, sp.phone_pro, sp.phone_mobile, sp.fk_soc, s.nom as thirdparty, s.tva_intra, ef.options_docsig_dni as dni FROM ".MAIN_DB_PREFIX."socpeople sp LEFT JOIN ".MAIN_DB_PREFIX."socpeople_extrafields ef ON ef.fk_object = sp.rowid LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = sp.fk_soc WHERE (sp.lastname LIKE '%".$db->escape($q)."%' OR sp.firstname LIKE '%".$db->escape($q)."%' OR sp.email LIKE '%".$db->escape($q)."%' OR sp.phone_pro LIKE '%".$db->escape($q)."%' OR sp.phone_mobile LIKE '%".$db->escape($q)."%' OR ef.options_docsig_dni LIKE '%".$db->escape($q)."%' OR s.tva_intra LIKE '%".$db->escape($q)."%') ORDER BY sp.lastname, sp.firstname LIMIT ".$limit;
		$resql = $db->query($sql);
		if ($resql) { while ($obj = $db->fetch_object($resql)) { $contacts[] = array('id' => (int)$obj->rowid, 'name' => trim(($obj->firstname ? $obj->firstname.' ' : '').$obj->lastname), 'email' => $obj->email, 'phone' => ($obj->phone_pro ?: $obj->phone_mobile), 'dni' => ($obj->dni ?: $obj->tva_intra), 'socid' => (int)$obj->fk_soc, 'socname' => $obj->thirdparty); } }
	}
	$response = array('success' => true, 'results' => $contacts);
}

/*
 * Cancel envelope
 */
elseif ($action == 'cancel_envelope') {
	$envelopeId = GETPOST('envelope_id', 'int');
	$reason = GETPOST('reason', 'restricthtml');

	$envelope = new DocsigEnvelope($db);
	if ($envelope->fetch($envelopeId) > 0) {
		$result = $envelope->cancel($user, $reason);
		
		$response = array(
			'success' => $result > 0,
			'message' => $result > 0 ? 'Envelope cancelled' : 'Failed to cancel envelope',
		);
	} else {
		$response = array(
			'success' => false,
			'error' => 'Envelope not found',
		);
	}
}

/*
 * Create contact inline
 */
elseif ($action == 'create_contact') {
	require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

	$name = GETPOST('name', 'restricthtml');
	$firstname = GETPOST('firstname', 'restricthtml');
	$email = GETPOST('email', 'email');
	$dni = GETPOST('dni', 'alpha');
	$socid = GETPOST('socid', 'int');

	$contact = new Contact($db);
	$contact->name = $name;
	$contact->firstname = $firstname;
	$contact->email = $email;
	$contact->socid = $socid;

	$contactId = $contact->create($user);

	if ($contactId > 0) {
		// Add DNI extrafield
		if ($dni) {
			$contact->array_options['options_docsig_dni'] = $dni;
			$contact->insertExtraFields();
		}

		$response = array(
			'success' => true,
			'contact' => array(
				'id' => $contactId,
				'name' => $contact->getFullName($langs),
				'email' => $email,
				'dni' => $dni,
			),
		);
	} else {
		$response = array(
			'success' => false,
			'error' => $contact->error,
		);
	}
}

// Output JSON
docsig_json_response($response, 200);
