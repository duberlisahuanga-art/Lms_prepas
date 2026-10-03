<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== PRUEBA DEL IAController (como el chat) ===\n\n";

$ch = curl_init('http://localhost/lms_prepa/backend/controllers/IAController.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_POSTFIELDS => http_build_query([
        'accion' => 'chatLibre',
        'mensaje' => 'hola, prueba rapida',
        'curso' => '',
        'web' => '0',
        'contexto_web' => '',
        'historial' => '[]',
        'modulo' => 'cursos'
    ])
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Código HTTP: $code\n\n";
echo "Respuesta CRUDA del servidor:\n";
echo substr($res, 0, 1500);
echo "\n\n";

$json = json_decode($res, true);
if ($json && isset($json['ok'])) {
    echo "✅ JSON válido. ok=" . var_export($json['ok'], true) . "\n";
} else {
    echo "❌ NO es JSON válido → hay un error de PHP rompiendo la respuesta\n";
}
?>