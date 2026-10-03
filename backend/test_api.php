<?php
$apiKey = 'AIzaSyBO8XLgNlWO622CX1FQeyxwQzrSBLmR1wA';
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey";
$payload = ['contents' => [['parts' => [['text' => 'di hola']]]]];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 15
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "<h3>Respuesta cruda de Google:</h3>";
echo "<strong>HTTP:</strong> $code | <strong>cURL Error:</strong> $curlErr<br>";
echo "<pre>" . htmlspecialchars($resp) . "</pre>";
?>