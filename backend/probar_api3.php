<?php
$apiKey = 'AIzaSyAyV4yft5xsQMgCtm9v4CatAidlQOklpPE';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=' . $apiKey;

$data = ['contents' => [['parts' => [['text' => 'Di solo: OK']]]]];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode<br><pre>$response</pre>";

if ($httpCode === 200) {
    $json = json_decode($response, true);
    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        echo "<h2 style='color:green'>✅ FUNCIONA: " . $json['candidates'][0]['content']['parts'][0]['text'] . "</h2>";
    }
}
?>