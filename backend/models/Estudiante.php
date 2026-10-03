<?php
/**
 * MODELO ESTUDIANTE - DEVIOZ ACADEMY
 * Gestión específica de estudiantes (extiende funcionalidad de Usuario)
 */

require_once __DIR__ . '/../config/conexion.php';

class Estudiante {
    
    private $conn;
    private $table = 'usuarios';
    
    // Propiedades
    public $id;
    public $nombre;
    public $email;
    public $estado;
    public $created_at;
    
    public function __construct() {
        $this->conn = Conexion::getConexion();
    }
    
    // ==========================================
    // LISTAR SOLO ESTUDIANTES
    // ==========================================
    public function listar($estado = null) {
        $sql = "SELECT id, nombre, email, rol, estado, created_at FROM {$this->table} WHERE rol = 'estudiante'";
        
        if ($estado) {
            $sql .= " AND estado = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('s', $estado);
        } else {
            $stmt = $this->conn->prepare($sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $estudiantes = [];
        while ($row = $result->fetch_assoc()) {
            $estudiantes[] = $row;
        }
        return $estudiantes;
    }
    
    // ==========================================
    // OBTENER ESTUDIANTE POR ID
    // ==========================================
    public function obtenerPorId() {
        $sql = "SELECT id, nombre, email, rol, estado, created_at FROM {$this->table} WHERE id = ? AND rol = 'estudiante'";
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
    // OBTENER POR EMAIL
    // ==========================================
    public function obtenerPorEmail() {
        $sql = "SELECT id, nombre, email, rol, estado, created_at FROM {$this->table} WHERE email = ? AND rol = 'estudiante'";
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
    // ACTUALIZAR ESTUDIANTE
    // ==========================================
    public function actualizar() {
        $sql = "UPDATE {$this->table} SET nombre = ?, estado = ? WHERE id = ? AND rol = 'estudiante'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssi', $this->nombre, $this->estado, $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // ELIMINAR ESTUDIANTE
    // ==========================================
    public function eliminar() {
        $sql = "DELETE FROM {$this->table} WHERE id = ? AND rol = 'estudiante'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // CONTAR ESTUDIANTES
    // ==========================================
    public function contar($estado = 'activo') {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE rol = 'estudiante' AND estado = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $estado);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['total'];
    }
    
    // ==========================================
    // BUSCAR ESTUDIANTES
    // ==========================================
    public function buscar($termino) {
        $termino = '%' . $termino . '%';
        $sql = "SELECT id, nombre, email, rol, estado, created_at 
                FROM {$this->table} 
                WHERE rol = 'estudiante' 
                AND (nombre LIKE ? OR email LIKE ?) 
                ORDER BY id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $termino, $termino);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $estudiantes = [];
        while ($row = $result->fetch_assoc()) {
            $estudiantes[] = $row;
        }
        return $estudiantes;
    }
    
    // ==========================================
    // ESTADÍSTICAS DE ESTUDIANTES
    // ==========================================
    public function estadisticas() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos,
                    DATE(created_at) as fecha_registro
                FROM {$this->table} 
                WHERE rol = 'estudiante'
                GROUP BY DATE(created_at)
                ORDER BY fecha_registro DESC
                LIMIT 30";
        $result = $this->conn->query($sql);
        
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        return $stats;
    }
}
?>