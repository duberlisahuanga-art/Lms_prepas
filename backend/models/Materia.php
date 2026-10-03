<?php
/**
 * MODELO MATERIA - DEVIOZ ACADEMY
 * Gestión de materias
 */

require_once __DIR__ . '/../config/conexion.php';

class Materia {
    
    private $conn;
    private $table = 'materias';
    
    // Propiedades
    public $id;
    public $nombre;
    public $descripcion;
    public $icono;
    public $estado;
    public $created_at;
    
    public function __construct() {
        $this->conn = Conexion::getConexion();
    }
    
    // ==========================================
    // CREAR MATERIA
    // ==========================================
    public function crear() {
        $sql = "INSERT INTO {$this->table} (nombre, descripcion, icono, estado) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssss', $this->nombre, $this->descripcion, $this->icono, $this->estado);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            return true;
        }
        return false;
    }
    
    // ==========================================
    // OBTENER POR ID
    // ==========================================
    public function obtenerPorId() {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
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
    // LISTAR MATERIAS ACTIVAS
    // ==========================================
    public function listarActivas() {
        $sql = "SELECT * FROM {$this->table} WHERE estado = 'activo' ORDER BY nombre ASC";
        $result = $this->conn->query($sql);
        
        $materias = [];
        while ($row = $result->fetch_assoc()) {
            $materias[] = $row;
        }
        return $materias;
    }
    
    // ==========================================
    // LISTAR TODAS
    // ==========================================
    public function listarTodas() {
        $sql = "SELECT * FROM {$this->table} ORDER BY nombre ASC";
        $result = $this->conn->query($sql);
        
        $materias = [];
        while ($row = $result->fetch_assoc()) {
            $materias[] = $row;
        }
        return $materias;
    }
    
    // ==========================================
    // ACTUALIZAR
    // ==========================================
    public function actualizar() {
        $sql = "UPDATE {$this->table} SET nombre = ?, descripcion = ?, icono = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sssi', $this->nombre, $this->descripcion, $this->icono, $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // ELIMINAR (lógico)
    // ==========================================
    public function eliminar() {
        $sql = "UPDATE {$this->table} SET estado = 'inactivo' WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // VERIFICAR SI TIENE CURSOS ASOCIADOS
    // ==========================================
    public function tieneCursos() {
        $sql = "SELECT COUNT(*) as total FROM cursos WHERE materia_id = ? AND estado = 'activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['total'] > 0;
    }
    
    // ==========================================
    // CONTAR MATERIAS
    // ==========================================
    public function contar($estado = 'activo') {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE estado = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $estado);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['total'];
    }
}
?>