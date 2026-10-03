<?php
/**
 * DEVIOZ ARCADE — PartidaController
 * Motor de juego SERVER-SIDE del modo 🐉 Duelo del Dragón.
 * - HP, daño, críticos, escudos y FURIA se calculan AQUÍ (el cliente solo pinta).
 * - Las preguntas viajan SIN respuesta; la clave vive en la sesión.
 * - Al terminar: guarda arcade_partidas y suma XP al arcade_perfil.
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/conexion.php';

class PartidaController {
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
        $out = ['ok'=>$ok, 'insert_id'=>$st->insert_id];
        $st->close();
        return $out;
    }

    private function round(){ return $_SESSION['arcade_round'] ?? null; }
    private function hud($r){
        return ['hpJ'=>max($r['hpJ'],0), 'hpD'=>max($r['hpD'],0), 'racha'=>$r['racha'],
                'escudo'=>$r['escudo'], 'furia'=>$r['furia'], 'puntos'=>$r['puntos'],
                'idx'=>$r['idx'], 'total'=>count($r['ids'])];
    }
    private function preguntaActual($r){
        $q = $r['key'][$r['ids'][$r['idx']]];
        $ops = [['A',$q['opcion_a']],['B',$q['opcion_b']],['C',$q['opcion_c']],['D',$q['opcion_d']]];
        shuffle($ops); // el orden lo decide el servidor
        return ['id'=>intval($q['id']), 'pregunta'=>$q['pregunta'], 'dificultad'=>$q['dificultad'], 'opciones'=>$ops];
    }
    private function textoOpcion($q, $letra){
        $map = ['A'=>$q['opcion_a'],'B'=>$q['opcion_b'],'C'=>$q['opcion_c'],'D'=>$q['opcion_d']];
        return $map[$letra] ?? '—';
    }

    /* ============ INICIAR PARTIDA ============ */
    public function iniciar(){
        $uid = $this->userId();
        if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'Sin sesión']); return; }
        $perfil = $this->fetchAll("SELECT gamer_id FROM arcade_perfil WHERE usuario_id=?", 'i', [$uid]);
        if(!count($perfil)){ echo json_encode(['ok'=>false,'msg'=>'Crea tu Gamer ID primero (hub del Arcade)']); return; }

        $curso = intval($_REQUEST['curso_id'] ?? 0);
        $cat   = intval($_REQUEST['categoria_id'] ?? 0);
        $dif   = in_array($_REQUEST['dificultad'] ?? '', ['facil','medio','dificil'], true) ? $_REQUEST['dificultad'] : '';
        $lim   = min(max(intval($_REQUEST['limite'] ?? 10), 5), 15);

        $sql = "SELECT * FROM preguntas WHERE 1=1";
        $types=''; $params=[];
        if($curso){ $sql .= " AND curso_id = ?";     $types.='i'; $params[]=$curso; }
        if($cat){   $sql .= " AND categoria_id = ?"; $types.='i'; $params[]=$cat; }
        if($dif){   $sql .= " AND dificultad = ?";   $types.='s'; $params[]=$dif; }
        $pool = $this->fetchAll($sql, $types, $params);

        if(count($pool) < 5){
            echo json_encode(['ok'=>false,'codigo'=>'POOL_INSUFICIENTE',
                'msg'=>'No hay suficientes preguntas para este curso/filtro. Usa PreguntaController → generar_curso (el bot) y vuelve a intentar.']);
            return;
        }
        shuffle($pool);
        $pool = array_slice($pool, 0, $lim);
        $key = []; $ids = [];
        foreach($pool as $q){ $ids[] = intval($q['id']); $key[intval($q['id'])] = $q; }

        $_SESSION['arcade_round'] = [
            'modo'=>'dragon', 'curso_id'=>$curso, 'dificultad'=>$dif,
            'ids'=>$ids, 'key'=>$key, 'idx'=>0,
            'hpJ'=>100, 'hpD'=>100, 'racha'=>0, 'maxRacha'=>0,
            'escudo'=>0, 'furia'=>false,
            'puntos'=>0, 'aciertos'=>0, 'errores'=>0, 'fallos'=>[]
        ];
        $r = $_SESSION['arcade_round'];
        echo json_encode(['ok'=>true,'gamer'=>$perfil[0]['gamer_id'],
                          'hud'=>$this->hud($r), 'pregunta'=>$this->preguntaActual($r)]);
    }

    /* ============ RESPONDER (reglas del dragón server-side) ============ */
    public function responder(){
        $r = $this->round();
        if(!$r){ echo json_encode(['ok'=>false,'msg'=>'No hay partida activa. Usa accion=iniciar']); return; }
        $letra   = strtoupper(trim($_POST['letra'] ?? ''));
        $elapsed = floatval($_POST['elapsed'] ?? 99);

        $q  = $r['key'][$r['ids'][$r['idx']]];
        $ok = ($letra === $q['correcta']);
        $ev = ['dmg'=>0,'dmg_rec'=>0,'crit'=>false,'escudo_ganado'=>false,'furia_activada'=>false];

        if($ok){
            $r['aciertos']++; $r['racha']++; $r['maxRacha'] = max($r['maxRacha'], $r['racha']);
            $dmg = 15 + $r['racha']*3;
            if($r['racha'] >= 3){ $dmg += 10; $ev['crit'] = true; }
            $r['hpD'] -= $dmg; $r['puntos'] += $dmg; $ev['dmg'] = $dmg;
            if($r['racha'] % 3 === 0){ $r['escudo'] = 1; $ev['escudo_ganado'] = true; }
            if(!$r['furia'] && $r['hpD'] <= 50 && $r['hpD'] > 0){ $r['furia'] = true; $ev['furia_activada'] = true; }
        } else {
            $r['errores']++; $r['racha'] = 0;
            $r['fallos'][] = ['q'=>$q['pregunta'], 'dada'=>$this->textoOpcion($q,$letra),
                              'correcta'=>$this->textoOpcion($q,$q['correcta']), 'exp'=>$q['explicacion'] ?? ''];
            if($r['escudo']){ $r['escudo'] = 0; }
            else { $dmgRec = $r['furia'] ? 30 : 20; $r['hpJ'] -= $dmgRec; $ev['dmg_rec'] = $dmgRec; }
        }
        $r['idx']++;

        /* ¿Terminó? */
        $fin = null;
        if($r['hpD'] <= 0 || $r['hpJ'] <= 0 || $r['idx'] >= count($r['ids'])){
            $resultado = ($r['hpD'] <= 0) ? 'victoria'
                       : (($r['hpJ'] <= 0) ? 'derrota'
                       : ($r['hpD'] < $r['hpJ'] ? 'victoria' : 'derrota'));
            $xp = $r['aciertos']*10 + $r['maxRacha']*5 + ($resultado==='victoria' ? 50 : 0);
            if($resultado==='victoria') $r['puntos'] += 50;
            $fin = $this->cerrarPartida($r, $resultado, $xp);
            unset($_SESSION['arcade_round']);
        } else {
            $_SESSION['arcade_round'] = $r;
        }

        echo json_encode([
            'ok'=>true, 'es_correcta'=>$ok,
            'correcta_letra'=>$q['correcta'], 'explicacion'=>$q['explicacion'] ?? '',
            'eventos'=>$ev, 'hud'=>$this->hud($r),
            'fin'=>$fin,
            'pregunta'=> $fin ? null : $this->preguntaActual($r)
        ]);
    }

    /* ============ CIERRE: guarda partida + XP ============ */
    private function cerrarPartida($r, $resultado, $xp){
        $uid = $this->userId();
        $detalle = trim(($r['curso_id'] ? 'curso '.$r['curso_id'].' ' : '') . ($r['dificultad'] ?: 'mixto'));
        try{
            $this->execute(
                "INSERT INTO arcade_partidas (usuario_id, modo, detalle, aciertos, errores, puntos, xp_ganada, resultado)
                 VALUES (?,?,?,?,?,?,?,?)", 'isiiiiis',
                [$uid, 'dragon', $detalle, $r['aciertos'], $r['errores'], $r['puntos'], $xp, $resultado]);
            $this->execute("UPDATE arcade_perfil SET xp = xp + ? WHERE usuario_id = ?", 'ii', [$xp, $uid]);
        }catch(Exception $e){ /* el juego no debe romperse por un log */ }
        return ['resultado'=>$resultado, 'xp'=>$xp, 'puntos'=>$r['puntos'],
                'aciertos'=>$r['aciertos'], 'errores'=>$r['errores'],
                'max_racha'=>$r['maxRacha'], 'fallos'=>$r['fallos']];
    }

    /* ============ ABANDONAR ============ */
    public function abandonar(){
        unset($_SESSION['arcade_round']);
        echo json_encode(['ok'=>true,'msg'=>'Partida abandonada']);
    }

    /* ============ ESTADO (por si el front recarga) ============ */
    public function estado(){
        $r = $this->round();
        if(!$r){ echo json_encode(['ok'=>true,'activa'=>false]); return; }
        echo json_encode(['ok'=>true,'activa'=>true,'hud'=>$this->hud($r),'pregunta'=>$this->preguntaActual($r)]);
    }

    public function handle(){
        switch($_REQUEST['accion'] ?? ''){
            case 'iniciar':    $this->iniciar();    break;
            case 'responder':  $this->responder();  break;
            case 'abandonar':  $this->abandonar();  break;
            case 'estado':     $this->estado();     break;
            default: echo json_encode(['ok'=>false,'msg'=>'Acción no válida']);
        }
    }
}
(new PartidaController())->handle();
?>