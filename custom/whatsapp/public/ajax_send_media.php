<?php
/**
 * AJAX endpoint to send WhatsApp messages with media attachments
 */

if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

header('Content-Type: application/json');

require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once dol_buildpath('/whatsapp/class/gowaclient.class.php', 0);
require_once dol_buildpath('/whatsapp/lib/whatsapp.lib.php', 0);

// Check if user is logged in
if (!$user->id) {
    echo json_encode(array('error' => 1, 'message' => 'Not logged in'));
    exit;
}

$phone = GETPOST('phone', 'alpha');
$caption = GETPOST('caption', 'restricthtml');
$socid = GETPOST('socid', 'int');
$contactid = GETPOST('contactid', 'int');

if (empty($phone)) {
    echo json_encode(array('error' => 1, 'message' => 'Missing phone number'));
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
    $uploadErrors = array(
        UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
        UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
        UPLOAD_ERR_EXTENSION => 'Extensión de PHP bloqueó la subida'
    );
    $errorCode = isset($_FILES['file']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
    $errorMsg = isset($uploadErrors[$errorCode]) ? $uploadErrors[$errorCode] : 'Error desconocido';
    echo json_encode(array('error' => 1, 'message' => $errorMsg));
    exit;
}

$file = $_FILES['file'];
$filename = dol_sanitizeFileName($file['name']);
$filetype = $file['type'];
$filesize = $file['size'];
$tmppath = $file['tmp_name'];

// Validate file size (max 16MB for WhatsApp)
$maxSize = 16 * 1024 * 1024;
if ($filesize > $maxSize) {
    echo json_encode(array('error' => 1, 'message' => 'El archivo es demasiado grande. Máximo 16MB.'));
    exit;
}

// Determine media type
$mediaType = 'document';
if (strpos($filetype, 'image/') === 0) {
    $mediaType = 'image';
} elseif (strpos($filetype, 'video/') === 0) {
    $mediaType = 'video';
} elseif (strpos($filetype, 'audio/') === 0) {
    $mediaType = 'audio';
}

// Create upload directory
$uploadDir = $conf->whatsapp->dir_output . '/media/' . date('Y/m');
if (!is_dir($uploadDir)) {
    dol_mkdir($uploadDir);
}

// Generate unique filename
$uniqueFilename = date('YmdHis') . '_' . uniqid() . '_' . $filename;
$destPath = $uploadDir . '/' . $uniqueFilename;

// Move uploaded file
if (!dol_move_uploaded_file($tmppath, $destPath, 1, 0, $maxSize, 0, 'whatsapp')) {
    echo json_encode(array('error' => 1, 'message' => 'Error al guardar el archivo'));
    exit;
}

// Build public URL for the file
$fileUrl = DOL_URL_ROOT . '/document.php?modulepart=whatsapp&file=' . urlencode('media/' . date('Y/m') . '/' . $uniqueFilename);

// Send via GoWA API
$gowa = new GoWAClient($db);
$result = $gowa->sendMedia($phone, $destPath, $mediaType, $caption);

if ($result['error'] == 0) {
    // Log in agenda
    $actioncomm = new ActionComm($db);
    $actioncomm->type_code = 'AC_WA_MEDIA';
    $actioncomm->label = "WhatsApp " . ucfirst($mediaType) . " enviado a " . $phone;
    
    // Build note with caption and file info
    $note = '';
    if (!empty($caption)) {
        $note = $caption . "\n\n";
    }
    $note .= "[Archivo: " . $filename . " (" . dol_print_size($filesize) . ")]";
    
    $actioncomm->note_private = $note;
    $actioncomm->datep = time();
    $actioncomm->datef = time();
    $actioncomm->percentage = 100;
    $actioncomm->socid = $socid;
    $actioncomm->contactid = $contactid;
    $actioncomm->userownerid = $user->id;
    $actioncomm->authorid = $user->id;
    
    $actionId = $actioncomm->create($user);
    
    // Store media info - try extrafields first, then ECM
    if ($actionId > 0) {
        // Try to use extrafields if they exist
        $sqlCheck = "SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "actioncomm_extrafields LIKE 'wa_media_type'";
        $resCheck = $db->query($sqlCheck);
        
        if ($resCheck && $db->num_rows($resCheck) > 0) {
            // Use extrafields
            $sqlInsert = "INSERT INTO " . MAIN_DB_PREFIX . "actioncomm_extrafields";
            $sqlInsert .= " (fk_object, wa_media_type, wa_media_url, wa_media_filename, wa_media_size, wa_media_mime)";
            $sqlInsert .= " VALUES (";
            $sqlInsert .= (int)$actionId . ", ";
            $sqlInsert .= "'" . $db->escape($mediaType) . "', ";
            $sqlInsert .= "'" . $db->escape($fileUrl) . "', ";
            $sqlInsert .= "'" . $db->escape($filename) . "', ";
            $sqlInsert .= (int)$filesize . ", ";
            $sqlInsert .= "'" . $db->escape($filetype) . "'";
            $sqlInsert .= ")";
            $sqlInsert .= " ON DUPLICATE KEY UPDATE";
            $sqlInsert .= " wa_media_type = VALUES(wa_media_type),";
            $sqlInsert .= " wa_media_url = VALUES(wa_media_url),";
            $sqlInsert .= " wa_media_filename = VALUES(wa_media_filename),";
            $sqlInsert .= " wa_media_size = VALUES(wa_media_size),";
            $sqlInsert .= " wa_media_mime = VALUES(wa_media_mime)";
            
            $db->query($sqlInsert);
        }
        
        // Also register in ECM for document management
        require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
        
        $ecmfile = new EcmFiles($db);
        $ecmfile->filepath = 'whatsapp/media/' . date('Y/m');
        $ecmfile->filename = $uniqueFilename;
        $ecmfile->label = $filename;
        $ecmfile->fullpath_orig = $destPath;
        $ecmfile->gen_or_uploaded = 'uploaded';
        $ecmfile->description = 'WhatsApp media - ' . $mediaType;
        $ecmfile->keywords = 'whatsapp,' . $mediaType;
        $ecmfile->src_object_type = 'actioncomm';
        $ecmfile->src_object_id = $actionId;
        $ecmfile->create($user);
    }
    
    echo json_encode(array('error' => 0, 'message' => 'Archivo enviado correctamente'));
} else {
    // Delete the uploaded file if sending failed
    @unlink($destPath);
    echo json_encode($result);
}
