<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * View signature endpoint
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
dol_include_once('/zonajob/class/zonajobsignature.class.php');

// Load language files
$langs->loadLangs(array("zonajob@zonajob"));

// Get parameters
$id = GETPOST('id', 'int');
$format = GETPOST('format', 'alpha'); // 'png' or 'file'

// Security check
if (empty($user->rights->zonajob->read)) {
    accessforbidden();
}

if (empty($id)) {
    http_response_code(400);
    exit('Missing signature ID');
}

// Get signature info
$signature = new ZonaJobSignature($db);
$result = $signature->fetch($id);

if ($result <= 0) {
    http_response_code(404);
    exit('Signature not found');
}

// Check access to order
$order = new Commande($db);
$order->fetch($signature->fk_commande);

if (!$user->hasRight('commande', 'lire') && $order->fk_user_author != $user->id) {
    http_response_code(403);
    exit('Access denied');
}

// Check if signature is signed
if ($signature->status != ZonaJobSignature::STATUS_SIGNED) {
    http_response_code(400);
    exit('Signature not completed');
}

// Output based on format
if ($format == 'file' && !empty($signature->filepath)) {
    // Serve the saved file
    $filepath = $conf->zonajob->dir_output.'/signatures/'.$signature->filepath;
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        exit('Signature file not found');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    
    header('Content-Type: '.$mime);
    header('Content-Length: '.filesize($filepath));
    header('Content-Disposition: inline; filename="signature_'.$signature->id.'.png"');
    header('Cache-Control: public, max-age=86400');
    
    readfile($filepath);
    exit;
} else {
    // Serve from database (base64 data)
    if (empty($signature->signature_data)) {
        http_response_code(404);
        exit('Signature data not found');
    }
    
    // Parse base64 data
    $data = $signature->signature_data;
    if (strpos($data, 'data:image/png;base64,') === 0) {
        $data = substr($data, strlen('data:image/png;base64,'));
    }
    
    $imageData = base64_decode($data);
    
    if ($imageData === false) {
        http_response_code(500);
        exit('Invalid signature data');
    }
    
    header('Content-Type: image/png');
    header('Content-Length: '.strlen($imageData));
    header('Content-Disposition: inline; filename="signature_'.$signature->id.'.png"');
    header('Cache-Control: public, max-age=86400');
    
    echo $imageData;
    exit;
}
