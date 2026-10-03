<?php
/**
 * MATERIA CONTROLLER - Gestión de materias
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
    
    $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
    $esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
    
    switch ($accion) {
        
        // Listar materias (público)
        case 'listar':
            $sql = "SELECT id, nombre, descripcion, icono, estado FROM materias WHERE estado = 'activo' ORDER BY nombre ASC";
            $result = $conn->query($sql);
            $materias = [];
            while ($row = $result->fetch_assoc()) {
                $materias[] = $row;
            }
            enviarExito(['data' => $materias]);
            break;

        // Obtener materia por ID
        case 'obtener':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');
            
            $sql = "SELECT * FROM materias WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $materia = $stmt->get_result()->fetch_assoc();
            
            if (!$materia) enviarError('Materia no encontrada');
            enviarExito(['data' => $materia]);
            break;

        // Crear materia (solo admin)
        case 'crear':
            if (!$esAdmin) enviarError('Acceso denegado');
            
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $icono = trim($_POST['icono'] ?? 'fas fa-book');
            
            if (empty($nombre)) enviarError('El nombre es obligatorio');
            
            $sql = "INSERT INTO materias (nombre, descripcion, icono, estado) VALUES (?, ?, ?, 'activo')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $nombre, $descripcion, $icono);
            
            if ($stmt->execute()) {
                enviarExito(['msg' => 'Materia creada', 'data' => ['id' => $conn->insert_id]]);
            } else {
                enviarError('Error al crear la materia');
            }
            break;

        // Actualizar materia (solo admin)
        case 'actualizar':
            if (!$esAdmin) enviarError('Acceso denegado');
            
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $icono = trim($_POST['icono'] ?? '');
            
            if ($id <= 0 || empty($nombre)) enviarError('Datos inválidos');
            
            $sql = "UPDATE materias SET nombre = ?, descripcion = ?, icono = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssi', $nombre, $descripcion, $icono, $id);
            
            if ($stmt->execute()) {
                enviarExito(['msg' => 'Materia actualizada']);
            } else {
                enviarError('Error al actualizar');
            }
            break;

        // Eliminar materia (solo admin)
        case 'eliminar':
            if (!$esAdmin) enviarError('Acceso denegado');
            
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');
            
            $sql = "UPDATE materias SET estado = 'inactivo' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                enviarExito(['msg' => 'Materia eliminada']);
            } else {
                enviarError('Error al eliminar');
            }
            break;

        default:
            enviarError('Acción no válida');
            break;
    }
    
} catch (Exception $e) {
    enviarError('Error: ' . $e->getMessage());
}
?>