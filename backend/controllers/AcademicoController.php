<?php
/**
 * CONTROLADOR ACADÉMICO - DEVIOZ ACADEMY v2.0
 * Maneja Categorías, Niveles y Ciclos
 * ✅ Protección contra eliminación con cursos asignados
 * ✅ Validación de duplicados
 * ✅ Campo descripción soportado
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/conexion.php';

class AcademicoController {
    private $db;
    private $tablaMap = [
        'categoria' => 'categorias',
        'nivel'     => 'niveles',
        'ciclo'     => 'ciclos'
    ];

    public function __construct() {
        $this->db = Conexion::getConexion();
    }

    public function listar() {
        $tipo = $_GET['tipo'] ?? 'categoria';
        
        if (!isset($this->tablaMap[$tipo])) {
            echo json_encode(['ok' => false, 'msg' => 'Tipo no válido', 'data' => []]);
            return;
        }
        
        $tabla = $this->tablaMap[$tipo];
        
        try {
            // Ordenar por nombre ASC para que aparezcan alfabéticamente en selects
            $result = $this->db->query("SELECT id, nombre, descripcion FROM $tabla ORDER BY nombre ASC");
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error al listar: ' . $e->getMessage(), 'data' => []]);
        }
    }

    public function crear() {
        $tipo = $_POST['tipo'] ?? 'categoria';
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if (!isset($this->tablaMap[$tipo])) {
            echo json_encode(['ok' => false, 'msg' => 'Tipo no válido']);
            return;
        }
        
        if (empty($nombre)) {
            echo json_encode(['ok' => false, 'msg' => 'El nombre es obligatorio']);
            return;
        }
        
        $tabla = $this->tablaMap[$tipo];
        
        try {
            // ✅ Validar duplicado (case-insensitive)
            $chk = $this->db->prepare("SELECT id FROM $tabla WHERE LOWER(nombre) = LOWER(?)");
            $chk->bind_param("s", $nombre);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                echo json_encode(['ok' => false, 'msg' => "Ya existe '$nombre'"]);
                return;
            }
            
            // ✅ Insertar con descripción
            $stmt = $this->db->prepare("INSERT INTO $tabla (nombre, descripcion) VALUES (?, ?)");
            $stmt->bind_param("ss", $nombre, $descripcion);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'ok' => true, 
                    'msg' => "✅ " . ucfirst($tipo) . " '$nombre' creado", 
                    'id' => $this->db->insert_id
                ]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al guardar']);
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error al crear: ' . $e->getMessage()]);
        }
    }

    public function actualizar() {
        $tipo = $_POST['tipo'] ?? 'categoria';
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if (!isset($this->tablaMap[$tipo])) {
            echo json_encode(['ok' => false, 'msg' => 'Tipo no válido']);
            return;
        }
        
        if (!$id || empty($nombre)) {
            echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
            return;
        }
        
        $tabla = $this->tablaMap[$tipo];
        
        try {
            // ✅ Validar duplicado (excluyendo el propio registro)
            $chk = $this->db->prepare("SELECT id FROM $tabla WHERE LOWER(nombre) = LOWER(?) AND id != ?");
            $chk->bind_param("si", $nombre, $id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                echo json_encode(['ok' => false, 'msg' => "Ya existe otra $tipo con el nombre '$nombre'"]);
                return;
            }
            
            // ✅ Actualizar con descripción
            $stmt = $this->db->prepare("UPDATE $tabla SET nombre = ?, descripcion = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nombre, $descripcion, $id);
            
            if ($stmt->execute()) {
                echo json_encode(['ok' => true, 'msg' => "✅ " . ucfirst($tipo) . " actualizado"]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar']);
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function eliminar() {
        $tipo = $_POST['tipo'] ?? 'categoria';
        $id = intval($_POST['id'] ?? 0);
        
        if (!isset($this->tablaMap[$tipo])) {
            echo json_encode(['ok' => false, 'msg' => 'Tipo no válido']);
            return;
        }
        
        if (!$id) {
            echo json_encode(['ok' => false, 'msg' => 'ID requerido']);
            return;
        }
        
        $tabla = $this->tablaMap[$tipo];
        
        try {
            // ✅ PROTECCIÓN: No eliminar si hay cursos usándola
            $campoFk = $tipo . '_id';
            $chk = $this->db->prepare("SELECT COUNT(*) as total FROM cursos WHERE $campoFk = ?");
            $chk->bind_param("i", $id);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            
            if ($row['total'] > 0) {
                echo json_encode([
                    'ok' => false, 
                    'msg' => "No se puede eliminar: hay {$row['total']} curso(s) usando esta {$tipo}. Reasigna o elimina esos cursos primero."
                ]);
                return;
            }
            
            $stmt = $this->db->prepare("DELETE FROM $tabla WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['ok' => true, 'msg' => "✅ " . ucfirst($tipo) . " eliminado"]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'No se encontró el registro']);
            }
        } catch (Exception $e) {
            // Capturar error de foreign key constraint
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'foreign key') !== false || strpos($errorMsg, 'constraint') !== false) {
                echo json_encode([
                    'ok' => false, 
                    'msg' => "No se puede eliminar: hay registros relacionados"
                ]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al eliminar: ' . $errorMsg]);
            }
        }
    }

    public function handleRequest() {
        $accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';
        
        switch ($accion) {
            case 'listar':
                $this->listar();
                break;
            case 'crear':
                $this->crear();
                break;
            case 'actualizar':
                $this->actualizar();
                break;
            case 'eliminar':
                $this->eliminar();
                break;
            default:
                echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);
        }
    }
}

$controller = new AcademicoController();
$controller->handleRequest();
?>