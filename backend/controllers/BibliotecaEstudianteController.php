<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/conexion.php';

class BibliotecaEstudianteController {
    private $db;

    public function __construct(){
        $this->db = Conexion::getConexion();
        $this->ensureTables();
    }

    private function userId(){ return intval($_SESSION['usuario_id'] ?? 0); }

    /* ============================================================
     * HELPERS ANTI "Commands out of sync"
     * Siempre: execute → leer todo → free() → close()
     * Nunca queda un statement/resultset abierto en la conexión.
     * ============================================================ */
    private function fetchAll($sql, $types = '', $params = []){
        $st = $this->db->prepare($sql);
        if(!$st) throw new Exception('Prepare falló: ' . $this->db->error);
        if($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();
        $rows = [];
        while($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();      // ← libera el resultset
        $st->close();      // ← cierra el statement
        return $rows;
    }

    private function execute($sql, $types = '', $params = []){
        $st = $this->db->prepare($sql);
        if(!$st) throw new Exception('Prepare falló: ' . $this->db->error);
        if($types !== '') $st->bind_param($types, ...$params);
        $ok = $st->execute();
        $out = ['ok'=>$ok, 'affected'=>$st->affected_rows, 'insert_id'=>$st->insert_id];
        $st->close();      // ← cierra el statement
        return $out;
    }

    /* Crea las tablas si no existen (DDL no deja resultset abierto) */
    private function ensureTables(){
        try{
            $this->db->query("CREATE TABLE IF NOT EXISTS estudiante_preferencias (
                usuario_id INT NOT NULL PRIMARY KEY,
                universidad_id INT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $this->db->query("CREATE TABLE IF NOT EXISTS mis_libros (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                curso_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_curso (usuario_id, curso_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }catch(Exception $e){ error_log('ensureTables: ' . $e->getMessage()); }
    }

    /* ========== DIAGNÓSTICO ========== */
    public function diagnostico(){
        $uid = $this->userId();
        $out = ['usuario_id_sesion'=>$uid, 'tablas'=>[], 'mis_libros_rows'=>null, 'preferencia'=>null];
        try{
            $res = $this->db->query("SHOW TABLES");
            $tables = [];
            while($r = $res->fetch_array()) $tables[] = $r[0];
            $res->free();
            $out['tablas'] = [
                'mis_libros' => in_array('mis_libros', $tables),
                'estudiante_preferencias' => in_array('estudiante_preferencias', $tables),
                'cursos' => in_array('cursos', $tables),
            ];
            if($out['tablas']['mis_libros'] && $uid){
                $rows = $this->fetchAll("SELECT COUNT(*) c FROM mis_libros WHERE usuario_id=?", 'i', [$uid]);
                $out['mis_libros_rows'] = intval($rows[0]['c'] ?? 0);
            }
            if($out['tablas']['estudiante_preferencias'] && $uid){
                $rows = $this->fetchAll("SELECT universidad_id FROM estudiante_preferencias WHERE usuario_id=?", 'i', [$uid]);
                $out['preferencia'] = $rows ? $rows[0]['universidad_id'] : 'SIN_FILA';
            }
            echo json_encode(['ok'=>true,'data'=>$out]);
        }catch(Exception $e){
            echo json_encode(['ok'=>false,'msg'=>$e->getMessage(),'data'=>$out]);
        }
    }

    /* ========== PREFERENCIA DE UNIVERSIDAD ========== */
    public function preferencia(){
        $uid = $this->userId();
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión (usuario_id=0)']); return; }
        try{
            $rows = $this->fetchAll(
                "SELECT p.universidad_id, u.nombre AS universidad_nombre
                 FROM estudiante_preferencias p
                 LEFT JOIN universidades u ON u.id = p.universidad_id
                 WHERE p.usuario_id = ?", 'i', [$uid]);
            $row = $rows[0] ?? null;
            echo json_encode(['ok'=>true,'data'=>$row
                ? ['configurado'=>true,'universidad_id'=>$row['universidad_id'],'universidad_nombre'=>$row['universidad_nombre']]
                : ['configurado'=>false,'universidad_id'=>null,'universidad_nombre'=>null]]);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    }

    public function guardarPreferencia(){
        $uid = $this->userId();
        $uni = intval($_POST['universidad_id'] ?? 0);
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión']); return; }
        try{
            $this->execute(
                "INSERT INTO estudiante_preferencias (usuario_id, universidad_id) VALUES (?,?)
                 ON DUPLICATE KEY UPDATE universidad_id = VALUES(universidad_id)", 'ii', [$uid,$uni]);
            echo json_encode(['ok'=>true,'msg'=>'✅ Universidad guardada']);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    }

    public function quitarPreferencia(){
        $uid = $this->userId();
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión']); return; }
        try{
            $this->execute("UPDATE estudiante_preferencias SET universidad_id = NULL WHERE usuario_id = ?", 'i', [$uid]);
            echo json_encode(['ok'=>true,'msg'=>'Universidad deselegida']);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    }

    /* ========== LIBROS (catálogo) ========== */
    public function libros(){
        $uni = intval($_GET['universidad_id'] ?? 0);
        try{
            $base = "SELECT c.id, c.nombre, c.descripcion, c.pdf_url, c.universidad_id,
                            u.nombre AS universidad_nombre, c.categoria_id, ca.nombre AS categoria_nombre,
                            c.nivel_id, n.nombre AS nivel_nombre, c.ciclo_id, ci.nombre AS ciclo_nombre
                     FROM cursos c
                     LEFT JOIN universidades u ON u.id = c.universidad_id
                     LEFT JOIN categorias ca ON ca.id = c.categoria_id
                     LEFT JOIN niveles n ON n.id = c.nivel_id
                     LEFT JOIN ciclos ci ON ci.id = c.ciclo_id
                     WHERE c.pdf_url IS NOT NULL AND c.pdf_url <> ''";
            if($uni){
                $rows = $this->fetchAll($base . " AND c.universidad_id = ? ORDER BY ca.nombre, n.nombre, ci.nombre, c.nombre", 'i', [$uni]);
            } else {
                $rows = $this->fetchAll($base . " ORDER BY ca.nombre, n.nombre, ci.nombre, c.nombre");
            }
            echo json_encode(['ok'=>true,'data'=>$rows]);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage(),'data'=>[]]); }
    }

    /* ========== MIS LIBROS GUARDADOS ========== */
    public function misLibros(){
        $uid = $this->userId();
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión (usuario_id=0)','data'=>[]]); return; }
        try{
            $rows = $this->fetchAll(
                "SELECT ml.created_at, c.id, c.nombre, c.descripcion, c.pdf_url,
                        u.nombre AS universidad_nombre, ca.nombre AS categoria_nombre,
                        n.nombre AS nivel_nombre, ci.nombre AS ciclo_nombre
                 FROM mis_libros ml
                 JOIN cursos c ON c.id = ml.curso_id
                 LEFT JOIN universidades u ON u.id = c.universidad_id
                 LEFT JOIN categorias ca ON ca.id = c.categoria_id
                 LEFT JOIN niveles n ON n.id = c.nivel_id
                 LEFT JOIN ciclos ci ON ci.id = c.ciclo_id
                 WHERE ml.usuario_id = ? ORDER BY ml.created_at DESC", 'i', [$uid]);
            echo json_encode(['ok'=>true,'data'=>$rows]);
        }catch(Exception $e){
            echo json_encode(['ok'=>false,'msg'=>'mis_libros: ' . $e->getMessage(),'data'=>[]]);
        }
    }

    /* ========== AGREGAR / QUITAR ========== */
    public function agregar(){
        $uid = $this->userId(); $cid = intval($_POST['curso_id'] ?? 0);
        if(!$uid || !$cid){ echo json_encode(['ok'=>false,'msg'=>'Datos inválidos (sesión=' . $uid . ', curso=' . $cid . ')']); return; }
        try{
            $this->execute("INSERT IGNORE INTO mis_libros (usuario_id, curso_id) VALUES (?,?)", 'ii', [$uid,$cid]);
            // Verificación con statement NUEVO y cerrado (ya no hay sync roto)
            $rows = $this->fetchAll("SELECT COUNT(*) c FROM mis_libros WHERE usuario_id=? AND curso_id=?", 'ii', [$uid,$cid]);
            $n = intval($rows[0]['c'] ?? 0);
            if($n > 0) echo json_encode(['ok'=>true,'msg'=>'✅ Agregado a Mis Cursos']);
            else echo json_encode(['ok'=>false,'msg'=>'El INSERT no quedó guardado (usuario=' . $uid . ', curso=' . $cid . ')']);
        }catch(Exception $e){
            echo json_encode(['ok'=>false,'msg'=>'agregar: ' . $e->getMessage()]);
        }
    }

    public function quitar(){
        $uid = $this->userId(); $cid = intval($_POST['curso_id'] ?? 0);
        try{
            $this->execute("DELETE FROM mis_libros WHERE usuario_id=? AND curso_id=?", 'ii', [$uid,$cid]);
            echo json_encode(['ok'=>true,'msg'=>'Quitado de Mis Cursos']);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    }

    public function handle(){
        switch($_REQUEST['accion'] ?? ''){
            case 'diagnostico': $this->diagnostico(); break;
            case 'preferencia': $this->preferencia(); break;
            case 'guardar_preferencia': $this->guardarPreferencia(); break;
            case 'quitar_preferencia': $this->quitarPreferencia(); break;
            case 'libros': $this->libros(); break;
            case 'mis_libros': $this->misLibros(); break;
            case 'agregar': $this->agregar(); break;
            case 'quitar': $this->quitar(); break;
            default: echo json_encode(['ok'=>false,'msg'=>'Acción no válida']);
        }
    }
}
(new BibliotecaEstudianteController())->handle();
?>