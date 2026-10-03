<?php
/**
 * MODELO USUARIO - DEVIOZ ACADEMY
 * Gestión de usuarios (admin y estudiantes)
 */

require_once __DIR__ . '/../config/conexion.php';

class Usuario {
    
    private $conn;
    private $table = 'usuarios';
    
    // Propiedades
    public $id;
    public $nombre;
    public $email;
    public $password;
    public $rol;
    public $estado;
    public $created_at;
    
    public function __construct() {
        $this->conn = Conexion::getConexion();
    }
    
    // ==========================================
    // CREAR USUARIO
    // ==========================================
    public function crear() {
        // Verificar si el email ya existe
        $sql = "SELECT id FROM {$this->table} WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $this->email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            return false; // Email ya registrado
        }
        
        $hash = password_hash($this->password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO {$this->table} (nombre, email, password, rol, estado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sssss', $this->nombre, $this->email, $hash, $this->rol, $this->estado);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            return true;
        }
        return false;
    }
    
    // ==========================================
    // OBTENER POR EMAIL (para login)
    // ==========================================
    public function obtenerPorEmail() {
        $sql = "SELECT id, nombre, email, password, rol, estado, created_at FROM {$this->table} WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $this->email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    
    // ==========================================
    // OBTENER POR ID
    // ==========================================
    public function obtenerPorId() {
        $sql = "SELECT id, nombre, email, rol, estado, created_at FROM {$this->table} WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    
    // ==========================================
    // LISTAR TODOS
    // ==========================================
    public function listar() {
        $sql = "SELECT id, nombre, email, rol, estado, created_at FROM {$this->table} ORDER BY id DESC";
        $result = $this->conn->query($sql);
        
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        return $usuarios;
    }
    
    // ==========================================
    // ACTUALIZAR
    // ==========================================
    public function actualizar() {
        $sql = "UPDATE {$this->table} SET nombre = ?, rol = ?, estado = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sssi', $this->nombre, $this->rol, $this->estado, $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // ACTUALIZAR CONTRASEÑA
    // ==========================================
    public function actualizarPassword() {
        $hash = password_hash($this->password, PASSWORD_BCRYPT);
        $sql = "UPDATE {$this->table} SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $hash, $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // ELIMINAR (eliminación física)
    // ==========================================
    public function eliminar() {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // DESACTIVAR (eliminación lógica)
    // ==========================================
    public function desactivar() {
        $sql = "UPDATE {$this->table} SET estado = 'inactivo' WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // CONTAR USUARIOS
    // ==========================================
    public function contar($estado = null, $rol = null) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];
        $types = '';
        
        if ($estado) {
            $sql .= " AND estado = ?";
            $params[] = $estado;
            $types .= 's';
        }
        
        if ($rol) {
            $sql .= " AND rol = ?";
            $params[] = $rol;
            $types .= 's';
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['total'];
    }
    
    // ==========================================
    // VERIFICAR CONTRASEÑA
    // ==========================================
    public function verificarPassword($passwordIngresada, $passwordHash) {
        return password_verify($passwordIngresada, $passwordHash);
    }
}
?>