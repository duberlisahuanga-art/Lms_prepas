<?php
/**
 * DEVIOZ ARCADE — AmigosController
 * Sistema de amistades online con LÍMITE DE 25 SLOTS:
 *  - Enviar / aceptar / rechazar solicitudes
 *  - Eliminar amigo → LIBERA 1 slot (no reinicia el cupo)
 *  - Si estás 25/25, no puedes enviar ni aceptar hasta liberar espacio
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/conexion.php';

class AmigosController {
    const MAX_AMIGOS = 25;

    private $db;
    public function __construct(){
        $this->db = Conexion::getConexion();
        /* Tabla auto-creada: no necesitas correr SQL */
        $this->db->query("CREATE TABLE IF NOT EXISTS arcade_amistades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_a INT NOT NULL,
            usuario_b INT NOT NULL,
            estado ENUM('pendiente','aceptado') NOT NULL DEFAULT 'pendiente',
            iniciado_por INT NOT NULL,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pareja (usuario_a, usuario_b),
            KEY idx_a (usuario_a, estado),
            KEY idx_b (usuario_b, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
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
        $out = ['ok'=>$ok, 'insert_id'=>$st->insert_id];
        $st->close();
        return $out;
    }
    private function pareja($u1, $u2){ return [$u1 < $u2 ? $u1 : $u2, $u1 < $u2 ? $u2 : $u1]; }

    private function countAmigos($uid){
        $r = $this->fetchAll("SELECT COUNT(*) c FROM arcade_amistades
                              WHERE estado='aceptado' AND (usuario_a=? OR usuario_b=?)", 'ii', [$uid, $uid]);
        return intval($r[0]['c'] ?? 0);
    }
    private function getRank($pts){
        $pts = max(0, intval($pts));
        $div = min(intdiv($pts,100), 27);
        $lig = min(intdiv($div,4), 6);
        $L = [['Bronce','🥉','rank-bronze'],['Plata','🥈','rank-silver'],['Oro','🥇','rank-gold'],
              ['Platino','💎','rank-plat'],['Diamante','💠','rank-dia'],['Maestro','🔮','rank-master'],['Leyenda','👑','rank-legend']];
        return ['liga'=>$L[$lig][0],'ico'=>$L[$lig][1],'css'=>$L[$lig][2],'division'=>['IV','III','II','I'][$div%4]];
    }
    private function perfilDe($uids){
        if(!count($uids)) return [];
        $in = implode(',', array_map('intval', $uids));
        $rows = $this->fetchAll("SELECT u.id, u.nombre, p.gamer_id, p.avatar, p.color, p.xp, p.pts_ranked
                                 FROM usuarios u LEFT JOIN arcade_perfil p ON p.usuario_id = u.id
                                 WHERE u.id IN ($in)");
        $map = [];
        foreach($rows as $r){ $r['rank'] = $this->getRank($r['pts_ranked'] ?? 0); $map[intval($r['id'])] = $r; }
        return $map;
    }

    /* ============ PANEL: amigos + solicitudes + slots ============ */
    public function panel(){
        $uid = $this->userId();
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión']); return; }
        $usados = $this->countAmigos($uid);

        $amigosRows = $this->fetchAll("SELECT usuario_a, usuario_b FROM arcade_amistades
                                       WHERE estado='aceptado' AND (usuario_a=? OR usuario_b=?) ORDER BY id DESC", 'ii', [$uid,$uid]);
        $idsAmigos = []; foreach($amigosRows as $r){ $idsAmigos[] = intval($r['usuario_a']) === $uid ? intval($r['usuario_b']) : intval($r['usuario_a']); }

        $recRows = $this->fetchAll("SELECT iniciado_por FROM arcade_amistades
                                    WHERE estado='pendiente' AND usuario_a=? AND usuario_b<>? AND iniciado_por<>?", 'iii', [$uid,$uid,$uid]);
        /* pendientes donde yo soy el destino: iniciado_por != yo y el par me incluye */
        $recRows = $this->fetchAll("SELECT usuario_a, usuario_b, iniciado_por FROM arcade_amistades
                                    WHERE estado='pendiente' AND iniciado_por <> ? AND (usuario_a=? OR usuario_b=?)", 'iii', [$uid,$uid,$uid]);
        $idsRec = []; foreach($recRows as $r){ $idsRec[] = intval($r['iniciado_por']); }

        $envRows = $this->fetchAll("SELECT usuario_a, usuario_b, iniciado_por FROM arcade_amistades
                                    WHERE estado='pendiente' AND iniciado_por = ? AND (usuario_a=? OR usuario_b=?)", 'iii', [$uid,$uid,$uid]);
        $idsEnv = []; foreach($envRows as $r){ $idsEnv[] = intval($r['usuario_a']) === $uid ? intval($r['usuario_b']) : intval($r['usuario_a']); }

        $perf = $this->perfilDe(array_merge($idsAmigos, $idsRec, $idsEnv));
        $mk = function($ids) use ($perf){ $out=[]; foreach($ids as $i){ if(isset($perf[$i])) $out[]=$perf[$i]; } return $out; };

        echo json_encode(['ok'=>true,'data'=>[
            'slots'=>['usados'=>$usados,'max'=>self::MAX_AMIGOS,'libres'=>self::MAX_AMIGOS-$usados,'lleno'=>$usados>=self::MAX_AMIGOS],
            'amigos'=>$mk($idsAmigos),
            'recibidas'=>$mk($idsRec),
            'enviadas'=>$mk($idsEnv)
        ]]);
    }

    /* ============ BUSCAR JUGADORES ============ */
    public function buscar(){
        $uid = $this->userId();
        $q = trim($_GET['q'] ?? '');
        if(mb_strlen($q) < 2){ echo json_encode(['ok'=>true,'data'=>[]]); return; }
        $like = '%'.$q.'%';
        $rows = $this->fetchAll(
            "SELECT u.id, u.nombre, p.gamer_id, p.avatar, p.color, p.xp, p.pts_ranked
             FROM usuarios u LEFT JOIN arcade_perfil p ON p.usuario_id = u.id
             WHERE u.id <> ? AND (p.gamer_id LIKE ? OR u.nombre LIKE ?) AND p.gamer_id IS NOT NULL
             LIMIT 8", 'iss', [$uid, $like, $like]);
        foreach($rows as &$r){
            $r['rank'] = $this->getRank($r['pts_ranked'] ?? 0);
            $r['amigos'] = $this->countAmigos(intval($r['id']));
            [$a,$b] = $this->pareja($uid, intval($r['id']));
            $rel = $this->fetchAll("SELECT estado, iniciado_por FROM arcade_amistades WHERE usuario_a=? AND usuario_b=?", 'ii', [$a,$b]);
            $r['relacion'] = !count($rel) ? null : ($rel[0]['estado']==='aceptado' ? 'amigos' : (intval($rel[0]['iniciado_por'])===$uid ? 'enviada' : 'recibida'));
        }
        echo json_encode(['ok'=>true,'data'=>$rows]);
    }

    /* ============ ENVIAR SOLICITUD (con regla de 25) ============ */
    public function enviar(){
        $uid = $this->userId();
        $dest = intval($_POST['usuario_id'] ?? 0);
        if(!$uid || !$dest || $dest === $uid){ echo json_encode(['ok'=>false,'msg'=>'Destino inválido']); return; }

        $mios = $this->countAmigos($uid);
        if($mios >= self::MAX_AMIGOS){
            echo json_encode(['ok'=>false,'msg'=>'🔒 Tus 25 slots de amigos están llenos. Elimina un amigo para LIBERAR 1 espacio y poder agregar a este jugador.']);
            return;
        }
        $suyos = $this->countAmigos($dest);
        if($suyos >= self::MAX_AMIGOS){
            echo json_encode(['ok'=>false,'msg'=>'La lista de ese jugador está llena (25/25). No puede recibir amigos ahora.']);
            return;
        }
        [$a,$b] = $this->pareja($uid, $dest);
        $ex = $this->fetchAll("SELECT estado, iniciado_por FROM arcade_amistades WHERE usuario_a=? AND usuario_b=?", 'ii', [$a,$b]);
        if(count($ex)){
            if($ex[0]['estado']==='aceptado'){ echo json_encode(['ok'=>false,'msg'=>'Ya son amigos']); return; }
            echo json_encode(['ok'=>false,'msg'=> intval($ex[0]['iniciado_por'])===$uid ? 'Ya le enviaste una solicitud (está pendiente)' : 'Ese jugador ya te envió una solicitud: revísala en Recibidas']);
            return;
        }
        $this->execute("INSERT INTO arcade_amistades (usuario_a, usuario_b, estado, iniciado_por) VALUES (?,'?','pendiente',?) " === '' ? '' : "INSERT INTO arcade_amistades (usuario_a, usuario_b, estado, iniciado_por) VALUES (?,?, 'pendiente', ?)", 'iii', [$a, $b, $uid]);
        echo json_encode(['ok'=>true,'msg'=>'📨 Solicitud enviada']);
    }

    /* ============ ACEPTAR / RECHAZAR ============ */
    public function responder(){
        $uid = $this->userId();
        $de = intval($_POST['usuario_id'] ?? 0);
        $acc = $_POST['accion'] ?? '';
        [$a,$b] = $this->pareja($uid, $de);
        $row = $this->fetchAll("SELECT * FROM arcade_amistades WHERE usuario_a=? AND usuario_b=? AND estado='pendiente' AND iniciado_por=?", 'iii', [$a,$b,$de]);
        if(!count($row)){ echo json_encode(['ok'=>false,'msg'=>'Solicitud no encontrada']); return; }

        if($acc === 'aceptar'){
            if($this->countAmigos($uid) >= self::MAX_AMIGOS){ echo json_encode(['ok'=>false,'msg'=>'🔒 No puedes aceptar: tus 25 slots están llenos. Libera 1 espacio eliminando un amigo.']); return; }
            if($this->countAmigos($de) >= self::MAX_AMIGOS){ echo json_encode(['ok'=>false,'msg'=>'El otro jugador llegó a su límite de 25 amigos']); return; }
            $this->execute("UPDATE arcade_amistades SET estado='aceptado' WHERE id=?", 'i', [intval($row[0]['id'])]);
            echo json_encode(['ok'=>true,'msg'=>'✅ ¡Ahora son amigos!']);
        } else {
            $this->execute("DELETE FROM arcade_amistades WHERE id=?", 'i', [intval($row[0]['id'])]);
            echo json_encode(['ok'=>true,'msg'=>'Solicitud rechazada']);
        }
    }

    /* ============ ELIMINAR AMIGO (libera 1 slot) ============ */
    public function eliminar(){
        $uid = $this->userId();
        $otro = intval($_POST['usuario_id'] ?? 0);
        [$a,$b] = $this->pareja($uid, $otro);
        $r = $this->execute("DELETE FROM arcade_amistades WHERE usuario_a=? AND usuario_b=? AND estado='aceptado'", 'ii', [$a,$b]);
        $usados = $this->countAmigos($uid);
        echo json_encode(['ok'=>$r['ok'],
            'msg'=>$r['ok'] ? "🗑️ Amistad eliminada. Slot liberado: $usados/".self::MAX_AMIGOS." (puedes agregar 1 nuevo jugador)" : 'No se encontró la amistad']);
    }

    public function handle(){
        switch($_REQUEST['accion'] ?? ''){
            case 'panel':   $this->panel();   break;
            case 'buscar':  $this->buscar();  break;
            case 'enviar':  $this->enviar();  break;
            case 'responder': $this->responder(); break;
            case 'eliminar': $this->eliminar(); break;
            default: echo json_encode(['ok'=>false,'msg'=>'Acción no válida']);
        }
    }
}
(new AmigosController())->handle();
?>