-- =====================================================
-- DATOS INICIALES - LMS PREPA
-- Ejecutar DESPUÉS de lms_prepa.sql
-- =====================================================

USE lms_prepa;

-- =====================================================
-- ADMIN POR DEFECTO
-- Email: admin@lms.com
-- Contraseña: admin123
-- =====================================================
-- Hash bcrypt generado y verificado para "admin123"
INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES
('Administrador General', 'admin@lms.com', 
 '$2y$10$3zBGigTIWjA3DEWIPGD73uoAf1MvC99o.lnI85tGtBHc9CTQqYhDi', 
 'admin', 'activo');


-- =====================================================
-- USUARIO DE PRUEBA (ya verificado)
-- Email: usuario@prueba.com
-- Contraseña: usuario123
-- =====================================================
-- Hash bcrypt generado y verificado para "usuario123"
INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES
('Juan Pérez García', 'usuario@prueba.com', 
 '$2y$10$HB49IcB2Ptlfjq7xO6jOtO5IEuorrMnNCPRn6DRPOlcnziwcInp1G', 
 'usuario', 'activo');


-- =====================================================
-- ESTUDIANTE DE PRUEBA (ya verificado)
-- Email: estudiante@prueba.com
-- Contraseña: estudiante123
-- =====================================================
INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES
('Ana Torres Ramírez', 'estudiante@prueba.com', 
 '$2y$10$gZyOFjUzwhcRCC1.UOjl2O/B1thc04MZRYI233ljkcNJPrE6IbUza', 
 'estudiante', 'activo');


-- =====================================================
-- MATERIAS DE PREPARATORIA
-- =====================================================
INSERT INTO materias (nombre, clave, descripcion, creditos) VALUES
('Matemáticas I', 'MAT101', 'Álgebra y aritmética básica', 5),
('Matemáticas II', 'MAT102', 'Geometría y trigonometría', 5),
('Matemáticas III', 'MAT103', 'Cálculo diferencial', 6),
('Física I', 'FIS101', 'Mecánica clásica', 5),
('Física II', 'FIS102', 'Termodinámica y ondas', 5),
('Química', 'QUI101', 'Química general', 5),
('Biología', 'BIO101', 'Biología celular y molecular', 5),
('Historia Universal', 'HIS101', 'Historia de la humanidad', 4),
('Historia de México', 'HIS102', 'Historia nacional', 4),
('Literatura', 'LIT101', 'Literatura hispanoamericana', 4),
('Español', 'ESP101', 'Gramática y redacción', 4),
('Inglés I', 'ING101', 'Inglés básico', 4),
('Inglés II', 'ING102', 'Inglés intermedio', 4),
('Filosofía', 'FIL101', 'Introducción a la filosofía', 4),
('Ética', 'ETI101', 'Ética y valores', 3),
('Informática', 'INF101', 'Computación básica', 4),
('Educación Física', 'EDF101', 'Deportes y salud', 2);


-- =====================================================
-- PROFESORES DE EJEMPLO
-- =====================================================
-- Nota: Primero se deben crear los usuarios de los profesores
-- Este script asume que los usuarios ya existen con IDs específicos

-- Si quieres crear usuarios profesores completos, usa este formato:
/*
INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES
('Prof. María López', 'maria.lopez@lms.com', 'HASH_AQUI', 'usuario', 'activo');

INSERT INTO profesores (usuario_id, especialidad, telefono) VALUES
(LAST_INSERT_ID(), 'Matemáticas', '555-0101');
*/


-- =====================================================
-- CURSOS DE EJEMPLO
-- =====================================================
-- (Descomentar cuando existan profesores)
/*
INSERT INTO cursos (materia_id, profesor_id, nombre, horario, aula, cupo_maximo) VALUES
(1, 1, 'Matemáticas I - Grupo A', 'Lun-Mié 8:00-10:00', 'Aula 101', 30),
(2, 1, 'Matemáticas II - Grupo A', 'Mar-Jue 10:00-12:00', 'Aula 102', 30),
(4, 2, 'Física I - Grupo A', 'Lun-Mié 10:00-12:00', 'Lab Física', 25);
*/


-- =====================================================
-- UNIVERSIDADES DE EJEMPLO (módulo biblioteca)
-- =====================================================
INSERT INTO universidades (nombre, pais, tipo) VALUES
('Universidad Nacional Mayor de San Marcos', 'Perú', 'pública'),
('Pontificia Universidad Católica del Perú', 'Perú', 'privada'),
('Universidad Nacional de Ingeniería', 'Perú', 'pública'),
('Universidad Peruana de Ciencias Aplicadas', 'Perú', 'privada'),
('Universidad del Pacífico', 'Perú', 'privada');


-- =====================================================
-- EDITORIALES DE EJEMPLO (módulo biblioteca)
-- =====================================================
INSERT INTO editoriales (nombre, pais, sitio_web) VALUES
('Fondo Editorial PUCP', 'Perú', 'https://fondoeditorial.pucp.edu.pe'),
('Editorial San Marcos', 'Perú', ''),
('McGraw-Hill Education', 'Estados Unidos', 'https://www.mheducation.com'),
('Pearson', 'Reino Unido', 'https://www.pearson.com');


-- =====================================================
-- MENSAJE FINAL
-- =====================================================
SELECT '✅ Base de datos LMS Prepa creada exitosamente' AS mensaje;
SELECT CONCAT('📧 Admin: admin@lms.com / admin123') AS credenciales;
SELECT CONCAT('📧 Usuario: usuario@prueba.com / usuario123') AS credenciales;
SELECT CONCAT('📧 Estudiante: estudiante@prueba.com / estudiante123') AS credenciales;