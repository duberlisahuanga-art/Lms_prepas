<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/conexion.php';

class UniversidadController {
    private $db;

    public function __construct() {
        $this->db = Conexion::getConexion();
    }

    public function listar() {
        try {
            $result = $this->db->query("SELECT id, nombre, pais, ciudad, tipo, descripcion FROM universidades ORDER BY nombre ASC");
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage(), 'data' => []], JSON_UNESCAPED_UNICODE);
        }
    }

    public function crear() {
        $nombre = trim($_POST['nombre'] ?? '');
        $pais = trim($_POST['pais'] ?? 'Perú');
        $ciudad = trim($_POST['ciudad'] ?? 'Lima');
        $tipo = trim($_POST['tipo'] ?? 'nacional');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if (empty($nombre)) {
            echo json_encode(['ok' => false, 'msg' => 'El nombre es obligatorio'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // ✅ Validar tipo: permitir nacional, particular O instituto
        if (!in_array($tipo, ['nacional', 'particular', 'instituto'])) {
            $tipo = 'nacional';
        }

        try {
            // Verificar duplicado
            $chk = $this->db->prepare("SELECT id FROM universidades WHERE LOWER(nombre) = LOWER(?)");
            $chk->bind_param("s", $nombre);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                echo json_encode(['ok' => false, 'msg' => "La universidad '$nombre' ya existe"], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = $this->db->prepare("INSERT INTO universidades (nombre, pais, ciudad, tipo, descripcion) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nombre, $pais, $ciudad, $tipo, $descripcion);
            $stmt->execute();

            echo json_encode(['ok' => true, 'msg' => 'Universidad creada correctamente', 'id' => $this->db->insert_id], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function eliminar() {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'ID válido requerido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Verificar si tiene cursos asociados antes de eliminar
            $chk = $this->db->prepare("SELECT COUNT(*) as total FROM cursos WHERE universidad_id = ?");
            $chk->bind_param("i", $id);
            $chk->execute();
            $res = $chk->get_result()->fetch_assoc();
            
            if ($res['total'] > 0) {
                echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar: tiene cursos asociados'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = $this->db->prepare("DELETE FROM universidades WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            echo json_encode(['ok' => true, 'msg' => 'Universidad eliminada correctamente'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function handleRequest() {
        $accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';
        switch ($accion) {
            case 'listar': $this->listar(); break;
            case 'crear': $this->crear(); break;
            case 'eliminar': $this->eliminar(); break;
            default: echo json_encode(['ok' => false, 'msg' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
        }
    }
}

$controller = new UniversidadController();
$controller->handleRequest();
?>