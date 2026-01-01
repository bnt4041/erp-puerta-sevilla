<?php
// PuertaSevilla - AJAX endpoint to return bank accounts (RIBs) for the "Propietario firmante" contact of a contract

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

require_once __DIR__ . '/../../../main.inc.php';

header('Content-Type: application/json; charset=UTF-8');

$contractId = (int) GETPOSTINT('contractid');
$resolveRibId = (int) GETPOSTINT('resolve_rib');

// If resolving an old rowid to IBAN
if (!empty($resolveRibId)) {
	$sql = "SELECT iban_prefix FROM " . MAIN_DB_PREFIX . "societe_rib WHERE rowid = " . $resolveRibId . " AND (status IS NULL OR status = 1)";
	$res = $db->query($sql);
	if ($res && ($obj = $db->fetch_object($res))) {
		echo json_encode(array('success' => true, 'iban' => $obj->iban_prefix));
	} else {
		echo json_encode(array('success' => false, 'iban' => ''));
	}
	exit;
}

if (empty($contractId)) {
	echo json_encode(array('success' => false, 'error' => 'missing_contractid'));
	exit;
}

// Basic permission: user must have read access to contracts
if (empty($user->rights->contrat->lire) && empty($user->rights->contrat->read)) {
	echo json_encode(array('success' => false, 'error' => 'forbidden'));
	exit;
}

$contactTypeCodes = array('PSVPROPSIGN', 'SALESREPSIGN');

// Find thirdparty of the "firmante" contact linked to this contract (prefer PSVPROPSIGN, fallback SALESREPSIGN)
$sql = "SELECT sp.fk_soc as socid";
$sql .= " FROM " . MAIN_DB_PREFIX . "element_contact ec";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "c_type_contact tc ON tc.rowid = ec.fk_c_type_contact";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "socpeople sp ON sp.rowid = ec.fk_socpeople";
$sql .= " WHERE ec.element_id = " . ((int) $contractId);
$sql .= " AND tc.element = 'contrat' AND tc.source = 'external'";
$sql .= " AND tc.code IN ('" . $db->escape($contactTypeCodes[0]) . "','" . $db->escape($contactTypeCodes[1]) . "')";
$sql .= " ORDER BY (tc.code='" . $db->escape($contactTypeCodes[0]) . "') DESC, ec.rowid ASC";

$res = $db->query($sql);
if (!$res) {
	echo json_encode(array('success' => false, 'error' => 'db_error', 'detail' => $db->lasterror()));
	exit;
}

$obj = $db->fetch_object($res);
$socId = $obj ? (int) $obj->socid : 0;

if (empty($socId)) {
	echo json_encode(array('success' => false, 'error' => 'no_thirdparty_for_contact', 'items' => array()));
	exit;
}

// Fetch bank accounts for that thirdparty
$sql2 = "SELECT rowid, iban_prefix, bank, default_rib";
$sql2 .= " FROM " . MAIN_DB_PREFIX . "societe_rib";
$sql2 .= " WHERE fk_soc = " . $socId;
$sql2 .= " AND (status IS NULL OR status = 1)";
$sql2 .= " ORDER BY default_rib DESC, rowid ASC";

$res2 = $db->query($sql2);
if (!$res2) {
	echo json_encode(array('success' => false, 'error' => 'db_error', 'detail' => $db->lasterror()));
	exit;
}

$items = array();
while ($rib = $db->fetch_object($res2)) {
	$label = '';
	if (!empty($rib->iban_prefix)) {
		$label = $rib->iban_prefix;
	}
	if (!empty($rib->bank)) {
		$label .= (!empty($label) ? ' - ' : '') . $rib->bank;
	}
	if (empty($label)) {
		$label = (string) $rib->rowid;
	}

	$items[] = array(
		'id' => !empty($rib->iban_prefix) ? (string) $rib->iban_prefix : (string) $rib->rowid,
		'label' => $label,
		'default' => !empty($rib->default_rib) ? 1 : 0,
	);
}

echo json_encode(array('success' => true, 'items' => $items));
