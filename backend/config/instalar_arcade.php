<?php
/**
 * DEVIOZ ARCADE — Instalador único de base de datos
 * 1) Abre: http://localhost/lms_prepa/backend/config/instalar_arcade.php
 * 2) Verifica el reporte de abajo
 * 3) BORRA este archivo al terminar
 */
require_once __DIR__ . '/conexion.php';
$db = Conexion::getConexion();
$db->set_charset('utf8mb4');

$tablas = [
'arcade_perfil' => "CREATE TABLE IF NOT EXISTS arcade_perfil (
  usuario_id   INT NOT NULL PRIMARY KEY,
  gamer_id     VARCHAR(16) NOT NULL UNIQUE,
  avatar       VARCHAR(8)  NOT NULL DEFAULT '🧙',
  color        VARCHAR(20) NOT NULL DEFAULT 'cian',
  titulo       VARCHAR(40) NOT NULL DEFAULT 'Novato',
  xp           INT NOT NULL DEFAULT 0,
  pts_ranked   INT NOT NULL DEFAULT 0,
  id_cambiado  TINYINT(1) NOT NULL DEFAULT 0,
  creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_perfil_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'preguntas' => "CREATE TABLE IF NOT EXISTS preguntas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id     INT NULL,
  categoria_id INT NULL,
  dificultad ENUM('facil','medio','dificil') NOT NULL DEFAULT 'facil',
  pregunta    TEXT NOT NULL,
  opcion_a    VARCHAR(255) NOT NULL,
  opcion_b    VARCHAR(255) NOT NULL,
  opcion_c    VARCHAR(255) NOT NULL,
  opcion_d    VARCHAR(255) NOT NULL,
  correcta    CHAR(1) NOT NULL DEFAULT 'A',
  explicacion TEXT NULL,
  creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_curso_dif (curso_id, dificultad),
  KEY idx_cat_dif  (categoria_id, dificultad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'arcade_partidas' => "CREATE TABLE IF NOT EXISTS arcade_partidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  modo VARCHAR(30) NOT NULL,
  detalle VARCHAR(60) NULL,
  aciertos INT NOT NULL DEFAULT 0,
  errores  INT NOT NULL DEFAULT 0,
  puntos   INT NOT NULL DEFAULT 0,
  xp_ganada INT NOT NULL DEFAULT 0,
  resultado ENUM('victoria','derrota','empate') NULL,
  jugado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_usuario (usuario_id),
  CONSTRAINT fk_partida_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'arcade_duelos' => "CREATE TABLE IF NOT EXISTS arcade_duelos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(6) NOT NULL UNIQUE,
  j1_id INT NOT NULL,
  j2_id INT NULL,
  preguntas TEXT NOT NULL,
  prog_j1 TEXT NULL,
  prog_j2 TEXT NULL,
  estado ENUM('esperando','jugando','fin') NOT NULL DEFAULT 'esperando',
  ganador_id INT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'arcade_equipos' => "CREATE TABLE IF NOT EXISTS arcade_equipos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL,
  tag VARCHAR(6) NOT NULL,
  codigo VARCHAR(6) NOT NULL UNIQUE,
  capitan_id INT NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'arcade_equipo_miembros' => "CREATE TABLE IF NOT EXISTS arcade_equipo_miembros (
  equipo_id INT NOT NULL,
  usuario_id INT NOT NULL,
  rol ENUM('capitan','miembro') NOT NULL DEFAULT 'miembro',
  PRIMARY KEY (equipo_id, usuario_id),
  UNIQUE KEY uq_un_equipo (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'arcade_equipo_stats' => "CREATE TABLE IF NOT EXISTS arcade_equipo_stats (
  equipo_id INT NOT NULL PRIMARY KEY,
  pts INT NOT NULL DEFAULT 0,
  wins INT NOT NULL DEFAULT 0,
  losses INT NOT NULL DEFAULT 0,
  racha INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'arcade_salas' => "CREATE TABLE IF NOT EXISTS arcade_salas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(6) NOT NULL UNIQUE,
  equipo1_id INT NOT NULL,
  equipo2_id INT NULL,
  jugadores TEXT NULL,
  preguntas TEXT NULL,
  estado ENUM('esperando','jugando','fin') NOT NULL DEFAULT 'esperando',
  ganador_equipo_id INT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'arcade_retos' => "CREATE TABLE IF NOT EXISTS arcade_retos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  de_equipo INT NOT NULL,
  para_equipo INT NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'revancha',
  sala_id INT NULL,
  estado ENUM('pendiente','aceptado','rechazado') NOT NULL DEFAULT 'pendiente',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_para (para_equipo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$semilla = "INSERT INTO preguntas (curso_id, categoria_id, dificultad, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, correcta, explicacion) VALUES
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Mate%' LIMIT 1),'facil','¿Cuánto es 7 × 8?','54','56','58','64','B','7 × 8 = 56. Truco: 7×(10−2) = 70−14.'),
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Mate%' LIMIT 1),'facil','Resuelve: 2x + 6 = 14','x = 2','x = 4','x = 6','x = 8','B','2x = 8 → x = 4.'),
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Mate%' LIMIT 1),'medio','¿Fórmula de la posición en MRUV?','x = x₀ + vt','x = x₀ + v₀t + ½at²','x = vt²','x = v₀ + at','B','Con aceleración constante aparece ½at².'),
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Mate%' LIMIT 1),'dificil','Derivada de f(x) = x³','x²','3x','3x²','x³/3','C','Regla de potencias: n·xⁿ⁻¹ = 3x².'),
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Cien%' LIMIT 1),'facil','¿Fórmula química del agua?','CO₂','H₂O','O₂','NaCl','B','Dos hidrógenos y un oxígeno.'),
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Cien%' LIMIT 1),'medio','Proceso por el que las plantas producen su alimento','Respiración','Fotosíntesis','Fermentación','Digestión','B','Luz + CO₂ + agua → glucosa + O₂.'),
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Cien%' LIMIT 1),'dificil','¿Qué ley de Newton dice F = m·a?','Primera','Segunda','Tercera','Gravitación','B','Segunda ley: fuerza = masa × aceleración.'),
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Comun%' LIMIT 1),'facil','Sinónimo de \"rápido\"','Lento','Veloz','Tranquilo','Débil','B','Veloz = que se mueve con rapidez.'),
(NULL, (SELECT id FROM categorias WHERE nombre LIKE '%Comun%' LIMIT 1),'medio','¿Cuál palabra lleva tilde?','Camion','Examen','Lapiz','Reloj','C','Lá-piz: grave que no termina en n, s o vocal.'),
(NULL, NULL,'facil','¿Capital del Perú?','Arequipa','Trujillo','Lima','Cusco','C','Lima, fundada en 1535.'),
(NULL, NULL,'medio','Sigue la serie: 2, 4, 8, 16, ...','24','30','32','64','C','Cada término duplica al anterior.'),
(NULL, NULL,'dificil','Un libro cuesta S/ 60 con 25% de descuento. ¿Cuánto pagas?','S/ 40','S/ 45','S/ 50','S/ 55','B','25% de 60 = 15; 60 − 15 = 45.')";

echo '<html><meta charset="utf-8"><body style="font-family:monospace;background:#0b0618;color:#e6e1ff;padding:30px;"><h2 style="color:#22d9ff;"> DEVIOZ ARCADE — Instalador</h2><pre style="font-size:14px;line-height:1.8;">';
echo "Base de datos conectada: <b style='color:#34d399;'>" . $db->select_db($db->query("SELECT DATABASE()")->fetch_row()[0]) . "</b> (" . $db->query("SELECT DATABASE()")->fetch_row()[0] . ")\n\n";

foreach ($tablas as $nombre => $sql) {
    if ($db->query($sql) === true) echo "✅ Tabla <b>$nombre</b> lista\n";
    else echo "❌ Tabla <b>$nombre</b>: " . $db->error . "\n";
}

$res = $db->query("SELECT COUNT(*) c FROM preguntas");
$cuantas = intval($res->fetch_assoc()['c']);
if ($cuantas === 0) {
    if ($db->query($semilla) === true) echo "\n✅ Semilla: 12 preguntas insertadas\n";
    else echo "\n❌ Semilla: " . $db->error . "\n";
} else {
    echo "\nℹ️ Semilla omitida: `preguntas` ya tiene $cuantas filas\n";
}

echo "\n--- VERIFICACIÓN ---\n";
$t = $db->query("SHOW TABLES LIKE 'arcade%'");
while ($r = $t->fetch_row()) echo "· " . $r[0] . "\n";
echo "· preguntas (con columna curso_id: " . (count($db->query("SHOW COLUMNS FROM preguntas LIKE 'curso_id'")->fetch_all()) ? 'SÍ' : 'NO') . ")\n";
echo "\n BORRA este archivo (instalar_arcade.php) cuando termines.";
echo '</pre></body></html>';