<?php

if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

$core_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once '../lib/whatsapp.lib.php';

// Check if user is logged in
if (!$user->id) {
    echo json_encode(array('error' => 1, 'message' => 'Not logged in'));
    exit;
}

$phone = GETPOST('phone', 'alpha');
$msg = GETPOST('msg', 'restricthtml');
$socid = GETPOST('socid', 'int');
$contactid = GETPOST('contactid', 'int');

if (empty($phone) || empty($msg)) {
    echo json_encode(array('error' => 1, 'message' => 'Missing parameters'));
    exit;
}

// Send Message
$result = whatsapp_send($phone, $msg);

if ($result['error'] == 0) {
    // Log in agenda
    require_once $core_path.'/comm/action/class/actioncomm.class.php';
    $actioncomm = new ActionComm($db);
    $actioncomm->type_code = 'AC_WA';
    $actioncomm->label = "WhatsApp enviado a " . $phone;
    $actioncomm->note_private = $msg;
    $actioncomm->datep = time();
    $actioncomm->datef = time();
    $actioncomm->percentage = 100;
    $actioncomm->socid = $socid;
    $actioncomm->contactid = $contactid;
    $actioncomm->userownerid = $user->id;
    $actioncomm->authorid = $user->id;
    $actioncomm->create($user);
}

echo json_encode($result);
