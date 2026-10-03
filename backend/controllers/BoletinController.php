<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/conexion.php';

class BoletinController {
    private $db;

    public function __construct() {
        $this->db = Conexion::getConexion();
    }

    public function listar() {
        try {
            $sql = "SELECT b.*, u.nombre as estudiante_nombre, c.nombre as curso_nombre 
                    FROM boletines b 
                    LEFT JOIN usuarios u ON b.estudiante_id = u.id 
                    LEFT JOIN cursos c ON b.curso_id = c.id 
                    ORDER BY b.fecha_registro DESC";
            $result = $this->db->query($sql);
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage(), 'data' => []]);
        }
    }

    public function crear() {
        $estudiante_id = $_POST['estudiante_id'] ?? null;
        $curso_id = $_POST['curso_id'] ?? null;
        $nota = $_POST['nota'] ?? 0;
        $comentarios = trim($_POST['comentarios'] ?? '');

        if (!$estudiante_id || !$curso_id) {
            echo json_encode(['ok' => false, 'msg' => 'Estudiante y curso son obligatorios']);
            return;
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO boletines (estudiante_id, curso_id, nota, comentarios) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iids", $estudiante_id, $curso_id, $nota, $comentarios);
            $stmt->execute();
            
            echo json_encode(['ok' => true, 'msg' => 'Boletín registrado correctamente', 'id' => $this->db->insert_id]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function eliminar() {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['ok' => false, 'msg' => 'ID requerido']);
            return;
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM boletines WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            echo json_encode(['ok' => true, 'msg' => 'Boletín eliminado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function handleRequest() {
        $accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';
        switch ($accion) {
            case 'listar': $this->listar(); break;
            case 'crear': $this->crear(); break;
            case 'eliminar': $this->eliminar(); break;
            default: echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);
        }
    }
}

$controller = new BoletinController();
$controller->handleRequest();
?>