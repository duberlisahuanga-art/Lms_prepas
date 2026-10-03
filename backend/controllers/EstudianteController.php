<?php
/**
 * ESTUDIANTE CONTROLLER - Gestión específica de estudiantes
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

function enviarError($msg) { ob_end_clean(); echo json_encode(['ok' => false, 'msg' => $msg]); exit; }
function enviarExito($data) { ob_end_clean(); echo json_encode(['ok' => true, 'msg' => $data['msg'] ?? 'OK', 'data' => $data['data'] ?? null]); exit; }

try {
    require_once __DIR__ . '/../config/conexion.php';
    $conn = Conexion::getConexion();
    
    // Verificar que sea admin
    if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin') {
        enviarError('Acceso denegado');
    }
    
    $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
    
    switch ($accion) {
        
        // Listar solo estudiantes
        case 'listar':
            $sql = "SELECT id, nombre, email, rol, estado, created_at FROM usuarios WHERE rol = 'estudiante' ORDER BY id DESC";
            $result = $conn->query($sql);
            $estudiantes = [];
            while ($row = $result->fetch_assoc()) {
                $estudiantes[] = $row;
            }
            enviarExito(['data' => $estudiantes]);
            break;

        // Obtener estudiante por ID
        case 'obtener':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');
            
            $sql = "SELECT id, nombre, email, rol, estado, created_at FROM usuarios WHERE id = ? AND rol = 'estudiante'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $estudiante = $stmt->get_result()->fetch_assoc();
            
            if (!$estudiante) enviarError('Estudiante no encontrado');
            enviarExito(['data' => $estudiante]);
            break;

        // Actualizar estudiante
        case 'actualizar':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $estado = $_POST['estado'] ?? 'activo';
            
            if ($id <= 0 || empty($nombre)) enviarError('Datos inválidos');
            
            $sql = "UPDATE usuarios SET nombre = ?, estado = ? WHERE id = ? AND rol = 'estudiante'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssi', $nombre, $estado, $id);
            
            if ($stmt->execute()) {
                enviarExito(['msg' => 'Estudiante actualizado']);
            } else {
                enviarError('Error al actualizar');
            }
            break;

        // Eliminar estudiante
        case 'eliminar':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');
            
            $sql = "DELETE FROM usuarios WHERE id = ? AND rol = 'estudiante'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                enviarExito(['msg' => 'Estudiante eliminado']);
            } else {
                enviarError('Error al eliminar');
            }
            break;

        // Contar estudiantes
        case 'contar':
            $sql = "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'estudiante' AND estado = 'activo'";
            $result = $conn->query($sql);
            $total = $result->fetch_assoc()['total'];
            enviarExito(['data' => ['total' => $total]]);
            break;

        default:
            enviarError('Acción no válida');
            break;
    }
    
} catch (Exception $e) {
    enviarError('Error: ' . $e->getMessage());
}
?>