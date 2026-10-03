<?php
/**
 * MODELO CURSO - DEVIOZ ACADEMY
 * Gestión de cursos
 */

require_once __DIR__ . '/../config/conexion.php';

class Curso {
    
    private $conn;
    private $table = 'cursos';
    
    // Propiedades
    public $id;
    public $nombre;
    public $descripcion;
    public $materia_id;
    public $imagen;
    public $estado;
    public $created_at;
    
    public function __construct() {
        $this->conn = Conexion::getConexion();
    }
    
    // ==========================================
    // CREAR CURSO
    // ==========================================
    public function crear() {
        $sql = "INSERT INTO {$this->table} (nombre, descripcion, materia_id, imagen, estado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssiss', $this->nombre, $this->descripcion, $this->materia_id, $this->imagen, $this->estado);
        
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
        $sql = "SELECT c.*, m.nombre as materia_nombre 
                FROM {$this->table} c 
                LEFT JOIN materias m ON c.materia_id = m.id 
                WHERE c.id = ?";
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
    // LISTAR CURSOS ACTIVOS
    // ==========================================
    public function listarActivos() {
        $sql = "SELECT c.*, m.nombre as materia_nombre 
                FROM {$this->table} c 
                LEFT JOIN materias m ON c.materia_id = m.id 
                WHERE c.estado = 'activo' 
                ORDER BY c.id DESC";
        $result = $this->conn->query($sql);
        
        $cursos = [];
        while ($row = $result->fetch_assoc()) {
            $cursos[] = $row;
        }
        return $cursos;
    }
    
    // ==========================================
    // LISTAR TODOS (incluye inactivos)
    // ==========================================
    public function listarTodos() {
        $sql = "SELECT c.*, m.nombre as materia_nombre 
                FROM {$this->table} c 
                LEFT JOIN materias m ON c.materia_id = m.id 
                ORDER BY c.id DESC";
        $result = $this->conn->query($sql);
        
        $cursos = [];
        while ($row = $result->fetch_assoc()) {
            $cursos[] = $row;
        }
        return $cursos;
    }
    
    // ==========================================
    // LISTAR POR MATERIA
    // ==========================================
    public function listarPorMateria($materia_id) {
        $sql = "SELECT * FROM {$this->table} WHERE materia_id = ? AND estado = 'activo' ORDER BY id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $materia_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cursos = [];
        while ($row = $result->fetch_assoc()) {
            $cursos[] = $row;
        }
        return $cursos;
    }
    
    // ==========================================
    // ACTUALIZAR
    // ==========================================
    public function actualizar() {
        $sql = "UPDATE {$this->table} SET nombre = ?, descripcion = ?, materia_id = ?, imagen = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssisi', $this->nombre, $this->descripcion, $this->materia_id, $this->imagen, $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // ACTUALIZAR ESTADO
    // ==========================================
    public function actualizarEstado($estado) {
        $sql = "UPDATE {$this->table} SET estado = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $estado, $this->id);
        return $stmt->execute();
    }
    
    // ==========================================
    // ELIMINAR (lógico - cambiar estado)
    // ==========================================
    public function eliminar() {
        return $this->actualizarEstado('inactivo');
    }
    
    // ==========================================
    // CONTAR CURSOS
    // ==========================================
    public function contar($estado = 'activo') {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE estado = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $estado);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['total'];
    }
    
    // ==========================================
    // BUSCAR CURSOS
    // ==========================================
    public function buscar($termino) {
        $termino = '%' . $termino . '%';
        $sql = "SELECT c.*, m.nombre as materia_nombre 
                FROM {$this->table} c 
                LEFT JOIN materias m ON c.materia_id = m.id 
                WHERE c.nombre LIKE ? OR c.descripcion LIKE ? 
                AND c.estado = 'activo'
                ORDER BY c.id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $termino, $termino);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cursos = [];
        while ($row = $result->fetch_assoc()) {
            $cursos[] = $row;
        }
        return $cursos;
    }
}
?>