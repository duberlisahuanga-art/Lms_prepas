<?php
/**
 * AUTH CONTROLLER - DEVIOZ ACADEMY
 */

// Permitir CORS para pruebas locales
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

function enviarError($msg) { 
    ob_end_clean(); 
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $msg]); 
    exit; 
}

function enviarExito($data) { 
    ob_end_clean(); 
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => $data['msg'] ?? 'OK', 'usuario' => $data['usuario'] ?? null]); 
    exit; 
}

try {
    require_once __DIR__ . '/../config/conexion.php';
    $conn = Conexion::getConexion();
    
    // LEER LA ACCIÓN CORRECTAMENTE
    $accion = '';
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];
    } elseif (isset($_GET['accion'])) {
        $accion = $_GET['accion'];
    }
    
    // DEBUG: Mostrar qué acción se recibió (quitar después)
    // echo json_encode(['debug_accion' => $accion, 'post' => $_POST]); exit;
    
    switch ($accion) {
        
        case 'login':
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                enviarError('Completa todos los campos');
            }
            
            $sql = "SELECT id, nombre, email, password, rol, estado FROM usuarios WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $usuario = $result->fetch_assoc();
                
                if ($usuario['estado'] !== 'activo') {
                    enviarError('Cuenta inactiva');
                }
                
                if (password_verify($password, $usuario['password'])) {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];
                    $_SESSION['usuario_email'] = $usuario['email'];
                    $_SESSION['usuario_rol'] = $usuario['rol'];
                    
                    enviarExito([
                        'msg' => 'Bienvenido, ' . $usuario['nombre'],
                        'usuario' => [
                            'id' => $usuario['id'],
                            'nombre' => $usuario['nombre'],
                            'email' => $usuario['email'],
                            'rol' => $usuario['rol']
                        ]
                    ]);
                } else {
                    enviarError('Contraseña incorrecta');
                }
            } else {
                enviarError('Correo no registrado');
            }
            break;

        case 'registrar':
            $nombre = trim($_POST['nombre'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $confirmarPassword = $_POST['confirmarPassword'] ?? '';
            
            if (empty($nombre) || empty($email) || empty($password)) {
                enviarError('Completa todos los campos');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                enviarError('Correo inválido');
            }
            if (strlen($password) < 6) {
                enviarError('La contraseña debe tener al menos 6 caracteres');
            }
            if ($password !== $confirmarPassword) {
                enviarError('Las contraseñas no coinciden');
            }
            
            $sql = "SELECT id FROM usuarios WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                enviarError('Este correo ya está registrado');
            }
            
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, 'estudiante', 'activo')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $nombre, $email, $hash);
            
            if ($stmt->execute()) {
                enviarExito([
                    'msg' => 'Registro exitoso. Ya puedes iniciar sesión.',
                    'usuario' => [
                        'id' => $conn->insert_id,
                        'nombre' => $nombre,
                        'email' => $email,
                        'rol' => 'estudiante'
                    ]
                ]);
            } else {
                enviarError('Error al crear la cuenta');
            }
            break;

        case 'me':
            if (isset($_SESSION['usuario_id'])) {
                echo json_encode([
                    'ok' => true,
                    'usuario' => [
                        'id' => $_SESSION['usuario_id'],
                        'nombre' => $_SESSION['usuario_nombre'],
                        'email' => $_SESSION['usuario_email'] ?? '',
                        'rol' => $_SESSION['usuario_rol']
                    ]
                ]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'No hay sesión activa']);
            }
            exit;
            break;

        case 'logout':
            session_unset();
            session_destroy();
            enviarExito(['msg' => 'Sesión cerrada']);
            break;

        case 'actualizar_perfil':
            if (!isset($_SESSION['usuario_id'])) {
                enviarError('Debes iniciar sesión');
            }
            
            $nombre = trim($_POST['nombre'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($nombre)) {
                enviarError('El nombre es obligatorio');
            }
            
            try {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        enviarError('La contraseña debe tener al menos 6 caracteres');
                    }
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $sql = "UPDATE usuarios SET nombre = ?, password = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ssi', $nombre, $hash, $_SESSION['usuario_id']);
                } else {
                    $sql = "UPDATE usuarios SET nombre = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('si', $nombre, $_SESSION['usuario_id']);
                }
                
                if ($stmt->execute()) {
                    $_SESSION['usuario_nombre'] = $nombre;
                    enviarExito(['msg' => 'Perfil actualizado']);
                } else {
                    enviarError('Error al actualizar');
                }
            } catch (Exception $e) {
                enviarError('Error del servidor');
            }
            break;

        default:
            // DEBUG: Mostrar qué se recibió
            enviarError('Acción no válida: "' . $accion . '" | POST: ' . json_encode($_POST));
            break;
    }
    
} catch (Exception $e) {
    enviarError('Error: ' . $e->getMessage());
} catch (Error $e) {
    enviarError('Error fatal: ' . $e->getMessage());
}
?>