<?php
/* Public middleware to serve order documents via secure token */

// Load Dolibarr environment (no auth required)
$res = 0;
if (!$res && file_exists(__DIR__.'/../master.inc.php')) {
    $res = @include __DIR__.'/../master.inc.php';
}
if (!$res && file_exists(__DIR__.'/../main.inc.php')) {
    $res = @include __DIR__.'/../main.inc.php';
}
if (!$res) {
    // Try relative includes if path differs
    if (file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
}
if (!$res) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Cannot load Dolibarr environment';
    exit;
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

$token = GETPOST('token', 'alpha');
if (empty($token)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing token';
    exit;
}

// Look up token record
$sql = "SELECT token, filename, filepath, date_expiration, downloads, active FROM ".MAIN_DB_PREFIX."zonajob_doc_tokens WHERE token='".$db->escape($token)."'";
$resql = $db->query($sql);
if (!$resql) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Database error';
    exit;
}

$obj = $db->fetch_object($resql);
if (!$obj) {
    header('HTTP/1.1 404 Not Found');
    echo 'Invalid token';
    exit;
}

// Check expiration and active
$now = dol_now();
$expTs = 0;
if (!empty($obj->date_expiration)) {
    $expTs = strtotime($obj->date_expiration);
}
if ((int)$obj->active !== 1 || ($expTs > 0 && $now > $expTs)) {
    header('HTTP/1.1 410 Gone');
    echo 'Link expired';
    exit;
}

$filepath = $obj->filepath;
$filename = $obj->filename;
if (!is_file($filepath)) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found';
    exit;
}

// Detect MIME type
$mime = dol_mimetype($filename);
if (empty($mime)) {
    $mime = 'application/octet-stream';
}

// Stream file
$disposition = 'inline'; // Allow browser to view PDFs/images; user can download if desired
if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($mime, 'application/pdf') === false && strpos($mime, 'image/') !== 0) {
    // For non-viewable types, force download
    $disposition = 'attachment';
}

header('Content-Type: '.$mime);
header('Content-Length: '.filesize($filepath));
header('Content-Disposition: '.$disposition.'; filename="'.basename($filename).'"');
header('X-Content-Type-Options: nosniff');

$fp = fopen($filepath, 'rb');
if ($fp) {
    fpassthru($fp);
    fclose($fp);
}

// Update downloads count (best-effort)
$db->query("UPDATE ".MAIN_DB_PREFIX."zonajob_doc_tokens SET downloads=downloads+1 WHERE token='".$db->escape($token)."'");

exit;
