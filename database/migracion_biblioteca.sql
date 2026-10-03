-- =====================================================
-- MIGRACIÓN - MÓDULO DE BIBLIOTECA
-- LMS PREPA
--
-- Este script es SEGURO para bases de datos que ya existen
-- y que tienen datos que no quieres perder. NO borra nada.
--
-- Úsalo si ya habías creado la base de datos antes con
-- lms_prepa.sql / estructura.sql y no quieres empezar de cero.
--
-- Si vas a crear la base de datos por primera vez, no necesitas
-- este archivo: usa lms_prepa.sql (o estructura.sql), que ya
-- incluye todo esto.
-- =====================================================

USE lms_prepa;

-- ---------------------------------------------------------------
-- 1) Permitir los roles 'estudiante' y 'profesor' en usuarios.rol
--    (antes el ENUM solo aceptaba 'admin' y 'usuario', lo que
--    hacía fallar el registro de nuevas cuentas)
-- ---------------------------------------------------------------
ALTER TABLE usuarios
    MODIFY rol ENUM('admin', 'usuario', 'estudiante', 'profesor') NOT NULL DEFAULT 'usuario';

-- ---------------------------------------------------------------
-- 2) Tablas del módulo de biblioteca (no existían todavía)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS universidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    pais VARCHAR(100) NOT NULL DEFAULT 'Perú',
    tipo ENUM('pública', 'privada') NOT NULL DEFAULT 'pública',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS autores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(200) NOT NULL,
    nacionalidad VARCHAR(100) DEFAULT 'Desconocida',
    biografia TEXT,
    
    UNIQUE KEY uk_nombre_completo (nombre_completo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS editoriales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    pais VARCHAR(100) DEFAULT 'Perú',
    sitio_web VARCHAR(255) DEFAULT '',
    
    UNIQUE KEY uk_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS libros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) DEFAULT NULL,
    autor_principal_id INT DEFAULT NULL,
    editorial_id INT DEFAULT NULL,
    universidad_id INT NOT NULL,
    materia_id INT NOT NULL,
    anio_publicacion INT DEFAULT NULL,
    numero_paginas INT DEFAULT NULL,
    descripcion TEXT,
    archivo_pdf VARCHAR(255) DEFAULT NULL,
    portada VARCHAR(255) DEFAULT NULL,
    estado ENUM('activo', 'eliminado') NOT NULL DEFAULT 'activo',
    descargas INT DEFAULT 0,
    calificacion_promedio DECIMAL(3,2) DEFAULT 0.00,
    es_recomendado TINYINT(1) DEFAULT 0,
    fecha_subido DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (autor_principal_id) REFERENCES autores(id) ON DELETE SET NULL,
    FOREIGN KEY (editorial_id) REFERENCES editoriales(id) ON DELETE SET NULL,
    FOREIGN KEY (universidad_id) REFERENCES universidades(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE RESTRICT,
    INDEX idx_estado (estado),
    INDEX idx_universidad (universidad_id),
    INDEX idx_materia (materia_id),
    INDEX idx_isbn (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS biblioteca_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    libro_id INT NOT NULL,
    fecha_descarga DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (libro_id) REFERENCES libros(id) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario_libro (usuario_id, libro_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_libro (libro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 3) Universidades de ejemplo (solo si la tabla está vacía)
-- ---------------------------------------------------------------
INSERT INTO universidades (nombre, pais, tipo)
SELECT * FROM (SELECT 'Universidad Nacional Mayor de San Marcos' AS nombre, 'Perú' AS pais, 'pública' AS tipo) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM universidades LIMIT 1);

INSERT INTO universidades (nombre, pais, tipo)
SELECT * FROM (SELECT 'Pontificia Universidad Católica del Perú', 'Perú', 'privada') AS tmp
WHERE (SELECT COUNT(*) FROM universidades) <= 1;

INSERT INTO universidades (nombre, pais, tipo)
SELECT * FROM (SELECT 'Universidad Nacional de Ingeniería', 'Perú', 'pública') AS tmp
WHERE (SELECT COUNT(*) FROM universidades) <= 2;

SELECT '✅ Migración del módulo de biblioteca aplicada correctamente' AS mensaje;
