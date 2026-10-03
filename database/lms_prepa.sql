-- =====================================================
-- LMS PREPA - Sistema de Gestión Educativa
-- Script principal de creación de base de datos
-- Tecnología: MySQL (XAMPP)
-- =====================================================

-- Eliminar BD si existe (solo en desarrollo)
DROP DATABASE IF EXISTS lms_prepa;

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS lms_prepa 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE lms_prepa;

-- =====================================================
-- TABLA: usuarios
-- Almacena admins y usuarios registrados
-- =====================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'usuario', 'estudiante', 'profesor') NOT NULL DEFAULT 'usuario',
    estado ENUM('pendiente', 'activo', 'inactivo', 'bloqueado') NOT NULL DEFAULT 'pendiente',
    avatar VARCHAR(255) DEFAULT NULL,
    ultimo_acceso DATETIME DEFAULT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para optimización
    INDEX idx_email (email),
    INDEX idx_rol (rol),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: codigos_verificacion
-- Guarda códigos de 6 dígitos para registro y recuperación
-- =====================================================
CREATE TABLE codigos_verificacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    codigo VARCHAR(6) NOT NULL,
    tipo ENUM('registro', 'recuperar', 'cambiar_email') NOT NULL,
    intentos INT DEFAULT 0,
    usado TINYINT(1) DEFAULT 0,
    expira DATETIME NOT NULL,
    creado DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email_tipo (email, tipo),
    INDEX idx_expira (expira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: profesores
-- Información específica de profesores
-- =====================================================
CREATE TABLE profesores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    especialidad VARCHAR(100),
    telefono VARCHAR(20),
    fecha_ingreso DATE,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: estudiantes
-- Información específica de estudiantes
-- =====================================================
CREATE TABLE estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    matricula VARCHAR(20) UNIQUE,
    telefono VARCHAR(20),
    direccion VARCHAR(255),
    fecha_nacimiento DATE,
    grado VARCHAR(20),
    grupo VARCHAR(5),
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_matricula (matricula),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: materias
-- Materias disponibles en la preparatoria
-- =====================================================
CREATE TABLE materias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    clave VARCHAR(20) UNIQUE,
    descripcion TEXT,
    creditos INT DEFAULT 1,
    activa TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: cursos
-- Cursos/materias impartidas
-- =====================================================
CREATE TABLE cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    materia_id INT NOT NULL,
    profesor_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    horario VARCHAR(100),
    aula VARCHAR(50),
    cupo_maximo INT DEFAULT 30,
    estado ENUM('activo', 'inactivo', 'finalizado') DEFAULT 'activo',
    fecha_inicio DATE,
    fecha_fin DATE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE RESTRICT,
    FOREIGN KEY (profesor_id) REFERENCES profesores(id) ON DELETE RESTRICT,
    INDEX idx_materia (materia_id),
    INDEX idx_profesor (profesor_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: inscripciones
-- Estudiantes inscritos en cursos
-- =====================================================
CREATE TABLE inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    curso_id INT NOT NULL,
    fecha_inscripcion DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'baja', 'completado') DEFAULT 'activo',
    
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    UNIQUE KEY uk_estudiante_curso (estudiante_id, curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: calificaciones
-- Notas de los estudiantes
-- =====================================================
CREATE TABLE calificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inscripcion_id INT NOT NULL,
    tipo_evaluacion ENUM('parcial', 'tarea', 'examen', 'proyecto', 'final') NOT NULL,
    descripcion VARCHAR(150),
    calificacion DECIMAL(5,2) NOT NULL,
    peso DECIMAL(5,2) DEFAULT 1.00,
    fecha_evaluacion DATE NOT NULL,
    observaciones TEXT,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE,
    INDEX idx_inscripcion (inscripcion_id),
    INDEX idx_tipo (tipo_evaluacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: sesiones
-- Control de sesiones activas (opcional, para logout remoto)
-- =====================================================
CREATE TABLE sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    ip VARCHAR(45),
    user_agent TEXT,
    activa TINYINT(1) DEFAULT 1,
    expira DATETIME NOT NULL,
    creado DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: logs_actividad
-- Auditoría de acciones importantes
-- =====================================================
CREATE TABLE logs_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT,
    ip VARCHAR(45),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: universidades
-- Universidades/institutos de la biblioteca digital
-- =====================================================
CREATE TABLE universidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    pais VARCHAR(100) NOT NULL DEFAULT 'Perú',
    tipo ENUM('pública', 'privada') NOT NULL DEFAULT 'pública',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: autores
-- Autores de los libros de la biblioteca digital
-- =====================================================
CREATE TABLE autores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(200) NOT NULL,
    nacionalidad VARCHAR(100) DEFAULT 'Desconocida',
    biografia TEXT,
    
    UNIQUE KEY uk_nombre_completo (nombre_completo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: editoriales
-- Editoriales de los libros de la biblioteca digital
-- =====================================================
CREATE TABLE editoriales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    pais VARCHAR(100) DEFAULT 'Perú',
    sitio_web VARCHAR(255) DEFAULT '',
    
    UNIQUE KEY uk_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- TABLA: libros
-- Catálogo de libros de la biblioteca digital
-- =====================================================
CREATE TABLE libros (
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


-- =====================================================
-- TABLA: biblioteca_usuario
-- Historial de libros descargados/leídos por cada usuario
-- =====================================================
CREATE TABLE biblioteca_usuario (
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