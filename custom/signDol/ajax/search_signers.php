<?php
/* Copyright (C) 2026 DocSig Module
 *
 * AJAX endpoint to search contacts and thirdparties for signer selection
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

@ob_end_clean();

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}

header('Content-Type: application/json; charset=utf-8');

if (!$res) {
    print json_encode(['error' => 'Include failed', 'success' => false, 'contacts' => [], 'thirdparties' => []]);
    exit;
}

// Check module enabled
if (!isModEnabled('docsig')) {
    print json_encode(['error' => 'Module disabled', 'success' => false, 'contacts' => [], 'thirdparties' => []]);
    exit;
}

// Check if user is logged in
if (empty($user->id)) {
    print json_encode(['error' => 'Not authenticated', 'success' => false, 'contacts' => [], 'thirdparties' => []]);
    exit;
}

// Security check
if (!$user->hasRight('docsig', 'envelope', 'write')) {
    print json_encode(['error' => 'Access denied', 'success' => false, 'contacts' => [], 'thirdparties' => []]);
    exit;
}

$query = GETPOST('q', 'alpha');

if (strlen($query) < 1) {
    print json_encode(['contacts' => [], 'thirdparties' => [], 'success' => true, 'query' => $query]);
    exit;
}

$response = [
    'contacts' => [],
    'thirdparties' => [],
    'success' => true,
    'query' => $query
];

$searchTerm = '%'.$db->escape($query).'%';

// Search contacts
$sql = "SELECT c.rowid, c.firstname, c.lastname, c.email, c.phone_mobile, c.phone";
$sql .= ", s.nom as socname, s.rowid as socid";
$sql .= " FROM ".MAIN_DB_PREFIX."socpeople as c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
$sql .= " WHERE c.entity IN (".getEntity('socpeople').")";
$sql .= " AND c.statut = 1";
$sql .= " AND (";
$sql .= "   c.lastname LIKE '".$searchTerm."'";
$sql .= "   OR c.firstname LIKE '".$searchTerm."'";
$sql .= "   OR c.email LIKE '".$searchTerm."'";
$sql .= " )";
$sql .= " ORDER BY c.lastname, c.firstname";
$sql .= " LIMIT 20";
// echo $sql;
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $name = trim($obj->firstname.' '.$obj->lastname);
        if (empty($name)) $name = $obj->lastname;
        
        $response['contacts'][] = [
            'id' => (int)$obj->rowid,
            'name' => $name,
            'firstname' => $obj->firstname ?: '',
            'lastname' => $obj->lastname ?: '',
            'email' => $obj->email ?: '',
            'phone' => $obj->phone_mobile ?: ($obj->phone_pro ?: ''),
            'company' => $obj->socname ?: '',
            'socid' => (int)($obj->socid ?: 0)
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
$sql .= "   s.nom LIKE '".$searchTerm."'";
$sql .= "   OR s.email LIKE '".$searchTerm."'";
$sql .= " )";
$sql .= " ORDER BY s.nom";
$sql .= " LIMIT 20";

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $response['thirdparties'][] = [
            'id' => (int)$obj->rowid,
            'name' => $obj->nom,
            'email' => $obj->email ?: '',
            'phone' => $obj->phone ?: '',
            'company' => ''
        ];
    }
    $db->free($resql);
}

print json_encode($response);
exit;
