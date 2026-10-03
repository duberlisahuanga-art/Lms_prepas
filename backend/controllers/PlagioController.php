<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/gemini.php';

class PlagioController {
    private $db;
    private $ia;

    public function __construct() {
        $this->db = Conexion::getConexion();
        $this->ia = new GeminiAPI();
    }

    /**
     * VERIFICAR SI USUARIO ESTÁ BLOQUEADO
     */
    public function verificarBloqueo() {
        $usuario_id = $_GET['usuario_id'] ?? null;

        if (!$usuario_id) {
            echo json_encode(['ok' => false, 'msg' => 'Usuario requerido']);
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM usuarios_bloqueados_ia WHERE usuario_id = ? AND (fecha_desbloqueo IS NULL OR fecha_desbloqueo > NOW())");
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $bloqueado = $result->fetch_assoc();

            if ($bloqueado) {
                echo json_encode([
                    'ok' => false,
                    'bloqueado' => true,
                    'msg' => 'Tu acceso a la IA está bloqueado por intento de plagio',
                    'razon' => $bloqueado['razon'],
                    'fecha_bloqueo' => $bloqueado['fecha_bloqueo']
                ]);
            } else {
                echo json_encode(['ok' => true, 'bloqueado' => false]);
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * ANALIZAR RESPUESTA EN BUSCA DE PLAGIO
     */
    public function analizarPlagio() {
        $usuario_id = $_POST['usuario_id'] ?? null;
        $respuesta = $_POST['respuesta'] ?? '';
        $pregunta = $_POST['pregunta'] ?? '';
        $curso_id = $_POST['curso_id'] ?? null;
        $test_id = $_POST['test_id'] ?? null;

        if (!$usuario_id || !$respuesta || !$pregunta) {
            echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
            return;
        }

        // Obtener contenido del PDF
        $contenidoPDF = '';
        if ($curso_id) {
            $stmt = $this->db->prepare("SELECT contenido FROM pdf_contenido WHERE curso_id = ?");
            $stmt->bind_param("i", $curso_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $contenidoPDF = $row['contenido'] ?? '';
        }

        // Analizar con IA
        $analisis = $this->ia->detectarPlagio($respuesta, $contenidoPDF, $pregunta);

        if (!$analisis) {
            echo json_encode(['ok' => false, 'msg' => 'Error al analizar']);
            return;
        }

        $data = json_decode($analisis, true);
        $esPlagio = $data['es_plagio'] ?? false;
        $confianza = $data['nivel_confianza'] ?? 0;

        // Si detecta plagio con alta confianza (>80%), bloquear
        if ($esPlagio && $confianza >= 80) {
            // Registrar detección
            $stmt = $this->db->prepare("INSERT INTO deteccion_plagio (usuario_id, tipo, descripcion, evidencia, test_id) VALUES (?, 'copia_externa', ?, ?, ?)");
            $descripcion = $data['razon'] ?? 'Respuesta sospechosa de plagio';
            $evidencia = $data['evidencia'] ?? '';
            $stmt->bind_param("issi", $usuario_id, $descripcion, $evidencia, $test_id);
            $stmt->execute();

            // Bloquear usuario
            $stmt = $this->db->prepare("INSERT INTO usuarios_bloqueados_ia (usuario_id, razon) VALUES (?, ?) ON DUPLICATE KEY UPDATE razon = VALUES(razon), fecha_bloqueo = NOW()");
            $stmt->bind_param("is", $usuario_id, $descripcion);
            $stmt->execute();

            echo json_encode([
                'ok' => true,
                'es_plagio' => true,
                'bloqueado' => true,
                'msg' => 'Se detectó plagio. Tu acceso a la IA ha sido bloqueado.',
                'confianza' => $confianza
            ]);
        } else {
            echo json_encode([
                'ok' => true,
                'es_plagio' => false,
                'bloqueado' => false,
                'confianza' => $confianza
            ]);
        }
    }

    /**
     * DESBLOQUEAR USUARIO (ADMIN)
     */
    public function desbloquearUsuario() {
        $usuario_id = $_POST['usuario_id'] ?? null;

        if (!$usuario_id) {
            echo json_encode(['ok' => false, 'msg' => 'Usuario requerido']);
            return;
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM usuarios_bloqueados_ia WHERE usuario_id = ?");
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();

            echo json_encode(['ok' => true, 'msg' => 'Usuario desbloqueado']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * LISTAR BLOQUEADOS (Dashboard)
     */
    public function listarBloqueados() {
        try {
            $result = $this->db->query("SELECT * FROM usuarios_bloqueados_ia WHERE fecha_desbloqueo IS NULL OR fecha_desbloqueo > NOW()");
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage(), 'data' => []]);
        }
    }

    public function handleRequest() {
        $accion = $_GET['accion'] ?? $_POST['accion'] ?? 'verificarBloqueo';
        switch ($accion) {
            case 'verificarBloqueo': $this->verificarBloqueo(); break;
            case 'analizarPlagio': $this->analizarPlagio(); break;
            case 'desbloquearUsuario': $this->desbloquearUsuario(); break;
            case 'listarBloqueados': $this->listarBloqueados(); break;
            default: echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);
        }
    }
}

$controller = new PlagioController();
$controller->handleRequest();
?>