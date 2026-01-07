<?php
/* Copyright (C) 2026 DocSig Module
 *
 * AJAX endpoint to search contacts and thirdparties for signer selection
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die(json_encode(['error' => 'Include of main fails']));
}

// Check module enabled
if (!isModEnabled('docsig')) {
    http_response_code(403);
    die(json_encode(['error' => 'Module not enabled']));
}

// Security check
if (!$user->hasRight('docsig', 'envelope', 'write')) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied']));
}

header('Content-Type: application/json');

$query = GETPOST('q', 'alpha');

if (strlen($query) < 2) {
    echo json_encode(['contacts' => [], 'thirdparties' => []]);
    exit;
}

$response = [
    'contacts' => [],
    'thirdparties' => []
];

// Search contacts
$sql = "SELECT c.rowid, c.firstname, c.lastname, c.email, c.phone_mobile, c.phone_pro, c.civility";
$sql .= ", s.nom as socname, s.rowid as socid";
$sql .= " FROM ".MAIN_DB_PREFIX."socpeople as c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
$sql .= " WHERE c.entity IN (".getEntity('socpeople').")";
$sql .= " AND c.statut = 1";
$sql .= " AND (";
$sql .= "   c.lastname LIKE '%".$db->escape($query)."%'";
$sql .= "   OR c.firstname LIKE '%".$db->escape($query)."%'";
$sql .= "   OR c.email LIKE '%".$db->escape($query)."%'";
$sql .= "   OR CONCAT(c.firstname, ' ', c.lastname) LIKE '%".$db->escape($query)."%'";
$sql .= " )";
$sql .= " ORDER BY c.lastname, c.firstname";
$sql .= " LIMIT 20";

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $name = trim($obj->firstname.' '.$obj->lastname);
        if (empty($name)) $name = $obj->lastname;
        
        $response['contacts'][] = [
            'id' => (int)$obj->rowid,
            'name' => $name,
            'firstname' => $obj->firstname,
            'lastname' => $obj->lastname,
            'email' => $obj->email,
            'phone' => $obj->phone_mobile ?: $obj->phone_pro,
            'company' => $obj->socname,
            'socid' => (int)$obj->socid,
            'dni' => '' // Could be loaded from extrafields if needed
        ];
    }
    $db->free($resql);
}

// Search thirdparties
$sql = "SELECT s.rowid, s.nom, s.email, s.phone";
$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
$sql .= " WHERE s.entity IN (".getEntity('societe').")";
$sql .= " AND s.status = 1";
$sql .= " AND (";
$sql .= "   s.nom LIKE '%".$db->escape($query)."%'";
$sql .= "   OR s.email LIKE '%".$db->escape($query)."%'";
$sql .= " )";
$sql .= " ORDER BY s.nom";
$sql .= " LIMIT 20";

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $response['thirdparties'][] = [
            'id' => (int)$obj->rowid,
            'name' => $obj->nom,
            'email' => $obj->email,
            'phone' => $obj->phone,
            'company' => ''
        ];
    }
    $db->free($resql);
}

echo json_encode($response);
