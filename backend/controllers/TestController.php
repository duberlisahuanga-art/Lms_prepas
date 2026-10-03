<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/gemini.php';

class TestController {
    private $db;
    private $ia;

    public function __construct() {
        $this->db = Conexion::getConexion();
        $this->ia = new GeminiAPI();
    }

    public function generarConIA() {
        $curso_id = $_POST['curso_id'] ?? null;
        $categoria_id = $_POST['categoria_id'] ?? null;
        $nombre_curso = $_POST['nombre_curso'] ?? '';
        $nombre_categoria = $_POST['nombre_categoria'] ?? '';
        $cantidad = intval($_POST['cantidad'] ?? 10);
        $dificultad = $_POST['dificultad'] ?? 'medio';

        if (!$curso_id || !$categoria_id) {
            echo json_encode(['ok' => false, 'msg' => 'Curso y categoría son obligatorios']);
            return;
        }

        $stmtPDF = $this->db->prepare("SELECT contenido FROM pdf_contenido WHERE curso_id = ?");
        $stmtPDF->bind_param("i", $curso_id);
        $stmtPDF->execute();
        $resultPDF = $stmtPDF->get_result();
        $pdfRow = $resultPDF->fetch_assoc();

        $stmt = $this->db->prepare("SELECT pregunta FROM preguntas WHERE curso_id = ? AND categoria_id = ?");
        $stmt->bind_param("ii", $curso_id, $categoria_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $preguntasExistentes = [];
        while ($row = $result->fetch_assoc()) {
            $preguntasExistentes[] = $row['pregunta'];
        }

        if ($pdfRow && !empty($pdfRow['contenido'])) {
            $contenidoLimitado = substr($pdfRow['contenido'], 0, 8000);
            $preguntasJSON = $this->ia->generarPreguntasDesdePDF($contenidoLimitado, $nombre_categoria, $cantidad, $dificultad, $preguntasExistentes);
        } else {
            $preguntasJSON = $this->ia->generarPreguntas($nombre_curso, $nombre_categoria, $cantidad, $dificultad, $preguntasExistentes);
        }
        
        if (!$preguntasJSON) {
            echo json_encode(['ok' => false, 'msg' => 'Error al conectar con la IA']);
            return;
        }

        $data = json_decode($preguntasJSON, true);
        $preguntas = $data['preguntas'] ?? [];

        if (count($preguntas) === 0) {
            echo json_encode(['ok' => false, 'msg' => 'La IA no generó preguntas válidas']);
            return;
        }

        $stmt = $this->db->prepare("INSERT IGNORE INTO preguntas (curso_id, categoria_id, tipo, pregunta, opciones, respuesta_correcta, explicacion, dificultad) VALUES (?, ?, 'opcion_multiple', ?, ?, ?, ?, ?)");
        
        $guardadas = 0;
        foreach ($preguntas as $p) {
            $preguntaTexto = $p['pregunta'] ?? '';
            $opciones = json_encode($p['opciones'] ?? []);
            $respuesta = $p['respuesta_correcta'] ?? '';
            $explicacion = $p['explicacion'] ?? '';
            
            if (!empty($preguntaTexto) && !empty($respuesta)) {
                $stmt->bind_param("iisssss", $curso_id, $categoria_id, $preguntaTexto, $opciones, $respuesta, $explicacion, $dificultad);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $guardadas++;
                }
            }
        }

        echo json_encode(['ok' => true, 'msg' => "Se generaron $guardadas preguntas con IA", 'cantidad' => $guardadas]);
    }

    public function tutorDesatrancador() {
        $usuario_id = $_POST['usuario_id'] ?? null;
        $curso_id = $_POST['curso_id'] ?? null;
        $pregunta = $_POST['pregunta'] ?? '';

        if (!$usuario_id || !$curso_id || !$pregunta) {
            echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
            return;
        }

        // VERIFICAR BLOQUEO TEMPORAL POR PLAGIO
        $stmt = $this->db->prepare("SELECT id FROM tests WHERE estudiante_id = ? AND estado = 'en_progreso' AND bloqueado_ia = 1");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            echo json_encode([
                'ok' => false, 
                'bloqueado' => true,
                'msg' => 'No puedes usar el Tutor IA porque se detectó plagio en tu test actual. Termina el test para recuperar el acceso.'
            ]);
            return;
        }

        $stmt = $this->db->prepare("SELECT contenido FROM pdf_contenido WHERE curso_id = ?");
        $stmt->bind_param("i", $curso_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if (!$row || empty($row['contenido'])) {
            echo json_encode(['ok' => false, 'msg' => 'El PDF no está indexado']);
            return;
        }

        $contenidoLimitado = substr($row['contenido'], 0, 8000);
        $respuesta = $this->ia->tutorDesatrancador($contenidoLimitado, $pregunta);

        $stmt = $this->db->prepare("INSERT INTO chat_ia (usuario_id, pregunta, respuesta, contexto_pdf) VALUES (?, ?, ?, ?)");
        $contexto = "Curso ID: $curso_id";
        $stmt->bind_param("isss", $usuario_id, $pregunta, $respuesta, $contexto);
        $stmt->execute();

        echo json_encode(['ok' => true, 'respuesta' => $respuesta]);
    }

    public function verificarBloqueoIA() {
        $usuario_id = $_GET['usuario_id'] ?? null;
        
        if (!$usuario_id) {
            echo json_encode(['ok' => false, 'msg' => 'Usuario requerido']);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT id FROM tests WHERE estudiante_id = ? AND estado = 'en_progreso' AND bloqueado_ia = 1");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $testBloqueado = $stmt->get_result()->fetch_assoc();
        
        if ($testBloqueado) {
            echo json_encode([
                'ok' => false,
                'bloqueado' => true,
                'tipo' => 'temporal',
                'msg' => 'Tienes un test en progreso con restricciones de IA por plagio detectado'
            ]);
            return;
        }
        
        echo json_encode(['ok' => true, 'bloqueado' => false]);
    }

    public function guardarRespuestas() {
        $test_id = $_POST['test_id'] ?? null;
        $respuestas = json_decode($_POST['respuestas'] ?? '[]', true);
        $usuario_id = $_POST['usuario_id'] ?? null;
        
        if (!$test_id || empty($respuestas)) {
            echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
            return;
        }

        $correctas = 0;
        $total = count($respuestas);
        $plagioDetectado = false;
        $respuestasPlagio = [];

        foreach ($respuestas as $resp) {
            $pregunta_id = $resp['pregunta_id'];
            $respuesta_dada = $resp['respuesta'] ?? '';
            
            $stmt = $this->db->prepare("SELECT p.*, c.id as curso_id FROM preguntas p LEFT JOIN cursos c ON p.curso_id = c.id WHERE p.id = ?");
            $stmt->bind_param("i", $pregunta_id);
            $stmt->execute();
            $pregunta = $stmt->get_result()->fetch_assoc();
            
            if ($pregunta && !empty($respuesta_dada) && strlen($respuesta_dada) > 20) {
                $stmtPDF = $this->db->prepare("SELECT contenido FROM pdf_contenido WHERE curso_id = ?");
                $stmtPDF->bind_param("i", $pregunta['curso_id']);
                $stmtPDF->execute();
                $pdfRow = $stmtPDF->get_result()->fetch_assoc();
                $contenidoPDF = $pdfRow['contenido'] ?? '';
                
                if (!empty($contenidoPDF)) {
                    $analisis = $this->ia->detectarPlagio($respuesta_dada, substr($contenidoPDF, 0, 5000), $pregunta['pregunta']);
                    
                    if ($analisis) {
                        $data = json_decode($analisis, true);
                        if (($data['es_plagio'] ?? false) && ($data['nivel_confianza'] ?? 0) >= 80) {
                            $plagioDetectado = true;
                            $respuestasPlagio[] = [
                                'pregunta_id' => $pregunta_id,
                                'confianza' => $data['nivel_confianza']
                            ];
                        }
                    }
                }
            }
            
            $stmtResp = $this->db->prepare("INSERT INTO respuestas (test_id, pregunta_id, respuesta_dada, es_correcta) VALUES (?, ?, ?, ?)");
            $stmtCorrectas = $this->db->prepare("SELECT respuesta_correcta FROM preguntas WHERE id = ?");
            $stmtCorrectas->bind_param("i", $pregunta_id);
            $stmtCorrectas->execute();
            $row = $stmtCorrectas->get_result()->fetch_assoc();
            $es_correcta = ($respuesta_dada === ($row['respuesta_correcta'] ?? '')) ? 1 : 0;
            
            if ($es_correcta) $correctas++;
            
            $stmtResp->bind_param("iisi", $test_id, $pregunta_id, $respuesta_dada, $es_correcta);
            $stmtResp->execute();
        }

        if ($plagioDetectado) {
            $stmt = $this->db->prepare("UPDATE tests SET bloqueado_ia = 1 WHERE id = ?");
            $stmt->bind_param("i", $test_id);
            $stmt->execute();
            
            $stmt = $this->db->prepare("INSERT INTO deteccion_plagio (usuario_id, tipo, descripcion, test_id) VALUES (?, 'plagio_test', ?, ?)");
            $descripcion = "Plagio detectado: " . count($respuestasPlagio) . " respuestas";
            $stmt->bind_param("isi", $usuario_id, $descripcion, $test_id);
            $stmt->execute();
        }

        $puntaje = ($correctas / $total) * 20;
        
        $stmt = $this->db->prepare("UPDATE tests SET puntaje_obtenido = ?, estado = 'completado', bloqueado_ia = 0, fecha_fin = NOW() WHERE id = ?");
        $stmt->bind_param("di", $puntaje, $test_id);
        $stmt->execute();

        echo json_encode([
            'ok' => true, 
            'puntaje' => $puntaje, 
            'correctas' => $correctas, 
            'total' => $total,
            'plagio_detectado' => $plagioDetectado
        ]);
    }

    public function listarTestsIA() {
        try {
            $result = $this->db->query("SELECT * FROM tests ORDER BY fecha_inicio DESC");
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage(), 'data' => []]);
        }
    }

    public function listarPreguntas() {
        try {
            $result = $this->db->query("SELECT * FROM preguntas ORDER BY fecha_creacion DESC");
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage(), 'data' => []]);
        }
    }

    public function listarConsultasIA() {
        try {
            $result = $this->db->query("SELECT * FROM chat_ia ORDER BY fecha DESC");
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage(), 'data' => []]);
        }
    }

    public function actividadReciente() {
        try {
            $data = [];
            
            $result = $this->db->query("SELECT COUNT(*) as total FROM preguntas WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $row = $result->fetch_assoc();
            if ($row['total'] > 0) {
                $data[] = "Se generaron {$row['total']} preguntas en las últimas 24h";
            }
            
            $result = $this->db->query("SELECT COUNT(*) as total FROM chat_ia WHERE fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $row = $result->fetch_assoc();
            if ($row['total'] > 0) {
                $data[] = "{$row['total']} consultas al Tutor IA en las últimas 24h";
            }
            
            $result = $this->db->query("SELECT COUNT(*) as total FROM deteccion_plagio WHERE fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $row = $result->fetch_assoc();
            if ($row['total'] > 0) {
                $data[] = "{$row['total']} intentos de plagio detectados en las últimas 24h";
            }
            
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['ok' => true, 'data' => []]);
        }
    }

    public function handleRequest() {
        $accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';
        switch ($accion) {
            case 'generarConIA': $this->generarConIA(); break;
            case 'tutorDesatrancador': $this->tutorDesatrancador(); break;
            case 'verificarBloqueoIA': $this->verificarBloqueoIA(); break;
            case 'guardarRespuestas': $this->guardarRespuestas(); break;
            case 'listarTestsIA': $this->listarTestsIA(); break;
            case 'listarPreguntas': $this->listarPreguntas(); break;
            case 'listarConsultasIA': $this->listarConsultasIA(); break;
            case 'actividadReciente': $this->actividadReciente(); break;
            default: echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);
        }
    }
}

$controller = new TestController();
$controller->handleRequest();
?>