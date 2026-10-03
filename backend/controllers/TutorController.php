<?php
/**
 * TUTOR CONTROLLER - Historial de conversaciones del Tutor IA por estudiante
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

function enviarError($msg, $code = 400) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function enviarExito($data) {
    ob_end_clean();
    echo json_encode(['ok' => true, 'msg' => $data['msg'] ?? 'OK', 'data' => $data['data'] ?? null], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../config/conexion.php';
    $conn = Conexion::getConexion();

    if (!isset($_SESSION['usuario_id'])) enviarError('Debes iniciar sesión.', 401);
    $uid = intval($_SESSION['usuario_id']);

    $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

    switch ($accion) {

        // ----- Lista de conversaciones del estudiante -----
        case 'listar_conversaciones':
            $stmt = $conn->prepare("SELECT id, titulo, updated_at FROM tutor_conversaciones WHERE usuario_id = ? ORDER BY updated_at DESC LIMIT 40");
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            $convs = [];
            while ($row = $res->fetch_assoc()) $convs[] = $row;
            enviarExito(['data' => $convs]);
            break;

        // ----- Mensajes de una conversación (solo si es del usuario) -----
        case 'obtener_mensajes':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');

            $chk = $conn->prepare("SELECT id, titulo FROM tutor_conversaciones WHERE id = ? AND usuario_id = ?");
            $chk->bind_param('ii', $id, $uid);
            $chk->execute();
            $conv = $chk->get_result()->fetch_assoc();
            if (!$conv) enviarError('Conversación no encontrada.');

            $stmt = $conn->prepare("SELECT rol, mensaje, created_at FROM tutor_mensajes WHERE conversacion_id = ? ORDER BY id ASC");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $mensajes = [];
            while ($row = $res->fetch_assoc()) $mensajes[] = $row;

            enviarExito(['data' => ['titulo' => $conv['titulo'], 'mensajes' => $mensajes]]);
            break;

        // ----- Guardar un intercambio (pregunta + respuesta) -----
        case 'guardar_intercambio':
            $convId  = intval($_POST['conversacion_id'] ?? 0);
            $msgUser = trim($_POST['mensaje_usuario'] ?? '');
            $msgIa   = trim($_POST['mensaje_ia'] ?? '');

            if (empty($msgUser) || empty($msgIa)) enviarError('Faltan mensajes por guardar.');

            $titulo = null;
            if ($convId > 0) {
                $chk = $conn->prepare("SELECT id, titulo FROM tutor_conversaciones WHERE id = ? AND usuario_id = ?");
                $chk->bind_param('ii', $convId, $uid);
                $chk->execute();
                $conv = $chk->get_result()->fetch_assoc();
                if (!$conv) enviarError('Conversación no encontrada.');
                $titulo = $conv['titulo'];
                $upd = $conn->prepare("UPDATE tutor_conversaciones SET updated_at = NOW() WHERE id = ?");
                $upd->bind_param('i', $convId);
                $upd->execute();
            } else {
                $titulo = mb_substr($msgUser, 0, 60);
                $ins = $conn->prepare("INSERT INTO tutor_conversaciones (usuario_id, titulo) VALUES (?, ?)");
                $ins->bind_param('is', $uid, $titulo);
                $ins->execute();
                $convId = intval($ins->insert_id);
            }

            $insM = $conn->prepare("INSERT INTO tutor_mensajes (conversacion_id, rol, mensaje) VALUES (?, 'user', ?)");
            $insM->bind_param('is', $convId, $msgUser);
            $insM->execute();

            $insM2 = $conn->prepare("INSERT INTO tutor_mensajes (conversacion_id, rol, mensaje) VALUES (?, 'ia', ?)");
            $insM2->bind_param('is', $convId, $msgIa);
            $insM2->execute();

            enviarExito(['msg' => 'Guardado', 'data' => ['conversacion_id' => $convId, 'titulo' => $titulo]]);
            break;

        // ----- Eliminar una conversación completa -----
        case 'eliminar_conversacion':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');

            $chk = $conn->prepare("SELECT id FROM tutor_conversaciones WHERE id = ? AND usuario_id = ?");
            $chk->bind_param('ii', $id, $uid);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) enviarError('Conversación no encontrada.');

            $delM = $conn->prepare("DELETE FROM tutor_mensajes WHERE conversacion_id = ?");
            $delM->bind_param('i', $id);
            $delM->execute();

            $delC = $conn->prepare("DELETE FROM tutor_conversaciones WHERE id = ?");
            $delC->bind_param('i', $id);
            $delC->execute();

            enviarExito(['msg' => '✅ Conversación eliminada']);
            break;

        default:
            enviarError('Acción no válida.');
            break;
    }

} catch (Exception $e) {
    enviarError('Error interno: ' . $e->getMessage(), 500);
}
?>