<?php
/**
 * FUNCIONES AUXILIARES - DEVIOZ ACADEMY
 * Funciones utilitarias reutilizables en todo el proyecto
 */

/**
 * Sanitizar entrada de texto (evitar XSS)
 */
function sanitizar($texto) {
    if (empty($texto)) return '';
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar formato de email
 */
function esEmailValido($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar longitud mínima de contraseña
 */
function passwordValida($password, $min = 6) {
    return strlen($password) >= $min;
}

/**
 * Generar nombre de archivo seguro (para uploads)
 */
function generarNombreArchivo($nombreOriginal) {
    $ext = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    $nombreLimpio = preg_replace('/[^a-zA-Z0-9_]/', '', pathinfo($nombreOriginal, PATHINFO_FILENAME));
    return $nombreLimpio . '_' . time() . '.' . $ext;
}

/**
 * Validar tipo de archivo por extensión
 */
function esExtensionPermitida($archivo, $extensionesPermitidas) {
    $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    return in_array($ext, $extensionesPermitidas);
}

/**
 * Formatear fecha para mostrar
 */
function formatearFecha($fecha, $formato = 'd/m/Y H:i') {
    if (empty($fecha)) return 'N/A';
    $date = new DateTime($fecha);
    return $date->format($formato);
}

/**
 * Calcular diferencia de tiempo (hace cuánto)
 */
function tiempoTranscurrido($fecha) {
    $now = new DateTime();
    $ago = new DateTime($fecha);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return "hace {$diff->y} año(s)";
    if ($diff->m > 0) return "hace {$diff->m} mes(es)";
    if ($diff->d > 0) return "hace {$diff->d} día(s)";
    if ($diff->h > 0) return "hace {$diff->h} hora(s)";
    if ($diff->i > 0) return "hace {$diff->i} minuto(s)";
    return "hace unos segundos";
}

/**
 * Limitar longitud de texto (con puntos suspensivos)
 */
function limitarTexto($texto, $max = 100) {
    if (strlen($texto) <= $max) return $texto;
    return substr($texto, 0, $max) . '...';
}

/**
 * Generar slug a partir de texto (para URLs amigables)
 */
function generarSlug($texto) {
    $texto = strtolower(trim($texto));
    $texto = preg_replace('/[^a-z0-9\s-]/', '', $texto);
    $texto = preg_replace('/[\s-]+/', '-', $texto);
    return trim($texto, '-');
}

/**
 * Validar que un ID sea numérico y positivo
 */
function idValido($id) {
    return is_numeric($id) && intval($id) > 0;
}

/**
 * Obtener la URL base del proyecto
 */
function getBaseUrl() {
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $ruta = dirname($_SERVER['SCRIPT_NAME']);
    return $protocolo . '://' . $host . $ruta;
}

/**
 * Registrar actividad en log (para debugging)
 */
function registrarLog($mensaje, $tipo = 'info') {
    $fecha = date('Y-m-d H:i:s');
    $log = "[{$fecha}] [{$tipo}] {$mensaje}\n";
    $archivo = __DIR__ . '/../config/actividad.log';
    file_put_contents($archivo, $log, FILE_APPEND);
}

/**
 * Redirigir a una página
 */
function redirigir($url) {
    header("Location: {$url}");
    exit;
}

/**
 * Verificar si la petición es AJAX
 */
function esAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
?>