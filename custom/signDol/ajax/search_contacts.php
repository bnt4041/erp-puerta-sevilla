<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/ajax/search_contacts.php
 * \ingroup docsig
 * \brief   AJAX endpoint para búsqueda de contactos
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

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

// Security check
if (!isModEnabled('docsig')) {
    http_response_code(403);
    print json_encode(array('error' => 'Module not enabled'));
    exit;
}

if (!$user->hasRight('docsig', 'envelope', 'read')) {
    http_response_code(403);
    print json_encode(array('error' => 'Permission denied'));
    exit;
}

$langs->loadLangs(array('docsig@signDol', 'companies'));

// Get parameters
$query = GETPOST('q', 'alphanohtml');
$socid = GETPOSTINT('socid');
$limit = GETPOSTINT('limit') ?: 20;

header('Content-Type: application/json; charset=UTF-8');

$results = array();

if (strlen($query) < 2) {
    print json_encode(array('results' => array()));
    exit;
}

// Buscar contactos
$sql = "SELECT c.rowid, c.firstname, c.lastname, c.email, c.phone, c.phone_mobile,";
$sql .= " s.rowid as socid, s.nom as socname";
$sql .= " FROM ".MAIN_DB_PREFIX."socpeople as c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
$sql .= " WHERE c.entity IN (".getEntity('contact').")";
$sql .= " AND c.statut = 1"; // Solo contactos activos

// Filtro de búsqueda
$searchTerms = explode(' ', $query);
$searchConditions = array();
foreach ($searchTerms as $term) {
    $term = $db->escape(trim($term));
    if (!empty($term)) {
        $searchConditions[] = "(c.firstname LIKE '%".$term."%' OR c.lastname LIKE '%".$term."%' OR c.email LIKE '%".$term."%' OR s.nom LIKE '%".$term."%')";
    }
}
if (!empty($searchConditions)) {
    $sql .= " AND (".implode(' AND ', $searchConditions).")";
}

// Filtro por tercero (si se proporciona)
if ($socid > 0) {
    $sql .= " AND c.fk_soc = ".(int)$socid;
}

// Solo contactos con email
$sql .= " AND c.email IS NOT NULL AND c.email <> ''";

$sql .= " ORDER BY c.lastname, c.firstname";
$sql .= " LIMIT ".(int)$limit;

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $phone = $obj->phone_mobile ?: $obj->phone;
        
        $results[] = array(
            'id' => $obj->rowid,
            'firstname' => $obj->firstname,
            'lastname' => $obj->lastname,
            'fullname' => trim($obj->firstname.' '.$obj->lastname),
            'email' => $obj->email,
            'phone' => $phone,
            'socid' => $obj->socid,
            'socname' => $obj->socname,
            'label' => trim($obj->firstname.' '.$obj->lastname).($obj->socname ? ' ('.$obj->socname.')' : ''),
        );
    }
}

print json_encode(array('results' => $results));
