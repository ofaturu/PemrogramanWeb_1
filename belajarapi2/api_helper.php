<?php
session_start(); // Memulai session untuk flash message

$api_url = "http://172.16.9.36/belajarrelasi/api.php";

// Fungsi cURL yang disempurnakan
function callAPI($method, $url, $data = false) {
    $curl = curl_init();
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Fallback jika API tidak mengembalikan JSON yang valid
    $decoded = json_decode($response, true);
    if (!$decoded) {
        return ["status" => "error", "message" => "HTTP Code $httpcode. API Response tidak valid: $response"];
    }
    return $decoded;
}
?>
