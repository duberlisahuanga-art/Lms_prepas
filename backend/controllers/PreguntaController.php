<?php
/**
 * DEVIOZ ARCADE — PreguntaController
 * 1) GENERADOR BOT: crea preguntas por CURSO elegido (con caché para no gastar API)
 * 2) CRUD admin del banco
 * 3) jugables/validar: las respuestas NUNCA salen del servidor
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/conexion.php';

class PreguntaController {
    private $db;
    public function __construct(){ $this->db = Conexion::getConexion(); }

    private function fetchAll($sql, $types='', $params=[]){
        $st = $this->db->prepare($sql);
        if(!$st) throw new Exception('Prepare: ' . $this->db->error);
        if($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();
        $rows = []; while($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free(); $st->close();
        return $rows;
    }
    private function execute($sql, $types='', $params=[]){
        $st = $this->db->prepare($sql);
        if(!$st) throw new Exception('Prepare: ' . $this->db->error);
        if($types !== '') $st->bind_param($types, ...$params);
        $ok = $st->execute();
        $out = ['ok'=>$ok, 'insert_id'=>$st->insert_id];
        $st->close();
        return $out;
    }

    /* ============ 🤖 GENERADOR POR CURSO (caché anti-cuota) ============ */
    public function generarCurso(){
        $cursoId  = intval($_POST['curso_id'] ?? 0);
        $dif      = in_array($_POST['dificultad'] ?? 'medio', ['facil','medio','dificil'], true) ? $_POST['dificultad'] : 'medio';
        $objetivo = min(max(intval($_POST['cantidad'] ?? 8), 5), 12);
        if(!$cursoId){ echo json_encode(['ok'=>false,'msg'=>'Curso no válido']); return; }

        $c = $this->fetchAll(
            "SELECT c.id, c.nombre, c.descripcion, c.categoria_id,
                    cat.nombre AS categoria_nombre, n.nombre AS nivel_nombre,
                    ci.nombre AS ciclo_nombre, u.nombre AS universidad_nombre
             FROM cursos c
             LEFT JOIN categorias cat ON cat.id = c.categoria_id
             LEFT JOIN niveles n ON n.id = c.nivel_id
             LEFT JOIN ciclos ci ON ci.id = c.ciclo_id
             LEFT JOIN universidades u ON u.id = c.universidad_id
             WHERE c.id = ?", 'i', [$cursoId]);
        if(!count($c)){ echo json_encode(['ok'=>false,'msg'=>'El curso no existe']); return; }
        $curso = $c[0];

        // 1) ¿El banco ya cubre el objetivo? → no gastar IA
        $total = intval($this->fetchAll("SELECT COUNT(*) c FROM preguntas WHERE curso_id=? AND dificultad=?", 'is', [$cursoId, $dif])[0]['c']);
        if($total >= $objetivo){
            echo json_encode(['ok'=>true,'generadas'=>0,'banco_total'=>$total,
                              'msg'=>'♻️ Banco suficiente: se usan preguntas existentes de '.$curso['nombre']]);
            return;
        }
        $faltan = min($objetivo - $total, 10);

        // 2) Prompt de profesor al bot
        require_once __DIR__ . '/../config/gemini_free.php';
        $prompt = "Eres un profesor preuniversitario peruano. Genera EXACTAMENTE {$faltan} preguntas de opción múltiple del curso \"{$curso['nombre']}\""
            . ($curso['categoria_nombre'] ? " (categoría: {$curso['categoria_nombre']})" : '')
            . ($curso['nivel_nombre'] ? ", nivel {$curso['nivel_nombre']}" : '')
            . ($curso['ciclo_nombre'] ? ", ciclo {$curso['ciclo_nombre']}" : '') . ".\n"
            . "Descripción del curso: " . ($curso['descripcion'] ?: 'temario estándar del curso') . "\n"
            . "Dificultad: {$dif}.\n"
            . "Reglas: 4 opciones (opcion_a..opcion_d) con UNA sola correcta indicada en \"correcta\" (A/B/C/D); "
            . "\"explicacion\" de 1-2 líneas; contenido REAL del curso; evita preguntas duplicadas entre sí.\n"
            . "Responde SOLO un array JSON válido, sin markdown ni texto extra:\n"
            . '[{"pregunta":"","opcion_a":"","opcion_b":"","opcion_c":"","opcion_d":"","correcta":"A","explicacion":""}]';

        $ia = new GeminiFreeAPI();
        $raw = $ia->chatLibreIA($prompt, $curso['nombre'], 'arcade');
        if(!$raw){
            echo json_encode(['ok'=>false,'msg'=>'🤖 Bot en pausa por cuota de API. Intenta en 1-2 min o juega con el banco existente.']);
            return;
        }

        // 3) Parseo robusto del JSON
        $json = trim(str_replace(['```json','```'], '', $raw));
        if(preg_match('/\[[\s\S]*\]/', $json, $m)) $json = $m[0];
        $arr = json_decode($json, true);
        if(!is_array($arr) || !count($arr)){ echo json_encode(['ok'=>false,'msg'=>'El bot no devolvió un JSON válido']); return; }

        $insertadas = 0;
        foreach($arr as $p){
            if(empty($p['pregunta']) || empty($p['opcion_a']) || empty($p['opcion_b'])) continue;
            $correcta = strtoupper(trim($p['correcta'] ?? 'A'));
            if(!in_array($correcta, ['A','B','C','D'], true)) $correcta = 'A';
            $this->execute(
                "INSERT INTO preguntas (curso_id, categoria_id, dificultad, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, correcta, explicacion)
                 VALUES (?,?,?,?,?,?,?,?,?,?)", 'iissssssss',
                [$cursoId, $curso['categoria_id'] ?: null, $dif, trim($p['pregunta']),
                 trim($p['opcion_a']), trim($p['opcion_b'] ?? ''), trim($p['opcion_c'] ?? ''), trim($p['opcion_d'] ?? ''),
                 $correcta, trim($p['explicacion'] ?? '')]);
            $insertadas++;
        }
        $nuevoTotal = intval($this->fetchAll("SELECT COUNT(*) c FROM preguntas WHERE curso_id=? AND dificultad=?", 'is', [$cursoId, $dif])[0]['c']);
        echo json_encode(['ok'=>true,'generadas'=>$insertadas,'banco_total'=>$nuevoTotal,
                          'msg'=>"🤖 El bot generó {$insertadas} preguntas de \"".$curso['nombre']."\""]);
    }

    /* ============ 🎮 JUGABLES: sin respuestas (clave queda en sesión) ============ */
    public function jugables(){
        $curso = intval($_GET['curso_id'] ?? 0);
        $cat   = intval($_GET['categoria_id'] ?? 0);
        $dif   = $_GET['dificultad'] ?? '';
        $lim   = min(intval($_GET['limite'] ?? 10), 20);
        try{
            $sql = "SELECT id, dificultad, pregunta, opcion_a, opcion_b, opcion_c, opcion_d FROM preguntas WHERE 1=1";
            $types = ''; $params = [];
            if($curso){ $sql .= " AND curso_id = ?";     $types .= 'i'; $params[] = $curso; }
            if($cat){   $sql .= " AND categoria_id = ?"; $types .= 'i'; $params[] = $cat; }
            if($dif){   $sql .= " AND dificultad = ?";   $types .= 's'; $params[] = $dif; }
            $sql .= " ORDER BY RAND() LIMIT $lim";
            $rows = $this->fetchAll($sql, $types, $params);

            $map = [];
            if(count($rows)){
                $in = implode(',', array_map('intval', array_column($rows, 'id')));
                foreach($this->fetchAll("SELECT * FROM preguntas WHERE id IN ($in)") as $k){ $map[intval($k['id'])] = $k; }
            }
            $_SESSION['arena_key'] = $map;   // 🔐 la clave vive solo en el servidor
            echo json_encode(['ok'=>true,'data'=>$rows]);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage(),'data'=>[]]); }
    }

    /* ============ 🔐 VALIDAR (el navegador pregunta después de contestar) ============ */
    public function validar(){
        $id = intval($_POST['id'] ?? 0);
        $letra = strtoupper(trim($_POST['letra'] ?? ''));
        $key = $_SESSION['arena_key'][$id] ?? null;
        if(!$key){ echo json_encode(['ok'=>false,'msg'=>'Sesión de juego expirada: vuelve a iniciar la partida']); return; }
        echo json_encode(['ok'=>true,'es_correcta'=>($letra === $key['correcta']),'correcta'=>$key['correcta'],'explicacion'=>$key['explicacion'] ?? '']);
    }

    /* ============ 🛠️ CRUD ADMIN ============ */
    public function listar(){
        try{
            $rows = $this->fetchAll(
                "SELECT p.*, cat.nombre AS categoria_nombre, c.nombre AS curso_nombre
                 FROM preguntas p
                 LEFT JOIN categorias cat ON cat.id = p.categoria_id
                 LEFT JOIN cursos c ON c.id = p.curso_id
                 ORDER BY p.id DESC");
            echo json_encode(['ok'=>true,'data'=>$rows]);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage(),'data'=>[]]); }
    }
    public function crear(){
        try{
            $r = $this->execute(
                "INSERT INTO preguntas (curso_id, categoria_id, dificultad, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, correcta, explicacion)
                 VALUES (?,?,?,?,?,?,?,?,?,?)", 'iissssssss',
                [intval($_POST['curso_id'] ?? 0) ?: null, intval($_POST['categoria_id'] ?? 0) ?: null,
                 $_POST['dificultad'] ?? 'facil', trim($_POST['pregunta'] ?? ''),
                 trim($_POST['opcion_a'] ?? ''), trim($_POST['opcion_b'] ?? ''),
                 trim($_POST['opcion_c'] ?? ''), trim($_POST['opcion_d'] ?? ''),
                 strtoupper($_POST['correcta'] ?? 'A'), trim($_POST['explicacion'] ?? '')]);
            echo json_encode(['ok'=>$r['ok'],'msg'=>$r['ok']?'✅ Pregunta creada':'Error al crear']);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    }
    public function actualizar(){
        try{
            $r = $this->execute(
                "UPDATE preguntas SET curso_id=?, categoria_id=?, dificultad=?, pregunta=?, opcion_a=?, opcion_b=?, opcion_c=?, opcion_d=?, correcta=?, explicacion=? WHERE id=?",
                'iissssssssi',
                [intval($_POST['curso_id'] ?? 0) ?: null, intval($_POST['categoria_id'] ?? 0) ?: null,
                 $_POST['dificultad'] ?? 'facil', trim($_POST['pregunta'] ?? ''),
                 trim($_POST['opcion_a'] ?? ''), trim($_POST['opcion_b'] ?? ''),
                 trim($_POST['opcion_c'] ?? ''), trim($_POST['opcion_d'] ?? ''),
                 strtoupper($_POST['correcta'] ?? 'A'), trim($_POST['explicacion'] ?? ''),
                 intval($_POST['id'] ?? 0)]);
            echo json_encode(['ok'=>$r['ok'],'msg'=>$r['ok']?'✅ Actualizada':'Error']);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    }
    public function eliminar(){
        try{
            $r = $this->execute("DELETE FROM preguntas WHERE id=?", 'i', [intval($_POST['id'] ?? 0)]);
            echo json_encode(['ok'=>$r['ok'],'msg'=>$r['ok']?'✅ Eliminada':'No encontrada']);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    }

    public function handle(){
        switch($_REQUEST['accion'] ?? 'listar'){
            case 'generar_curso': $this->generarCurso(); break;
            case 'jugables':      $this->jugables();     break;
            case 'validar':       $this->validar();      break;
            case 'listar':        $this->listar();       break;
            case 'crear':         $this->crear();        break;
            case 'actualizar':    $this->actualizar();   break;
            case 'eliminar':      $this->eliminar();     break;
            default: echo json_encode(['ok'=>false,'msg'=>'Acción no válida']);
        }
    }
}
(new PreguntaController())->handle();
?>