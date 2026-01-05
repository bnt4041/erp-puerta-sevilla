<?php
/**
 * Test script for GoWA endpoints
 * Run this from inside the container or where PHP can access localhost:3000
 */

$endpoints = [
    '/app/status' => 'GET',
    '/app/devices' => 'GET',
    '/app/login' => 'GET',
    '/send/message' => 'POST'
];

$phone = '1234567890';
$message = 'Test message from discover_endpoints.php';

echo "Testing GoWA Endpoints (http://localhost:3000)...\n\n";

foreach ($endpoints as $ep => $method) {
    echo "Testing $ep ($method) ... ";
    
    $ch = curl_init('http://localhost:3000' . $ep);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($method === 'POST') {
        $data = json_encode(['phone' => $phone, 'message' => $message]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($code > 0) {
        echo "Status: $code\n";
        if ($code == 200 || $code == 400 || $code == 500) { 
            // 400/500 means endpoint exists but payload/state might be invalid, strictly better than 404
             echo "Response: " . substr($res, 0, 100) . "...\n";
        }
    } else {
        echo "FAILED: $error\n";
    }
    echo "----------------------------------------\n";
}
