<?php

require '../../../../main.inc.php';
require_once '../lib/whatsapp.lib.php';

// Check if user is logged in
if (!$user->id) {
    echo json_encode(array('error' => 1, 'message' => 'Not logged in'));
    exit;
}

$phone = GETPOST('phone', 'alpha');
$msg = GETPOST('msg', 'alpha');

if (empty($phone) || empty($msg)) {
    echo json_encode(array('error' => 1, 'message' => 'Missing parameters'));
    exit;
}

// Send Message
$result = whatsapp_send($phone, $msg);

echo json_encode($result);
