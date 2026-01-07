<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * View photo endpoint
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
dol_include_once('/zonajob/class/zonajobphoto.class.php');

// Load language files
$langs->loadLangs(array("zonajob@zonajob"));

// Get parameters
$file = GETPOST('file', 'alpha');
$thumb = GETPOSTINT('thumb');
$w = GETPOSTINT('w') > 0 ? GETPOSTINT('w') : 200;
$h = GETPOSTINT('h') > 0 ? GETPOSTINT('h') : 200;

// Security check
if (empty($user->rights->zonajob->order->read) && empty($user->rights->commande->lire)) {
    accessforbidden();
}

if (empty($file)) {
    http_response_code(400);
    exit('Missing file parameter');
}

// Clean file path (security)
$file = str_replace('..', '', $file);
$file = str_replace('//', '/', $file);

// Build full filepath
$filepath = $conf->zonajob->dir_output.'/photos/'.$file;

// Check file exists
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('File not found: '.$filepath);
}

// Get order ID from path to check permissions
$pathParts = explode('/', $file);
$orderId = isset($pathParts[0]) ? intval($pathParts[0]) : 0;

if ($orderId > 0) {
    $order = new Commande($db);
    $result = $order->fetch($orderId);
    
    // Check if user has access to this order
    if ($result > 0) {
        // Check commercial restriction
        if (!$user->rights->societe->client->voir && empty($socid)) {
            $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."societe_commerciaux";
            $sql .= " WHERE fk_soc = ".$order->socid." AND fk_user = ".$user->id;
            $resql = $db->query($sql);
            if ($resql) {
                $obj = $db->fetch_object($resql);
                if ($obj->nb == 0) {
                    http_response_code(403);
                    exit('Access denied');
                }
            }
        }
    }
}

// Get mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filepath);
finfo_close($finfo);

// If thumbnail requested and it's an image, create thumbnail
if ($thumb && strpos($mime, 'image/') === 0) {
    $cachekey = dol_hash($filepath.'_'.$w.'_'.$h, 3);
    $cachedir = $conf->zonajob->dir_output.'/photos/thumbs';
    if (!is_dir($cachedir)) {
        dol_mkdir($cachedir);
    }
    $cachefile = $cachedir.'/'.$cachekey.'.jpg';
    
    // Check if thumbnail exists and is fresh
    if (!file_exists($cachefile) || filemtime($cachefile) < filemtime($filepath)) {
        // Create thumbnail
        require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
        $result = vignette($filepath, $w, $h, '_thumb', 50, "thumbs");
        
        if ($result) {
            // vignette creates file with _thumb suffix
            $ext = pathinfo($filepath, PATHINFO_EXTENSION);
            $thumbpath = str_replace('.'.$ext, '_thumb.'.$ext, $filepath);
            if (file_exists($thumbpath)) {
                copy($thumbpath, $cachefile);
                $filepath = $cachefile;
            }
        }
    } else {
        $filepath = $cachefile;
    }
}

// Send headers
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($filepath));
header('Content-Disposition: inline; filename="'.basename($photo->filename).'"');
header('Cache-Control: public, max-age=86400');
header('Pragma: public');

// Output file
readfile($filepath);
exit;
