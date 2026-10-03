<?php
/**
 * DEVIOZ ARCADE — Reparador + Verificador (archivo NUEVO)
 * 1) Respalda la tabla `preguntas` vieja/incompatible como `preguntas_old_backup`
 * 2) Crea/verifica las 9 tablas del juego + semilla
 * 3) Chequea controllers, sesión y Gamer ID
 * Abre: http://localhost/lms_prepa/backend/config/reparar_arcade.php
 * Borra este archivo (y el instalar_arcade.php viejo) cuando todo salga verde.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
$db = Conexion::getConexion();
$db->set_charset('utf8mb4');

$okAll = true;
function linea($ok, $txt){ global $okAll; if(!$ok) $okAll = false; echo ($ok ? '✅ ' : '❌ ') . $txt . "\n"; }

echo '<html><meta charset="utf-8"><body style="font-family:monospace;background:#0b0618;color:#e6e1ff;padding:30px;"><h2 style="color:#22d9ff;">🎮 DEVIOZ ARCADE — Reparación + Verificación</h2><pre style="font-size:14px;line-height:1.8;">';

/* ---------- 0) CONEXIÓN ---------- */
$bd = $db->query("SELECT DATABASE()")->fetch_row()[0];
linea(true, "Base conectada: <b>$bd</b>");

/* ---------- 1) TABLA `preguntas` VIEJA → RESPALDO (este era tu error) ---------- */
if ($db->query("SHOW TABLES LIKE 'preguntas'")->num_rows > 0) {
    $names = [];
    $cols = $db->query("SHOW COLUMNS FROM preguntas");
    while ($c = $cols->fetch_assoc()) $names[] = $c['Field'];
    $necesarias = ['opcion_a','opcion_b','opcion_c','opcion_d','correcta','curso_id'];
    if (count(array_diff($necesarias, $names))) {
        $db->query("RENAME TABLE preguntas TO preguntas_old_backup");
        linea(true, "`preguntas` vieja SIN columnas del Arcade → movida a `preguntas_old_backup` (nada se perdió)");
    } else {
        linea(true, "`preguntas` ya tiene el esquema del Arcade: se conserva");
    }
}

/* ---------- 2) TABLAS (9) ---------- */
$tablas = [
'arcade_perfil' => "CREATE TABLE IF NOT EXISTS arcade_perfil (
  usuario_id INT NOT NULL PRIMARY KEY, gamer_id VARCHAR(16) NOT NULL UNIQUE,
  avatar VARCHAR(8) NOT NULL DEFAULT '🧙', color VARCHAR(20) NOT NULL DEFAULT 'cian',
  titulo VARCHAR(40) NOT NULL DEFAULT 'Novato', xp INT NOT NULL DEFAULT 0,
  pts_ranked INT NOT NULL DEFAULT 0, id_cambiado TINYINT(1) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_perfil_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'preguntas' => "CREATE TABLE IF NOT EXISTS preguntas (
  id INT AUTO_INCREMENT PRIMARY KEY, curso_id INT NULL, categoria_id INT NULL,
  dificultad ENUM('facil','medio','dificil') NOT NULL DEFAULT 'facil',
  pregunta TEXT NOT NULL, opcion_a VARCHAR(255) NOT NULL, opcion_b VARCHAR(255) NOT NULL,
  opcion_c VARCHAR(255) NOT NULL, opcion_d VARCHAR(255) NOT NULL,
  correcta CHAR(1) NOT NULL DEFAULT 'A', explicacion TEXT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_curso_dif (curso_id, dificultad), KEY idx_cat_dif (categoria_id, dificultad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'arcade_partidas' => "CREATE TABLE IF NOT EXISTS arcade_partidas (
  id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, modo VARCHAR(30) NOT NULL,
  detalle VARCHAR(60) NULL, aciertos INT NOT NULL DEFAULT 0, errores INT NOT NULL DEFAULT 0,
  puntos INT NOT NULL DEFAULT 0, xp_ganada INT NOT NULL DEFAULT 0,
  resultado ENUM('victoria','derrota','empate') NULL, jugado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_usuario (usuario_id),
  CONSTRAINT fk_partida_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'arcade_duelos' => "CREATE TABLE IF NOT EXISTS arcade_duelos (
  id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(6) NOT NULL UNIQUE,
  j1_id INT NOT NULL, j2_id INT NULL, preguntas TEXT NOT NULL, prog_j1 TEXT NULL, prog_j2 TEXT NULL,
  estado ENUM('esperando','jugando','fin') NOT NULL DEFAULT 'esperando', ganador_id INT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'arcade_equipos' => "CREATE TABLE IF NOT EXISTS arcade_equipos (
  id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(60) NOT NULL, tag VARCHAR(6) NOT NULL,
  codigo VARCHAR(6) NOT NULL UNIQUE, capitan_id INT NOT NULL, creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'arcade_equipo_miembros' => "CREATE TABLE IF NOT EXISTS arcade_equipo_miembros (
  equipo_id INT NOT NULL, usuario_id INT NOT NULL,
  rol ENUM('capitan','miembro') NOT NULL DEFAULT 'miembro',
  PRIMARY KEY (equipo_id, usuario_id), UNIQUE KEY uq_un_equipo (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'arcade_equipo_stats' => "CREATE TABLE IF NOT EXISTS arcade_equipo_stats (
  equipo_id INT NOT NULL PRIMARY KEY, pts INT NOT NULL DEFAULT 0, wins INT NOT NULL DEFAULT 0,
  losses INT NOT NULL DEFAULT 0, racha INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'arcade_salas' => "CREATE TABLE IF NOT EXISTS arcade_salas (
  id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(6) NOT NULL UNIQUE,
  equipo1_id INT NOT NULL, equipo2_id INT NULL, jugadores TEXT NULL, preguntas TEXT NULL,
  estado ENUM('esperando','jugando','fin') NOT NULL DEFAULT 'esperando', ganador_equipo_id INT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
'arcade_retos' => "CREATE TABLE IF NOT EXISTS arcade_retos (
  id INT AUTO_INCREMENT PRIMARY KEY, de_equipo INT NOT NULL, para_equipo INT NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'revancha', sala_id INT NULL,
  estado ENUM('pendiente','aceptado','rechazado') NOT NULL DEFAULT 'pendiente',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_para (para_equipo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($tablas as $nombre => $sql) {
    linea($db->query($sql) === true, "Tabla <b>$nombre</b>");
}

/* ---------- 3) SEMILLA (solo si está vacía) ---------- */
$semilla = "INSERT INTO preguntas (curso_id, categoria_id, dificultad, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, correcta, explicacion) VALUES
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Mate%' LIMIT 1),'facil','¿Cuánto es 7 × 8?','54','56','58','64','B','7 × 8 = 56.'),
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Mate%' LIMIT 1),'facil','Resuelve: 2x + 6 = 14','x = 2','x = 4','x = 6','x = 8','B','2x = 8 → x = 4.'),
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Mate%' LIMIT 1),'medio','¿Fórmula de la posición en MRUV?','x = x₀ + vt','x = x₀ + v₀t + ½at²','x = vt²','x = v₀ + at','B','Aparece ½at².'),
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Mate%' LIMIT 1),'dificil','Derivada de f(x) = x³','x²','3x','3x²','x³/3','C','n·xⁿ⁻¹ = 3x².'),
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Cien%' LIMIT 1),'facil','¿Fórmula química del agua?','CO₂','H₂O','O₂','NaCl','B','H₂O.'),
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Cien%' LIMIT 1),'medio','Proceso por el que las plantas producen su alimento','Respiración','Fotosíntesis','Fermentación','Digestión','B','Luz+CO₂+agua→glucosa+O₂.'),
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Cien%' LIMIT 1),'dificil','¿Qué ley de Newton dice F = m·a?','Primera','Segunda','Tercera','Gravitación','B','Segunda ley.'),
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Comun%' LIMIT 1),'facil','Sinónimo de \"rápido\"','Lento','Veloz','Tranquilo','Débil','B','Veloz.'),
(NULL,(SELECT id FROM categorias WHERE nombre LIKE '%Comun%' LIMIT 1),'medio','¿Cuál palabra lleva tilde?','Camion','Examen','Lapiz','Reloj','C','Lá-piz.'),
(NULL,NULL,'facil','¿Capital del Perú?','Arequipa','Trujillo','Lima','Cusco','C','Lima, 1535.'),
(NULL,NULL,'medio','Sigue la serie: 2, 4, 8, 16, ...','24','30','32','64','C','Se duplica.'),
(NULL,NULL,'dificil','Un libro cuesta S/ 60 con 25% de descuento. ¿Cuánto pagas?','S/ 40','S/ 45','S/ 50','S/ 55','B','60−15=45.')";
$cuantas = intval($db->query("SELECT COUNT(*) c FROM preguntas")->fetch_assoc()['c']);
if ($cuantas === 0) linea($db->query($semilla) === true, "Semilla: 12 preguntas insertadas");
else linea(true, "Semilla omitida: ya hay $cuantas preguntas");

/* ---------- 4) CHEQUEOS ESTRUCTURALES ---------- */
linea($db->query("SHOW COLUMNS FROM preguntas LIKE 'curso_id'")->num_rows > 0, "preguntas.curso_id existe (bot por curso)");
$uni = false;
$idx = $db->query("SHOW INDEX FROM arcade_perfil");
while ($r = $idx->fetch_assoc()) if ($r['Column_name'] === 'gamer_id' && $r['Non_unique'] == 0) $uni = true;
linea($uni, "arcade_perfil.gamer_id es UNIQUE (IDs irrepetibles)");
linea(intval($db->query("SELECT COUNT(*) c FROM preguntas")->fetch_assoc()['c']) >= 5, "Banco con ≥5 preguntas jugables");

/* ---------- 5) CONTROLLERS DEL JUEGO ---------- */
foreach (['ArcadeController.php','PreguntaController.php','PartidaController.php'] as $f) {
    linea(file_exists(__DIR__ . '/../controllers/' . $f), "Controller <b>$f</b> presente");
}

/* ---------- 6) SESIÓN + GAMER ID ---------- */
$uid = intval($_SESSION['usuario_id'] ?? 0);
if ($uid) {
    linea(true, "Sesión activa: usuario_id=$uid");
    $p = $db->query("SELECT gamer_id FROM arcade_perfil WHERE usuario_id=$uid");
    if ($p->num_rows) linea(true, "Gamer ID: " . $p->fetch_assoc()['gamer_id']);
    else linea(false, "Sin Gamer ID aún (normal: se crea al entrar al hub del Arcade)");
} else {
    linea(false, "Sin sesión: loguéate como estudiante antes de abrir esto");
}

echo "\n" . ($okAll
    ? "🟢 <b style='color:#34d399;'>TODO VERDE.</b> Backend del Arcade operativo. Borra este archivo y el instalar_arcade.php viejo."
    : "🔴 <b style='color:#fb7185;'>Hay rojos arriba.</b> Pégame este reporte tal cual y lo corrijo al instante.");
echo '</pre></body></html>';