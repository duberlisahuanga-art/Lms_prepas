<?php
/**
 * Elige el cerebro del tutor: 🦙 Llama primero, ♊ Gemini solo de repuesto.
 */
require_once __DIR__ . '/llama_free.php';
require_once __DIR__ . '/gemini_free.php';

function obtenerTutorIA(){
    if (LlamaFreeAPI::disponible()) return new LlamaFreeAPI();  // 🦙 tu Groq
    return new GeminiFreeAPI();                                 // ♊ solo si Llama falla
}

/* Para verificar: abre esta URL con ?test al final */
if (isset($_GET['test'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'motor_activo' => LlamaFreeAPI::disponible() ? 'LLAMA 🦙' : 'GEMINI ♊ (revisa tu key en llama_free.php)',
        'modo_llama'   => LlamaFreeAPI::MODO
    ]);
    exit;
}
?>