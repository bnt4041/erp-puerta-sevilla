<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * Library of functions for ZonaJob module
 */

/**
 * Get order status label with badge
 *
 * @param Commande $order Order object
 * @param Translate $langs Language object
 * @return string HTML badge
 */
function zonajob_get_status_badge($order, $langs)
{
    $statusClass = '';
    $statusLabel = '';
    
    switch ($order->statut) {
        case Commande::STATUS_DRAFT:
            $statusClass = 'badge-status0';
            $statusLabel = $langs->trans('StatusOrderDraft');
            break;
        case Commande::STATUS_VALIDATED:
            $statusClass = 'badge-status1';
            $statusLabel = $langs->trans('StatusOrderValidated');
            break;
        case Commande::STATUS_SHIPMENTONPROCESS:
            $statusClass = 'badge-status4';
            $statusLabel = $langs->trans('StatusOrderSentShort');
            break;
        case Commande::STATUS_CLOSED:
            $statusClass = 'badge-status6';
            $statusLabel = $langs->trans('StatusOrderDelivered');
            break;
        case Commande::STATUS_CANCELED:
            $statusClass = 'badge-status9';
            $statusLabel = $langs->trans('StatusOrderCanceled');
            break;
        default:
            $statusClass = 'badge-status0';
            $statusLabel = $langs->trans('Unknown');
    }
    
    return '<span class="badge '.$statusClass.'">'.$statusLabel.'</span>';
}

/**
 * Get signature status badge
 *
 * @param int $status Signature status
 * @param Translate $langs Language object
 * @return string HTML badge
 */
function zonajob_get_signature_status_badge($status, $langs)
{
    switch ($status) {
        case 0: // Pending
            return '<span class="badge badge-status0">'.$langs->trans('Pending').'</span>';
        case 1: // Signed
            return '<span class="badge badge-status4">'.$langs->trans('Signed').'</span>';
        case -1: // Cancelled
            return '<span class="badge badge-status9">'.$langs->trans('Cancelled').'</span>';
        default:
            return '<span class="badge badge-status0">'.$langs->trans('Unknown').'</span>';
    }
}

/**
 * Format phone number for WhatsApp
 *
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function zonajob_format_phone_whatsapp($phone)
{
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Add country code if missing (Spain default)
    if (strlen($phone) == 9) {
        $phone = '34'.$phone;
    }
    
    return $phone;
}

/**
 * Check if order can be signed
 *
 * @param Commande $order Order object
 * @param User $user User object
 * @return bool
 */
function zonajob_can_sign_order($order, $user)
{
    // Check permission
    if (empty($user->rights->zonajob->sign)) {
        return false;
    }
    
    // Can only sign validated or in-process orders
    if (!in_array($order->statut, array(Commande::STATUS_VALIDATED, Commande::STATUS_SHIPMENTONPROCESS))) {
        return false;
    }
    
    return true;
}

/**
 * Check if order can add photos
 *
 * @param Commande $order Order object
 * @param User $user User object
 * @return bool
 */
function zonajob_can_add_photos($order, $user)
{
    // Check permission
    if (empty($user->rights->zonajob->photo)) {
        return false;
    }
    
    // Can add photos to non-cancelled orders
    if ($order->statut == Commande::STATUS_CANCELED) {
        return false;
    }
    
    return true;
}

/**
 * Check if order can be sent
 *
 * @param Commande $order Order object
 * @param User $user User object
 * @return bool
 */
function zonajob_can_send_order($order, $user)
{
    // Check permission
    if (empty($user->rights->zonajob->send)) {
        return false;
    }
    
    // Can send validated or later orders
    if ($order->statut < Commande::STATUS_VALIDATED) {
        return false;
    }
    
    return true;
}

/**
 * Get photo count for order
 *
 * @param DoliDB $db Database handler
 * @param int $orderId Order ID
 * @return int Photo count
 */
function zonajob_get_photo_count($db, $orderId)
{
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."zonajob_photo";
    $sql .= " WHERE fk_commande = ".((int) $orderId);
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        return (int) $obj->nb;
    }
    return 0;
}

/**
 * Get signature count for order
 *
 * @param DoliDB $db Database handler
 * @param int $orderId Order ID
 * @return int Signature count
 */
function zonajob_get_signature_count($db, $orderId)
{
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."zonajob_signature";
    $sql .= " WHERE fk_commande = ".((int) $orderId);
    $sql .= " AND status = 1";
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        return (int) $obj->nb;
    }
    return 0;
}

/**
 * Get send count for order
 *
 * @param DoliDB $db Database handler
 * @param int $orderId Order ID
 * @return int Send count
 */
function zonajob_get_send_count($db, $orderId)
{
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."zonajob_send_history";
    $sql .= " WHERE fk_commande = ".((int) $orderId);
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        return (int) $obj->nb;
    }
    return 0;
}

/**
 * Format file size
 *
 * @param int $bytes Size in bytes
 * @return string Formatted size
 */
function zonajob_format_size($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2).' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2).' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2).' KB';
    } else {
        return $bytes.' B';
    }
}

/**
 * Get relative time string
 *
 * @param int $timestamp Unix timestamp
 * @param Translate $langs Language object
 * @return string Relative time
 */
function zonajob_relative_time($timestamp, $langs)
{
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $langs->trans('JustNow');
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return sprintf($langs->trans('MinutesAgo'), $mins);
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return sprintf($langs->trans('HoursAgo'), $hours);
    } elseif ($diff < 172800) {
        return $langs->trans('Yesterday');
    } else {
        $days = floor($diff / 86400);
        return sprintf($langs->trans('DaysAgo'), $days);
    }
}

/**
 * Generate thumbnail from image
 *
 * @param string $source Source file path
 * @param string $dest Destination file path
 * @param int $maxWidth Maximum width
 * @param int $maxHeight Maximum height
 * @return bool Success
 */
function zonajob_create_thumbnail($source, $dest, $maxWidth = 200, $maxHeight = 200)
{
    if (!file_exists($source)) {
        return false;
    }
    
    $imageInfo = getimagesize($source);
    if ($imageInfo === false) {
        return false;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $mime = $imageInfo['mime'];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int) ($width * $ratio);
    $newHeight = (int) ($height * $ratio);
    
    // Create image resource
    switch ($mime) {
        case 'image/jpeg':
            $srcImg = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $srcImg = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $srcImg = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $srcImg = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$srcImg) {
        return false;
    }
    
    // Create thumbnail
    $dstImg = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG/GIF
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
        imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize
    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save thumbnail
    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    
    switch ($mime) {
        case 'image/jpeg':
            $result = imagejpeg($dstImg, $dest, 85);
            break;
        case 'image/png':
            $result = imagepng($dstImg, $dest, 8);
            break;
        case 'image/gif':
            $result = imagegif($dstImg, $dest);
            break;
        case 'image/webp':
            $result = imagewebp($dstImg, $dest, 85);
            break;
        default:
            $result = false;
    }
    
    // Clean up
    imagedestroy($srcImg);
    imagedestroy($dstImg);
    
    return $result;
}
