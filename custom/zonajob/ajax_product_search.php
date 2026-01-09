<?php
/* Copyright (C) 2025 ZonaJob Dev
 * AJAX endpoint for product search with autocomplete
 */

// Keep output minimal (AJAX)
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);

session_cache_limiter('public');

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}

if (!$res) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode(array('error' => 'Cannot load Dolibarr', 'products' => array()));
    exit;
}

/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

header('Content-Type: application/json; charset=UTF-8');

// Require authenticated session
if (empty($user) || empty($user->id)) {
    http_response_code(401);
    echo json_encode(array('error' => 'Access denied', 'products' => array()));
    exit;
}

// Permissions: allow if user can read/create orders or read products
$canRead = (!empty($user->rights->commande->lire) || !empty($user->rights->commande->creer) || !empty($user->rights->zonajob->order->read));
if (!$canRead && !empty($user->rights->produit) && !empty($user->rights->produit->lire)) {
    $canRead = true;
}
if (!$canRead && !empty($user->rights->product) && !empty($user->rights->product->lire)) {
    $canRead = true;
}
if (!$canRead) {
    http_response_code(403);
    echo json_encode(array('error' => 'Forbidden', 'products' => array()));
    exit;
}

$langs->loadLangs(array('products'));

// Get search term
$search = trim((string) GETPOST('search', 'alphanohtml'));
$limit = GETPOSTINT('limit', 10);

if ($limit <= 0 || $limit > 50) $limit = 15;

if (empty($search) || strlen($search) < 1) {
    echo json_encode(array('products' => array()));
    exit;
}

// Search products
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$sql = "SELECT p.rowid, p.ref, p.label, p.price, p.tva_tx, p.fk_product_type, p.description";
$sql .= " FROM ".MAIN_DB_PREFIX."product as p";
$sql .= " WHERE p.entity IN (".getEntity('product').")";
$sql .= " AND p.tosell = 1";
$sql .= " AND (p.ref LIKE '%".$db->escape($search)."%'";
$sql .= " OR p.label LIKE '%".$db->escape($search)."%'";
$sql .= " OR p.description LIKE '%".$db->escape($search)."%')";
$sql .= " ORDER BY p.ref ASC";
$sql .= " LIMIT ".$limit;
// echo $sql;
$res = $db->query($sql);

$products = array();

if ($res) {
    while ($obj = $db->fetch_object($res)) {
        $prod_type = ($obj->fk_product_type == 0) ? $langs->trans('Product') : $langs->trans('Service');
        
        $products[] = array(
            'id' => $obj->rowid,
            'ref' => $obj->ref,
            'label' => $obj->label,
            'type' => $prod_type,
            'price' => (float) $obj->price,
            'tva_tx' => (float) $obj->tva_tx,
            'vat_src_code' => $obj->vat_src_code,
            'description' => $obj->description,
            'display' => $obj->ref.' - '.$obj->label.' ('.$prod_type.' - '.$obj->price.'€)'
        );
    }
}

echo json_encode(array('products' => $products));

