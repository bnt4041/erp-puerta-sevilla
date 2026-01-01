<?php
$endpoints = [
    '/app/message/send',
    '/app/send-message',
    '/app/chat/send',
    '/app/sendMessage',
    '/app/send',
    '/message/send',
    '/send'
];

$data = json_encode(['phone' => '1234567890', 'message' => 'test']);

foreach ($endpoints as $ep) {
    $ch = curl_init('http://localhost:3000' . $ep);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "$ep -> $code\n";
}
