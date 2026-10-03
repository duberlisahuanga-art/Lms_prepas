<?php
/**
 * DEVIOZ ARCADE — ArcadeController
 * FASE 1: Perfil de jugador (la cuenta del juego = la cuenta del sistema)
 * Gamer ID ÚNICO + avatar + color + rango (28 divisiones) + nivel por XP + ranking
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/conexion.php';

class ArcadeController {

    private const AVATARES = ['🧙','🦊','⚡','🐲','','🧠','','❄️','🌙','','🐺',''];
    private const COLORES  = ['cian','magenta','verde','ambar','rojo','violeta'];
    private const LIGAS = [
        ['nom'=>'Bronce',   'ico'=>'🥉', 'css'=>'rank-bronze'],
        ['nom'=>'Plata',    'ico'=>'🥈', 'css'=>'rank-silver'],
        ['nom'=>'Oro',      'ico'=>'🥇', 'css'=>'rank-gold'],
        ['nom'=>'Platino',  'ico'=>'💎', 'css'=>'rank-plat'],
        ['nom'=>'Diamante', 'ico'=>'💠', 'css'=>'rank-dia'],
        ['nom'=>'Maestro',  'ico'=>'🔮', 'css'=>'rank-master'],
        ['nom'=>'Leyenda',  'ico'=>'👑', 'css'=>'rank-legend'],
    ];

    private $db;
    public function __construct(){ $this->db = Conexion::getConexion(); }
    private function userId(){ return intval($_SESSION['usuario_id'] ?? 0); }

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
        $out = ['ok'=>$ok, 'insert_id'=>$st->insert_id, 'affected'=>$st->affected_rows];
        $st->close();
        return $out;
    }

    /* ---- Progresión: 7 ligas × 4 divisiones = 28 escalones (100 pts c/u) ---- */
    private function getRank($pts){
        $pts = max(0, intval($pts));
        $div_index  = min(intdiv($pts, 100), 27);
        $liga_index = min(intdiv($div_index, 4), 6);
        $romanos = ['IV','III','II','I'];
        $sub  = $div_index % 4;
        $liga = self::LIGAS[$liga_index];
        $en_div = $pts % 100;
        $es_max = ($liga_index === 6 && $sub === 3);
        return [
            'liga'=>$liga['nom'], 'ico'=>$liga['ico'], 'css'=>$liga['css'],
            'division'=>$romanos[$sub], 'nivel_absoluto'=>$div_index+1,
            'pts_en_division'=>$en_div,
            'pts_faltantes'=>$es_max ? 0 : 100-$en_div,
            'es_max'=>$es_max
        ];
    }
    private function getNivel($xp){
        $xp = max(0, intval($xp));
        return ['nivel'=>intdiv($xp,100)+1, 'xp_en_nivel'=>$xp%100, 'xp_faltante'=>100-($xp%100)];
    }

    /* ============ check_id: disponibilidad EN VIVO ============ */
    public function checkId(){
        $gid = trim($_GET['gamer_id'] ?? '');
        if(!preg_match('/^[A-Za-z0-9_]{3,16}$/', $gid)){
            echo json_encode(['ok'=>true,'disponible'=>false,'motivo'=>'3-16 caracteres: letras, números o _']);
            return;
        }
        $rows = $this->fetchAll("SELECT usuario_id FROM arcade_perfil WHERE gamer_id = ?", 's', [$gid]);
        echo json_encode(['ok'=>true,'disponible'=>!count($rows)]);
    }

    /* ============ crear_perfil: primera entrada al Arcade ============ */
    public function crearPerfil(){
        $uid = $this->userId();
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión: inicia sesión en Devioz']); return; }
        $ya = $this->fetchAll("SELECT usuario_id FROM arcade_perfil WHERE usuario_id=?", 'i', [$uid]);
        if(count($ya)){ echo json_encode(['ok'=>false,'msg'=>'Ya tienes un perfil de jugador']); return; }

        $gid    = trim($_POST['gamer_id'] ?? '');
        $avatar = $_POST['avatar'] ?? '🧙';
        $color  = $_POST['color'] ?? 'cian';
        if(!preg_match('/^[A-Za-z0-9_]{3,16}$/', $gid)){
            echo json_encode(['ok'=>false,'msg'=>'El ID debe tener 3-16 caracteres (letras, números o _)']); return;
        }
        if(!in_array($avatar, self::AVATARES, true)) $avatar = '🧙';
        if(!in_array($color, self::COLORES, true))  $color  = 'cian';

        $dup = $this->fetchAll("SELECT usuario_id FROM arcade_perfil WHERE gamer_id = ?", 's', [$gid]);
        if(count($dup)){ echo json_encode(['ok'=>false,'msg'=>'Ese Gamer ID ya existe. Elige otro.']); return; }

        try{
            $this->execute("INSERT INTO arcade_perfil (usuario_id, gamer_id, avatar, color) VALUES (?,?,?,?)",
                           'isss', [$uid, $gid, $avatar, $color]);
            echo json_encode(['ok'=>true,'msg'=>'🎮 ¡Bienvenido al Arcade, '.$gid.'!']);
        }catch(Exception $e){
            echo json_encode(['ok'=>false,'msg'=>'Ese Gamer ID acaba de ser tomado. Elige otro.']);
        }
    }

    /* ============ mi_perfil: tarjeta del jugador en el hub ============ */
    public function miPerfil(){
        $uid = $this->userId();
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión']); return; }
        try{
            $rows = $this->fetchAll("SELECT * FROM arcade_perfil WHERE usuario_id=?", 'i', [$uid]);
            if(!count($rows)){ echo json_encode(['ok'=>true,'existe'=>false,'data'=>null]); return; }
            $p = $rows[0];
            $stats = $this->fetchAll(
                "SELECT COUNT(*) partidas, SUM(resultado='victoria') victorias, SUM(resultado='derrota') derrotas
                 FROM arcade_partidas WHERE usuario_id=?", 'i', [$uid]);
            echo json_encode(['ok'=>true,'existe'=>true,'data'=>[
                'gamer_id'=>$p['gamer_id'], 'avatar'=>$p['avatar'], 'color'=>$p['color'],
                'titulo'=>$p['titulo'], 'xp'=>intval($p['xp']), 'pts_ranked'=>intval($p['pts_ranked']),
                'id_cambiado'=>intval($p['id_cambiado']),
                'rank'=>$this->getRank($p['pts_ranked']),
                'nivel'=>$this->getNivel($p['xp']),
                'stats'=>[
                    'partidas'=>intval($stats[0]['partidas'] ?? 0),
                    'victorias'=>intval($stats[0]['victorias'] ?? 0),
                    'derrotas'=>intval($stats[0]['derrotas'] ?? 0)
                ]
            ]]);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    }

    /* ============ cambiar_id: 1 sola vez gratis ============ */
    public function cambiarId(){
        $uid = $this->userId();
        $gid = trim($_POST['gamer_id'] ?? '');
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión']); return; }
        $rows = $this->fetchAll("SELECT id_cambiado FROM arcade_perfil WHERE usuario_id=?", 'i', [$uid]);
        if(!count($rows)){ echo json_encode(['ok'=>false,'msg'=>'No tienes perfil']); return; }
        if(intval($rows[0]['id_cambiado']) === 1){
            echo json_encode(['ok'=>false,'msg'=>'Ya usaste tu único cambio de ID']); return;
        }
        if(!preg_match('/^[A-Za-z0-9_]{3,16}$/', $gid)){
            echo json_encode(['ok'=>false,'msg'=>'Formato inválido (3-16: letras, números, _)']); return;
        }
        $dup = $this->fetchAll("SELECT usuario_id FROM arcade_perfil WHERE gamer_id=?", 's', [$gid]);
        if(count($dup)){ echo json_encode(['ok'=>false,'msg'=>'Ese ID ya existe']); return; }
        $this->execute("UPDATE arcade_perfil SET gamer_id=?, id_cambiado=1 WHERE usuario_id=?", 'si', [$gid, $uid]);
        echo json_encode(['ok'=>true,'msg'=>'✅ ID actualizado a '.$gid]);
    }

    /* ============ ranking: escalera individual en vivo ============ */
    public function ranking(){
        try{
            $rows = $this->fetchAll(
                "SELECT p.gamer_id, p.avatar, p.color, p.titulo, p.xp, p.pts_ranked, u.nombre
                 FROM arcade_perfil p JOIN usuarios u ON u.id = p.usuario_id
                 ORDER BY p.pts_ranked DESC, p.xp DESC LIMIT 10");
            foreach($rows as &$r){
                $r['rank']  = $this->getRank($r['pts_ranked']);
                $r['nivel'] = $this->getNivel($r['xp']);
            }
            echo json_encode(['ok'=>true,'data'=>$rows]);
        }catch(Exception $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage(),'data'=>[]]); }
    }

    public function handle(){
        switch($_REQUEST['accion'] ?? ''){
            case 'check_id':     $this->checkId();     break;
            case 'crear_perfil': $this->crearPerfil(); break;
            case 'mi_perfil':    $this->miPerfil();    break;
            case 'cambiar_id':   $this->cambiarId();   break;
            case 'ranking':      $this->ranking();     break;
            default: echo json_encode(['ok'=>false,'msg'=>'Acción no válida']);
        }
    }
}
(new ArcadeController())->handle();
?>