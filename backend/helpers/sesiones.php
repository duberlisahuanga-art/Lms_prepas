<?php
/**
 * GESTIÓN DE SESIONES - DEVIOZ ACADEMY
 * Manejo seguro de sesiones de usuario
 */

class Sesiones {
    
    /**
     * Iniciar sesión de usuario
     */
    public static function iniciar($usuario) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerar ID de sesión para prevenir fijación de sesión
        session_regenerate_id(true);
        
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_email'] = $usuario['email'] ?? '';
        $_SESSION['usuario_rol'] = $usuario['rol'];
        $_SESSION['usuario_estado'] = $usuario['estado'] ?? 'activo';
        $_SESSION['inicio_sesion'] = time();
        
        return true;
    }
    
    /**
     * Cerrar sesión
     */
    public static function cerrar() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        return true;
    }
    
    /**
     * Verificar si hay sesión activa
     */
    public static function estaLogueado() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
    }
    
    /**
     * Obtener datos del usuario en sesión
     */
    public static function getUsuario() {
        if (!self::estaLogueado()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['usuario_id'],
            'nombre' => $_SESSION['usuario_nombre'],
            'email' => $_SESSION['usuario_email'] ?? '',
            'rol' => $_SESSION['usuario_rol'],
            'estado' => $_SESSION['usuario_estado'] ?? 'activo'
        ];
    }
    
    /**
     * Verificar si el usuario es admin
     */
    public static function esAdmin() {
        return self::estaLogueado() && ($_SESSION['usuario_rol'] ?? '') === 'admin';
    }
    
    /**
     * Verificar si el usuario es estudiante
     */
    public static function esEstudiante() {
        return self::estaLogueado() && ($_SESSION['usuario_rol'] ?? '') === 'estudiante';
    }
    
    /**
     * Verificar rol específico
     */
    public static function tieneRol($rol) {
        return self::estaLogueado() && ($_SESSION['usuario_rol'] ?? '') === $rol;
    }
    
    /**
     * Obtener ID del usuario
     */
    public static function getId() {
        return $_SESSION['usuario_id'] ?? null;
    }
    
    /**
     * Obtener nombre del usuario
     */
    public static function getNombre() {
        return $_SESSION['usuario_nombre'] ?? 'Usuario';
    }
    
    /**
     * Obtener rol del usuario
     */
    public static function getRol() {
        return $_SESSION['usuario_rol'] ?? null;
    }
    
    /**
     * Verificar tiempo de sesión (timeout después de 2 horas)
     */
    public static function sesionActiva($timeout = 7200) {
        if (!self::estaLogueado()) {
            return false;
        }
        
        $inicio = $_SESSION['inicio_sesion'] ?? 0;
        if ((time() - $inicio) > $timeout) {
            self::cerrar();
            return false;
        }
        
        // Actualizar tiempo de sesión
        $_SESSION['inicio_sesion'] = time();
        return true;
    }
    
    /**
     * Proteger ruta (redirige al login si no hay sesión)
     */
    public static function protegerRuta($rolRequerido = null) {
        if (!self::estaLogueado()) {
            header('Location: ../pages/index.html');
            exit;
        }
        
        if ($rolRequerido && $_SESSION['usuario_rol'] !== $rolRequerido) {
            // Si el rol no coincide, redirigir según el rol real
            if ($_SESSION['usuario_rol'] === 'admin') {
                header('Location: dashboard_admin.html');
            } else {
                header('Location: dashboard_estudiante.html');
            }
            exit;
        }
        
        return true;
    }
    
    /**
     * Actualizar datos de sesión (después de editar perfil)
     */
    public static function actualizar($campo, $valor) {
        if (self::estaLogueado() && isset($_SESSION[$campo])) {
            $_SESSION[$campo] = $valor;
            return true;
        }
        return false;
    }
}
?>