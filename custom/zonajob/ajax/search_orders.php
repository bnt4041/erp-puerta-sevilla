<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    zonajob/ajax/search_orders.php
 * \ingroup zonajob
 * \brief   AJAX endpoint for order autocomplete search
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
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
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

// Check permission
if (empty($user->rights->zonajob->order->read) && empty($user->rights->commande->lire)) {
    http_response_code(403);
    echo json_encode(array('error' => 'Access denied'));
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$query = GETPOST('q', 'alpha');
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : 10;

if (empty($query) || strlen($query) < 2) {
    echo json_encode(array('results' => array()));
    exit;
}

$results = array();
$search = '%' . $db->escape($query) . '%';

// Build SQL query to search across multiple fields
$sql = "SELECT DISTINCT c.rowid, c.ref, c.ref_client, c.fk_statut,";
$sql .= " s.rowid as socid, s.nom as socname, s.phone as soc_phone,";
$sql .= " p.ref as projet_ref, p.title as projet_title";
$sql .= " FROM ".MAIN_DB_PREFIX."commande as c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet as p ON c.fk_projet = p.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON sp.fk_soc = s.rowid";
$sql .= " WHERE c.entity IN (".getEntity('commande').")";
$sql .= " AND (";
$sql .= "   c.ref LIKE '".$search."'";
$sql .= "   OR c.ref_client LIKE '".$search."'";
$sql .= "   OR s.nom LIKE '".$search."'";
$sql .= "   OR s.phone LIKE '".$search."'";
$sql .= "   OR sp.phone LIKE '".$search."'";
$sql .= "   OR sp.phone_mobile LIKE '".$search."'";
$sql .= "   OR p.ref LIKE '".$search."'";
$sql .= "   OR p.title LIKE '".$search."'";
$sql .= " )";

// Filter by user's permissions (commercial restriction)
if (!$user->rights->societe->client->voir && empty($socid)) {
    $sql .= " AND EXISTS (SELECT sc.fk_soc FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc WHERE sc.fk_soc = c.fk_soc AND sc.fk_user = ".((int) $user->id).")";
}

// Status filter based on config
$show_all = getDolGlobalString('ZONAJOB_SHOW_ALL_ORDERS', 0);
if (!$show_all) {
    $show_draft = getDolGlobalString('ZONAJOB_SHOW_DRAFT_ORDERS', 1);
    $show_validated = getDolGlobalString('ZONAJOB_SHOW_VALIDATED_ORDERS', 1);
    $statusFilters = array();
    if ($show_draft) {
        $statusFilters[] = Commande::STATUS_DRAFT;
    }
    if ($show_validated) {
        $statusFilters[] = Commande::STATUS_VALIDATED;
        $statusFilters[] = Commande::STATUS_SHIPMENTONPROCESS;
        $statusFilters[] = Commande::STATUS_CLOSED;
    }
    if (!empty($statusFilters)) {
        $sql .= " AND c.fk_statut IN (".implode(',', $statusFilters).")";
    }
}

$sql .= " ORDER BY c.date_commande DESC";
$sql .= " LIMIT ".(int)$limit;

$resql = $db->query($sql);

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        // Build status label
        $order = new Commande($db);
        $order->statut = $obj->fk_statut;
        $statusLabel = $order->getLibStatut(0);
        
        // Build display text
        $display = $obj->ref;
        if (!empty($obj->socname)) {
            $display .= ' - ' . $obj->socname;
        }
        if (!empty($obj->projet_ref)) {
            $display .= ' (' . $obj->projet_ref . ')';
        }
        
        $results[] = array(
            'id' => $obj->rowid,
            'ref' => $obj->ref,
            'ref_client' => $obj->ref_client,
            'socname' => $obj->socname,
            'soc_phone' => $obj->soc_phone,
            'projet_ref' => $obj->projet_ref,
            'projet_title' => $obj->projet_title,
            'status' => $obj->fk_statut,
            'status_label' => $statusLabel,
            'display' => $display,
            'url' => DOL_URL_ROOT.'/custom/zonajob/order_card.php?id='.$obj->rowid
        );
    }
}

echo json_encode(array('results' => $results));
