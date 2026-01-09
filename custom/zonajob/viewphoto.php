<?php
/**
 * \file    zonajob/viewphoto.php
 * \ingroup zonajob
 * \brief   View photo - Serves images from standard Dolibarr documents location
 */

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
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Get parameters
$file = GETPOST('file', 'alpha');
$thumb = GETPOSTINT('thumb');
$w = GETPOSTINT('w');
$h = GETPOSTINT('h');

if (empty($w)) $w = 200;
if (empty($h)) $h = 200;

// Security check
if (empty($user->rights->zonajob->order->read) && empty($user->rights->commande->lire)) {
    accessforbidden();
}

if (empty($file)) {
    http_response_code(400);
    exit('Missing file parameter');
}

// Decode URL encoded path
$file = urldecode($file);

// Security: Validate file path to prevent directory traversal
$file = str_replace('..', '', $file);
$file = str_replace('//', '/', $file);

// The file path should be absolute path like:
// /var/www/html/dolpuerta/documents/commandes/PED001/photo_xxx.jpg
$filepath = $file;

// Additional security: Ensure file is within documents/commandes directory
$documents_base = $conf->commande->dir_output;
if (!is_dir($documents_base)) {
    $documents_base = dirname($documents_base);
}
$documents_base = realpath($documents_base);

// Get real path for comparison
$file_real = @realpath($filepath);

if (empty($file_real) || (strpos($file_real, $documents_base) === false && $documents_base != dirname($file_real))) {
    dol_syslog("ZonaJob: Unauthorized access attempt to photo: " . $file, LOG_ERR);
    http_response_code(403);
    exit('Access denied');
}

// Check file exists
if (!file_exists($filepath) || !is_file($filepath)) {
    dol_syslog("ZonaJob: Photo file not found: " . $filepath, LOG_WARNING);
    http_response_code(404);
    exit('File not found');
}

// Get mime type
$finfo = @finfo_open(FILEINFO_MIME_TYPE);
$mime_type = @finfo_file($finfo, $filepath);
@finfo_close($finfo);

if (empty($mime_type)) {
    $mime_type = 'application/octet-stream';
}

// Validate it's an image
$allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-webp');
if (!in_array($mime_type, $allowed_types)) {
    dol_syslog("ZonaJob: Invalid mime type for photo: " . $mime_type, LOG_ERR);
    http_response_code(400);
    exit('Invalid file type');
}

// Generate thumbnail if requested
$serve_file = $filepath;
if ($thumb) {
    // Create cache directory for thumbs
    $thumb_dir = dirname($filepath) . '/.thumbs';
    if (!is_dir($thumb_dir)) {
        @mkdir($thumb_dir, 0755, true);
    }
    
    // Generate thumb filename
    $base_name = basename($filepath, pathinfo($filepath, PATHINFO_EXTENSION));
    $ext = pathinfo($filepath, PATHINFO_EXTENSION);
    $thumb_name = $base_name . '_' . $w . 'x' . $h . '.' . $ext;
    $thumb_path = $thumb_dir . '/' . $thumb_name;
    
    // Generate thumbnail if not cached
    if (!file_exists($thumb_path)) {
        generateThumbnail($filepath, $thumb_path, $w, $h);
    }
    
    if (file_exists($thumb_path)) {
        $serve_file = $thumb_path;
    }
}

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($serve_file));
header('Cache-Control: public, max-age=86400');
header('Pragma: public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

// Send file
readfile($serve_file);
exit;

/**
 * Generate thumbnail
 *
 * @param string $src Source file path
 * @param string $dest Destination file path
 * @param int $w Width
 * @param int $h Height
 * @return void
 */
function generateThumbnail($src, $dest, $w, $h)
{
    if (extension_loaded('imagick')) {
        try {
            $image = new Imagick($src);
            $image->thumbnailImage($w, $h, true);
            $image->writeImage($dest);
            $image->destroy();
            return;
        } catch (Exception $e) {
            // Fall back to GD
        }
    }
    
    if (extension_loaded('gd')) {
        try {
            generateThumbnailGD($src, $dest, $w, $h);
            return;
        } catch (Exception $e) {
            dol_syslog("Error generating thumbnail: " . $e->getMessage(), LOG_ERR);
        }
    }
}

/**
 * Generate thumbnail using GD
 *
 * @param string $src Source file
 * @param string $dest Destination file
 * @param int $w Width
 * @param int $h Height
 * @return void
 */
function generateThumbnailGD($src, $dest, $w, $h)
{
    $info = @getimagesize($src);
    if (!$info) {
        throw new Exception("Cannot get image size");
    }
    
    $mime = $info['mime'];
    
    // Create source image
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($src);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($src);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($src);
            break;
        default:
            throw new Exception("Unsupported image type: " . $mime);
    }
    
    if (!$image) {
        throw new Exception("Cannot create image from source");
    }
    
    $src_w = imagesx($image);
    $src_h = imagesy($image);
    
    // Calculate dimensions maintaining aspect ratio
    $ratio = min($w / $src_w, $h / $src_h);
    if ($ratio > 1) $ratio = 1; // Don't enlarge
    
    $new_w = (int)($src_w * $ratio);
    $new_h = (int)($src_h * $ratio);
    
    // Create thumbnail
    $thumb = imagecreatetruecolor($new_w, $new_h);
    
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);
    
    // Save thumbnail
    switch ($mime) {
        case 'image/jpeg':
            @imagejpeg($thumb, $dest, 90);
            break;
        case 'image/png':
            @imagepng($thumb, $dest);
            break;
        case 'image/gif':
            @imagegif($thumb, $dest);
            break;
        case 'image/webp':
            @imagewebp($thumb, $dest);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($thumb);
}
?>
