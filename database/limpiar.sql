-- =====================================================
-- LIMPIAR BASE DE DATOS - SOLO DESARROLLO
-- ⚠️  ESTO ELIMINA TODOS LOS DATOS
-- =====================================================

USE lms_prepa;

-- Desactivar verificación de foreign keys temporalmente
SET FOREIGN_KEY_CHECKS = 0;

-- Limpiar tablas en orden (de dependientes a independientes)
TRUNCATE TABLE logs_actividad;
TRUNCATE TABLE sesiones;
TRUNCATE TABLE calificaciones;
TRUNCATE TABLE inscripciones;
TRUNCATE TABLE cursos;
TRUNCATE TABLE materias;
TRUNCATE TABLE profesores;
TRUNCATE TABLE estudiantes;
TRUNCATE TABLE codigos_verificacion;
TRUNCATE TABLE usuarios;

-- Reactivar verificación
SET FOREIGN_KEY_CHECKS = 1;

SELECT '🧹 Base de datos limpiada correctamente' AS mensaje;