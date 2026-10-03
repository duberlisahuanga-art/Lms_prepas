<?php
/**
 * RESPUESTAS JSON - DEVIOZ ACADEMY
 * Funciones para generar respuestas JSON consistentes
 */

/**
 * Enviar respuesta de éxito
 */
function respuestaExito($datos = [], $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'msg' => $datos['msg'] ?? 'Operación exitosa',
        'data' => $datos['data'] ?? null,
        'usuario' => $datos['usuario'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Enviar respuesta de error
 */
function respuestaError($mensaje, $codigo = 400) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'msg' => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Respuesta para datos listados (arrays)
 */
function respuestaLista($datos, $total = null) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'data' => $datos,
        'total' => $total ?? count($datos)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Respuesta para un solo registro
 */
function respuestaUnico($datos) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'data' => $datos
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Respuesta para creación exitosa
 */
function respuestaCreado($datos = [], $id = null) {
    http_response_code(201);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'msg' => 'Registro creado exitosamente',
        'data' => $datos,
        'id' => $id
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Respuesta para actualización exitosa
 */
function respuestaActualizado($mensaje = 'Registro actualizado') {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'msg' => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Respuesta para eliminación exitosa
 */
function respuestaEliminado($mensaje = 'Registro eliminado') {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'msg' => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Respuesta de no autorizado (401)
 */
function respuestaNoAutorizado($mensaje = 'No autorizado') {
    respuestaError($mensaje, 401);
}

/**
 * Respuesta de acceso denegado (403)
 */
function respuestaAccesoDenegado($mensaje = 'Acceso denegado') {
    respuestaError($mensaje, 403);
}

/**
 * Respuesta de no encontrado (404)
 */
function respuestaNoEncontrado($mensaje = 'Recurso no encontrado') {
    respuestaError($mensaje, 404);
}

/**
 * Respuesta de error del servidor (500)
 */
function respuestaErrorServidor($mensaje = 'Error interno del servidor') {
    respuestaError($mensaje, 500);
}

/**
 * Respuesta para validación fallida
 */
function respuestaValidacion($errores) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'msg' => 'Errores de validación',
        'errores' => $errores
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>