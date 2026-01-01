<?php
$endpoints = [
    '/chat/send/text',
    '/message/text',
    '/send-message',
    '/api/send',
    '/api/message',
    '/wa/send',
    '/msg/send',
    '/v1/message',
    '/app/send-message'
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
