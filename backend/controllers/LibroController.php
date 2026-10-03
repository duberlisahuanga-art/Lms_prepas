<?php
/**
 * LIBRO CONTROLLER - Gestión de libros (Biblioteca INTEGRADA)
 * ✅ MEJORADO: validación robusta de archivos, creación automática de carpetas,
 *              sanitización, mejor manejo de errores y búsqueda para estudiantes
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

function enviarError($msg) { 
    ob_end_clean(); 
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE); 
    exit; 
}
function enviarExito($data) { 
    ob_end_clean(); 
    echo json_encode(['ok' => true, 'msg' => $data['msg'] ?? 'OK', 'data' => $data['data'] ?? null], JSON_UNESCAPED_UNICODE); 
    exit; 
}

try {
    require_once __DIR__ . '/../config/conexion.php';
    $conn = Conexion::getConexion();
    
    $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
    $esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
    
    // ===== UTILIDAD: Validar y subir archivo =====
    function subirArchivo($file, $subcarpeta, $tiposPermitidos, $maxSize = 10485760) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'msg' => 'Error al subir el archivo'];
        }
        
        // Validar tamaño
        if ($file['size'] > $maxSize) {
            return ['ok' => false, 'msg' => 'El archivo supera el tamaño máximo permitido (' . ($maxSize / 1048576) . ' MB)'];
        }
        
        // Validar tipo MIME real (no solo extensión)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $tiposPermitidos, true)) {
            return ['ok' => false, 'msg' => 'Tipo de archivo no permitido. MIME detectado: ' . $mime];
        }
        
        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extensionesValidas = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
        if (!in_array($ext, $extensionesValidas, true)) {
            return ['ok' => false, 'msg' => 'Extensión de archivo no válida'];
        }
        
        // Crear carpetas si no existen
        $upload_dir = __DIR__ . '/../../uploads/libros/' . $subcarpeta . '/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return ['ok' => false, 'msg' => 'No se pudo crear la carpeta de uploads'];
            }
        }
        
        // Sanitizar nombre y agregar timestamp
        $nombreBase = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
        $nombreArchivo = $subcarpeta . '/' . time() . '_' . substr($nombreBase, 0, 50) . '.' . $ext;
        $rutaCompleta = __DIR__ . '/../../uploads/libros/' . $nombreArchivo;
        
        if (!move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
            return ['ok' => false, 'msg' => 'Error al mover el archivo subido'];
        }
        
        return ['ok' => true, 'ruta' => $nombreArchivo];
    }
    
    switch ($accion) {
        
        // ===== LISTAR LIBROS (CON JOINS) =====
        case 'listar':
            $sql = "SELECT l.id, l.titulo, l.autor, l.descripcion, l.portada, l.pdf_url, l.estado,
                           l.categoria_id, l.nivel_id,
                           cat.nombre as categoria_nombre,
                           niv.nombre as nivel_nombre
                    FROM libros l
                    LEFT JOIN categorias cat ON l.categoria_id = cat.id
                    LEFT JOIN niveles niv ON l.nivel_id = niv.id
                    WHERE l.estado = 'activo' 
                    ORDER BY l.id DESC";
            $result = $conn->query($sql);
            if (!$result) enviarError('Error al consultar: ' . $conn->error);
            
            $libros = [];
            while ($row = $result->fetch_assoc()) {
                $libros[] = $row;
            }
            enviarExito(['data' => $libros]);
            break;

        // ===== OBTENER LIBRO POR ID =====
        case 'obtener':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');
            
            $sql = "SELECT l.*, cat.nombre as categoria_nombre, niv.nombre as nivel_nombre 
                    FROM libros l 
                    LEFT JOIN categorias cat ON l.categoria_id = cat.id 
                    LEFT JOIN niveles niv ON l.nivel_id = niv.id 
                    WHERE l.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $libro = $stmt->get_result()->fetch_assoc();
            
            if (!$libro) enviarError('Libro no encontrado');
            enviarExito(['data' => $libro]);
            break;

        // ===== 🆕 BUSCAR LIBROS (para estudiantes) =====
        case 'buscar':
            $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
            if (mb_strlen($q) < 2) enviarError('Escribe al menos 2 caracteres para buscar');
            
            // Limpiar y tokenizar
            $q = preg_replace('/\s+/', ' ', trim($q));
            $tokens = array_filter(explode(' ', $q), function($t) { return mb_strlen($t) >= 3; });
            if (empty($tokens)) $tokens = [$q];
            
            $conds = [];
            $tipos = '';
            $vals = [];
            foreach (array_slice($tokens, 0, 4) as $t) {
                $like = '%' . $t . '%';
                $conds[] = '(l.titulo LIKE ? OR l.autor LIKE ? OR l.descripcion LIKE ? OR cat.nombre LIKE ?)';
                $tipos .= 'ssss';
                array_push($vals, $like, $like, $like, $like);
            }
            
            $sql = "SELECT l.id, l.titulo, l.autor, l.descripcion, l.portada, l.pdf_url,
                           cat.nombre as categoria_nombre, niv.nombre as nivel_nombre
                    FROM libros l
                    LEFT JOIN categorias cat ON l.categoria_id = cat.id
                    LEFT JOIN niveles niv ON l.nivel_id = niv.id
                    WHERE l.estado = 'activo'
                    AND (" . implode(' OR ', $conds) . ")
                    ORDER BY l.id DESC
                    LIMIT 20";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($tipos, ...$vals);
            $stmt->execute();
            $res = $stmt->get_result();
            $libros = [];
            while ($row = $res->fetch_assoc()) $libros[] = $row;
            
            enviarExito(['data' => $libros, 'total' => count($libros)]);
            break;

        // ===== CREAR LIBRO (solo admin) =====
        case 'crear':
            if (!$esAdmin) enviarError('Acceso denegado. Se requiere rol de administrador.');
            
            $titulo = trim($_POST['titulo'] ?? '');
            $autor = trim($_POST['autor'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $categoria_id = intval($_POST['categoria_id'] ?? 0);
            $nivel_id = intval($_POST['nivel_id'] ?? 0);
            
            if (empty($titulo) || empty($autor)) enviarError('Título y autor son obligatorios');
            if (mb_strlen($titulo) > 255) enviarError('El título no puede superar 255 caracteres');
            if (mb_strlen($autor) > 255) enviarError('El autor no puede superar 255 caracteres');
            
            // Validar que categoria_id y nivel_id existan (si se enviaron)
            if ($categoria_id > 0) {
                $chk = $conn->prepare("SELECT id FROM categorias WHERE id = ?");
                $chk->bind_param('i', $categoria_id);
                $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) enviarError('La categoría seleccionada no existe');
            }
            if ($nivel_id > 0) {
                $chk = $conn->prepare("SELECT id FROM niveles WHERE id = ?");
                $chk->bind_param('i', $nivel_id);
                $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) enviarError('El nivel seleccionado no existe');
            }
            
            // Subir portada (si viene)
            $portada = '';
            if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
                $res = subirArchivo($_FILES['portada'], 'portadas', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], 5242880); // 5MB
                if (!$res['ok']) enviarError($res['msg']);
                $portada = $res['ruta'];
            }
            
            // Subir PDF (si viene)
            $pdf_url = '';
            if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
                $res = subirArchivo($_FILES['pdf'], 'pdfs', ['application/pdf'], 104857600); // 100MB
                if (!$res['ok']) enviarError($res['msg']);
                $pdf_url = $res['ruta'];
            }
            
            if (empty($pdf_url)) enviarError('El archivo PDF es obligatorio');
            
            // INSERT
            $sql = "INSERT INTO libros (titulo, autor, categoria_id, nivel_id, descripcion, portada, pdf_url, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'activo')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssiisss', $titulo, $autor, $categoria_id, $nivel_id, $descripcion, $portada, $pdf_url);
            
            if ($stmt->execute()) {
                enviarExito(['msg' => '✅ Libro agregado correctamente', 'data' => ['id' => $conn->insert_id]]);
            } else {
                enviarError('Error al agregar el libro: ' . $conn->error);
            }
            break;

        // ===== ACTUALIZAR LIBRO (solo admin) =====
        case 'actualizar':
            if (!$esAdmin) enviarError('Acceso denegado. Se requiere rol de administrador.');
            
            $id = intval($_POST['id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $autor = trim($_POST['autor'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $categoria_id = intval($_POST['categoria_id'] ?? 0);
            $nivel_id = intval($_POST['nivel_id'] ?? 0);
            
            if ($id <= 0 || empty($titulo) || empty($autor)) enviarError('Datos inválidos: ID, título y autor son obligatorios');
            if (mb_strlen($titulo) > 255) enviarError('El título no puede superar 255 caracteres');
            if (mb_strlen($autor) > 255) enviarError('El autor no puede superar 255 caracteres');
            
            // Validar que el libro existe
            $chk = $conn->prepare("SELECT id FROM libros WHERE id = ?");
            $chk->bind_param('i', $id);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) enviarError('El libro no existe');
            
            // Validar FKs
            if ($categoria_id > 0) {
                $chk = $conn->prepare("SELECT id FROM categorias WHERE id = ?");
                $chk->bind_param('i', $categoria_id);
                $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) enviarError('La categoría seleccionada no existe');
            }
            if ($nivel_id > 0) {
                $chk = $conn->prepare("SELECT id FROM niveles WHERE id = ?");
                $chk->bind_param('i', $nivel_id);
                $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) enviarError('El nivel seleccionado no existe');
            }
            
            // UPDATE (no tocamos archivos, solo metadata)
            $sql = "UPDATE libros SET titulo = ?, autor = ?, categoria_id = ?, nivel_id = ?, descripcion = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssiisi', $titulo, $autor, $categoria_id, $nivel_id, $descripcion, $id);
            
            if ($stmt->execute()) {
                enviarExito(['msg' => '✅ Libro actualizado correctamente']);
            } else {
                enviarError('Error al actualizar: ' . $conn->error);
            }
            break;

        // ===== ELIMINAR LIBRO (solo admin) - Soft delete =====
        case 'eliminar':
            if (!$esAdmin) enviarError('Acceso denegado. Se requiere rol de administrador.');
            
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');
            
            // Verificar que existe
            $chk = $conn->prepare("SELECT id FROM libros WHERE id = ?");
            $chk->bind_param('i', $id);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) enviarError('El libro no existe');
            
            $sql = "UPDATE libros SET estado = 'inactivo' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                enviarExito(['msg' => '✅ Libro eliminado (movido a inactivos)']);
            } else {
                enviarError('Error al eliminar: ' . $conn->error);
            }
            break;

        // ===== CONTAR LIBROS =====
        case 'contar':
            $sql = "SELECT COUNT(*) as total FROM libros WHERE estado = 'activo'";
            $result = $conn->query($sql);
            if (!$result) enviarError('Error al contar: ' . $conn->error);
            $total = $result->fetch_assoc()['total'];
            enviarExito(['data' => ['total' => (int)$total]]);
            break;

        default:
            enviarError('Acción no válida. Acciones disponibles: listar, obtener, buscar, crear, actualizar, eliminar, contar');
            break;
    }
    
} catch (Exception $e) {
    enviarError('Error del servidor: ' . $e->getMessage());
}
?>