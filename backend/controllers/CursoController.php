<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/conexion.php';

class CursoController {
    private $db;
    private $uploadDir = __DIR__ . '/../uploads/libros/';

    public function __construct() {
        $this->db = Conexion::getConexion();
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function listar() {
        try {
            $sql = "SELECT c.*, n.nombre as nivel_nombre, ci.nombre as ciclo_nombre, 
                           ca.nombre as categoria_nombre, u.nombre as universidad_nombre 
                    FROM cursos c 
                    LEFT JOIN niveles n ON c.nivel_id = n.id 
                    LEFT JOIN ciclos ci ON c.ciclo_id = ci.id 
                    LEFT JOIN categorias ca ON c.categoria_id = ca.id 
                    LEFT JOIN universidades u ON c.universidad_id = u.id 
                    ORDER BY c.id DESC";
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
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $nivel_id = $_POST['nivel_id'] ?? null;
        $ciclo_id = $_POST['ciclo_id'] ?? null;
        $categoria_id = $_POST['categoria_id'] ?? null;
        $universidad_id = $_POST['universidad_id'] ?? null;
        
        if (empty($nombre)) {
            echo json_encode(['ok' => false, 'msg' => 'El nombre es obligatorio']);
            return;
        }

        $pdf_url = null;
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $pdf_url = $this->subirPDF($_FILES['archivo']);
            if (!$pdf_url) {
                echo json_encode(['ok' => false, 'msg' => 'Error al subir el PDF']);
                return;
            }
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO cursos (nombre, descripcion, pdf_url, nivel_id, ciclo_id, categoria_id, universidad_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiiii", $nombre, $descripcion, $pdf_url, $nivel_id, $ciclo_id, $categoria_id, $universidad_id);
            $stmt->execute();
            $curso_id = $this->db->insert_id;

            if (isset($_POST['materias'])) {
                $materias = json_decode($_POST['materias'], true);
                if (is_array($materias)) {
                    $stmtMat = $this->db->prepare("INSERT INTO materias (curso_id, nombre, descripcion, orden) VALUES (?, ?, ?, ?)");
                    foreach ($materias as $index => $mat) {
                        $nombreMat = $mat['nombre'] ?? '';
                        $descMat = $mat['descripcion'] ?? '';
                        if (!empty($nombreMat)) {
                            $stmtMat->bind_param("issi", $curso_id, $nombreMat, $descMat, $index);
                            $stmtMat->execute();
                        }
                    }
                }
            }

            echo json_encode(['ok' => true, 'msg' => 'Curso creado correctamente', 'id' => $curso_id]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function actualizar() {
        $id = $_POST['id'] ?? null;
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $nivel_id = $_POST['nivel_id'] ?? null;
        $ciclo_id = $_POST['ciclo_id'] ?? null;
        $categoria_id = $_POST['categoria_id'] ?? null;
        $universidad_id = $_POST['universidad_id'] ?? null;

        if (!$id || empty($nombre)) {
            echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
            return;
        }

        $pdf_update = '';
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $stmt = $this->db->prepare("SELECT pdf_url FROM cursos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if ($row['pdf_url'] && file_exists(__DIR__ . '/../' . $row['pdf_url'])) {
                unlink(__DIR__ . '/../' . $row['pdf_url']);
            }

            $pdf_url = $this->subirPDF($_FILES['archivo']);
            if (!$pdf_url) {
                echo json_encode(['ok' => false, 'msg' => 'Error al subir el PDF']);
                return;
            }
            $pdf_update = ", pdf_url = '$pdf_url'";
        }

        try {
            $sql = "UPDATE cursos SET nombre = ?, descripcion = ?, nivel_id = ?, ciclo_id = ?, categoria_id = ?, universidad_id = ? $pdf_update WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ssiiiii", $nombre, $descripcion, $nivel_id, $ciclo_id, $categoria_id, $universidad_id, $id);
            $stmt->execute();

            echo json_encode(['ok' => true, 'msg' => 'Curso actualizado correctamente']);
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
            $stmt = $this->db->prepare("SELECT pdf_url FROM cursos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if ($row['pdf_url'] && file_exists(__DIR__ . '/../' . $row['pdf_url'])) {
                unlink(__DIR__ . '/../' . $row['pdf_url']);
            }

            $stmt = $this->db->prepare("DELETE FROM cursos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            echo json_encode(['ok' => true, 'msg' => 'Curso eliminado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function subirPDF($file) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($extension) !== 'pdf') {
            return null;
        }

        $nombreArchivo = 'curso_' . time() . '_' . uniqid() . '.pdf';
        $rutaDestino = $this->uploadDir . $nombreArchivo;

        if (move_uploaded_file($file['tmp_name'], $rutaDestino)) {
            return 'backend/uploads/libros/' . $nombreArchivo;
        }
        return null;
    }

    public function handleRequest() {
        $accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';
        switch ($accion) {
            case 'listar': $this->listar(); break;
            case 'crear': $this->crear(); break;
            case 'actualizar': $this->actualizar(); break;
            case 'eliminar': $this->eliminar(); break;
            default: echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);
        }
    }
}

$controller = new CursoController();
$controller->handleRequest();
?>