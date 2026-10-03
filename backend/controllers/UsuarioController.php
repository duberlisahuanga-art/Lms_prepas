<?php
/**
 * USUARIO CONTROLLER - Devioz Academy
 * ✅ ORDEN: Público → Logueado → Admin
 * ✅ Foto de perfil: subir (validada por contenido), cambiar y ELIMINAR
 * ✅ PERFIL: actualizar nombre + correo sobre la cuenta actual (UPDATE, no INSERT)
 * ✅ SEGURIDAD: restablecer contraseña con sesión iniciada (sin pedir la anterior)
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

function enviarError($msg, $code = 400) {
    ob_end_clean();
    http_response_code($code);
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

    // BLINDAJE: POST con datos de registro sin acción → registrar
    $correoPost = $_POST['correo'] ?? $_POST['email'] ?? '';
    if (!isset($_SESSION['usuario_rol']) && !empty($_POST['nombre']) && !empty($correoPost) && !empty($_POST['password']) && empty($accion)) {
        $accion = 'registrar';
    }

    // ==========================================================
    // 1. LOGIN (público)
    // ==========================================================
    if ($accion === 'login') {
        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($correo) || empty($password)) enviarError('Correo y contraseña obligatorios.');

        $stmt = $conn->prepare("SELECT id, nombre, email, rol, password, estado FROM usuarios WHERE email = ?");
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        if (!$usuario || !password_verify($password, $usuario['password'])) enviarError('Credenciales incorrectas.');
        if ($usuario['estado'] !== 'activo') enviarError('Cuenta inactiva o bloqueada.');

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_rol'] = $usuario['rol'];

        enviarExito(['msg' => 'Inicio de sesión exitoso', 'data' => [
            'id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'correo' => $usuario['email'], 'rol' => $usuario['rol']
        ]]);
    }

    // ==========================================================
    // 2. REGISTRO PÚBLICO (con reactivación de cuentas inactivas)
    // ==========================================================
    if (in_array($accion, ['registrar', 'crear', 'registro'], true)) {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? $_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($nombre) || empty($correo) || empty($password)) enviarError('Nombre, correo y contraseña son obligatorios.');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) enviarError('Correo electrónico no válido.');
        if (strlen($password) < 6) enviarError('La contraseña debe tener al menos 6 caracteres.');

        $stmt = $conn->prepare("SELECT id, estado FROM usuarios WHERE email = ?");
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $existente = $stmt->get_result()->fetch_assoc();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($existente) {
            if (in_array(strtolower($existente['estado'] ?? ''), ['eliminado','inactivo','desactivado','0'], true)) {
                $upd = $conn->prepare("UPDATE usuarios SET nombre=?, password=?, rol='estudiante', estado='activo' WHERE id=?");
                $upd->bind_param('ssi', $nombre, $hash, $existente['id']);
                if ($upd->execute()) enviarExito(['msg' => '✅ Cuenta reactivada correctamente. Ya puedes iniciar sesión.']);
                enviarError('Error al reactivar la cuenta.');
            }
            enviarError('El correo electrónico ya está registrado.');
        }

        $ins = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, 'estudiante', 'activo')");
        $ins->bind_param('sss', $nombre, $correo, $hash);
        if ($ins->execute()) enviarExito(['msg' => '✅ Cuenta creada correctamente. Ya puedes iniciar sesión.']);
        enviarError('Error al crear la cuenta.');
    }

    // ==========================================================
    // BLOQUE LOGUEADO (estudiante o admin)
    // ==========================================================
    $logueado = isset($_SESSION['usuario_id']);

    // ----- MI PERFIL -----
    if ($accion === 'mi_perfil') {
        if (!$logueado) enviarError('Debes iniciar sesión.', 401);
        $stmt = $conn->prepare("SELECT id, nombre, email AS correo, rol, estado, foto, created_at AS fecha_registro FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) enviarError('Usuario no encontrado.');
        enviarExito(['data' => $row]);
    }

    // ----- SUBIR FOTO (validada por CONTENIDO, no por nombre) -----
    if ($accion === 'subir_foto') {
        if (!$logueado) enviarError('Debes iniciar sesión.', 401);
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) enviarError('No se recibió ninguna imagen.');

        $f = $_FILES['foto'];
        if ($f['size'] > 2 * 1024 * 1024) enviarError('La imagen no debe superar 2 MB.');

        $info = @getimagesize($f['tmp_name']);
        if (!$info || empty($info['mime'])) enviarError('El archivo no es una imagen válida.');

        $permitidos = [
            'image/jpeg'  => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png'   => 'png',
            'image/webp'  => 'webp',
            'image/gif'   => 'gif',
        ];
        if (!isset($permitidos[$info['mime']])) {
            enviarError('Tipo no permitido. Usa JPG, PNG, WEBP o GIF. (Detectado: ' . $info['mime'] . ')');
        }
        $ext = $permitidos[$info['mime']];

        $dirUploads = __DIR__ . '/../../uploads/';
        if (!is_dir($dirUploads)) @mkdir($dirUploads, 0755, true);

        $nombreArchivo = 'perfil_' . intval($_SESSION['usuario_id']) . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dirUploads . $nombreArchivo)) enviarError('No se pudo guardar la imagen en el servidor.');

        // Borrar foto anterior si existía
        $stmtOld = $conn->prepare("SELECT foto FROM usuarios WHERE id = ?");
        $stmtOld->bind_param('i', $_SESSION['usuario_id']);
        $stmtOld->execute();
        $old = $stmtOld->get_result()->fetch_assoc();
        if (!empty($old['foto'])) {
            $oldPath = $dirUploads . basename($old['foto']);
            if (is_file($oldPath)) @unlink($oldPath);
        }

        $rutaWeb = '/lms_prepa/uploads/' . $nombreArchivo;
        $upd = $conn->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
        $upd->bind_param('si', $rutaWeb, $_SESSION['usuario_id']);
        if ($upd->execute()) enviarExito(['msg' => '✅ Foto de perfil actualizada', 'data' => ['foto' => $rutaWeb]]);
        enviarError('Error al guardar la foto en la base de datos.');
    }

    // ----- ELIMINAR FOTO (borra archivo y deja foto = NULL) -----
    if ($accion === 'eliminar_foto') {
        if (!$logueado) enviarError('Debes iniciar sesión.', 401);

        $stmt = $conn->prepare("SELECT foto FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || empty($row['foto'])) enviarError('No tienes ninguna foto de perfil actualmente.');

        $fotoPath = __DIR__ . '/../../uploads/' . basename($row['foto']);
        if (is_file($fotoPath)) @unlink($fotoPath);

        $upd = $conn->prepare("UPDATE usuarios SET foto = NULL WHERE id = ?");
        $upd->bind_param('i', $_SESSION['usuario_id']);
        if ($upd->execute()) {
            enviarExito(['msg' => '✅ Foto de perfil eliminada', 'data' => ['foto' => null]]);
        }
        enviarError('No se pudo eliminar la foto.');
    }

    // ============================================================
    // 🆕 ACTUALIZAR MI PERFIL (nombre + correo) SOBRE LA CUENTA ACTUAL
    //    → UPDATE sobre $_SESSION['usuario_id'], NUNCA INSERT
    // ============================================================
    if ($accion === 'actualizar_mi_perfil') {
        if (!$logueado) enviarError('Debes iniciar sesión.', 401);
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? $_POST['email'] ?? '');

        if (empty($nombre) || strlen($nombre) < 2) enviarError('Nombre inválido.');

        // Si envía correo, validar formato y unicidad (que nadie más lo tenga)
        if (!empty($correo)) {
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) enviarError('Correo no válido.');
            $chk = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $chk->bind_param('si', $correo, $_SESSION['usuario_id']);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                enviarError('Ese correo ya pertenece a otra cuenta. Elige otro.');
            }
        }

        // UPDATE sobre la cuenta actual (no se crea ningún usuario nuevo)
        if (!empty($correo)) {
            $upd = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
            $upd->bind_param('ssi', $nombre, $correo, $_SESSION['usuario_id']);
        } else {
            $upd = $conn->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?");
            $upd->bind_param('si', $nombre, $_SESSION['usuario_id']);
        }

        if ($upd->execute()) {
            $_SESSION['usuario_nombre'] = $nombre;
            if (!empty($correo)) $_SESSION['usuario_email'] = $correo;
            enviarExito(['msg' => '✅ Datos actualizados en tu cuenta actual (no se creó ningún usuario nuevo).']);
        }
        enviarError('No se pudo actualizar el perfil.');
    }

    // ============================================================
    // 🆕 RESTABLECER CONTRASEÑA (con sesión iniciada, sin pedir la anterior)
    //    Porque la contraseña guardada es hash bcrypt: NO se puede leer/mostrar.
    //    → UPDATE del password sobre $_SESSION['usuario_id']
    // ============================================================
    if ($accion === 'restablecer_password') {
        if (!$logueado) enviarError('Debes iniciar sesión para restablecer tu contraseña.', 401);
        $nueva = $_POST['password_nueva'] ?? '';
        $conf  = $_POST['password_confirm'] ?? $nueva;

        if (empty($nueva)) enviarError('Escribe una contraseña nueva.');
        if (strlen($nueva) < 6) enviarError('La nueva contraseña debe tener al menos 6 caracteres.');
        if ($nueva !== $conf) enviarError('La confirmación no coincide con la contraseña nueva.');

        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hash, $_SESSION['usuario_id']);
        if ($upd->execute()) {
            enviarExito(['msg' => '✅ Contraseña restablecida en tu cuenta actual. Úsala en tu próximo inicio de sesión.']);
        }
        enviarError('No se pudo restablecer la contraseña.');
    }

    // ----- CAMBIAR CONTRASEÑA (tradicional: pide la anterior) -----
    if ($accion === 'cambiar_password') {
        if (!$logueado) enviarError('Debes iniciar sesión.', 401);
        $actual = $_POST['password_actual'] ?? '';
        $nueva = $_POST['password_nueva'] ?? '';
        if (empty($actual) || empty($nueva)) enviarError('Completa ambos campos.');
        if (strlen($nueva) < 6) enviarError('La nueva contraseña debe tener al menos 6 caracteres.');

        $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || !password_verify($actual, $row['password'])) enviarError('La contraseña actual es incorrecta.');

        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hash, $_SESSION['usuario_id']);
        if ($upd->execute()) enviarExito(['msg' => '✅ Contraseña cambiada correctamente']);
        enviarError('No se pudo cambiar la contraseña.');
    }

    // ----- ELIMINAR MI CUENTA (borrado físico) -----
    if ($accion === 'eliminar_mi_cuenta') {
        if (!$logueado) enviarError('Debes iniciar sesión para eliminar tu cuenta.', 401);
        $password = $_POST['password'] ?? '';
        if (empty($password)) enviarError('Escribe tu contraseña para confirmar la eliminación.');

        $stmt = $conn->prepare("SELECT password, rol, foto FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($password, $row['password'])) enviarError('Contraseña incorrecta. Tu cuenta NO fue eliminada.');
        if ($row['rol'] === 'admin') enviarError('Un administrador no puede eliminar su propia cuenta desde aquí.');

        if (!empty($row['foto'])) {
            $fotoPath = __DIR__ . '/../../uploads/' . basename($row['foto']);
            if (is_file($fotoPath)) @unlink($fotoPath);
        }

        $del = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $del->bind_param('i', $_SESSION['usuario_id']);
        if ($del->execute()) { session_destroy(); enviarExito(['msg' => '✅ Tu cuenta fue eliminada permanentemente del sistema.']); }
        enviarError('Error al eliminar la cuenta.');
    }

    // ==========================================================
    // 3. CANDADO ADMIN
    // ==========================================================
    if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin') {
        enviarError('Acceso denegado. Requiere rol de administrador.', 403);
    }

    // ==========================================================
    // 4. ACCIONES ADMIN
    // ==========================================================
    switch ($accion) {
        case 'listar':
            $result = $conn->query("SELECT id, nombre, email AS correo, rol, estado, foto, created_at AS fecha_registro FROM usuarios ORDER BY id DESC");
            $usuarios = [];
            while ($row = $result->fetch_assoc()) $usuarios[] = $row;
            enviarExito(['data' => $usuarios]);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');
            $stmt = $conn->prepare("SELECT id, nombre, email AS correo, rol, estado, foto, created_at AS fecha_registro FROM usuarios WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $usuario = $stmt->get_result()->fetch_assoc();
            if (!$usuario) enviarError('Usuario no encontrado');
            enviarExito(['data' => $usuario]);
            break;

        case 'actualizar':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $rol = $_POST['rol'] ?? 'estudiante';
            $estado = $_POST['estado'] ?? 'activo';
            if ($id <= 0 || empty($nombre)) enviarError('Datos inválidos');
            $upd = $conn->prepare("UPDATE usuarios SET nombre=?, rol=?, estado=? WHERE id=?");
            $upd->bind_param('sssi', $nombre, $rol, $estado, $id);
            if ($upd->execute()) enviarExito(['msg' => 'Usuario actualizado correctamente']);
            enviarError('Error al actualizar');
            break;

        case 'crear_admin':
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? $_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'estudiante';
            if (empty($nombre) || empty($correo) || empty($password)) enviarError('Faltan datos.');
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) enviarError('Correo no válido.');
            if (!in_array($rol, ['estudiante','admin'], true)) $rol = 'estudiante';

            $chk = $conn->prepare("SELECT id, estado FROM usuarios WHERE email = ?");
            $chk->bind_param('s', $correo);
            $chk->execute();
            $ex = $chk->get_result()->fetch_assoc();
            $hash = password_hash($password, PASSWORD_DEFAULT);

            if ($ex) {
                if (in_array(strtolower($ex['estado'] ?? ''), ['eliminado','inactivo','desactivado','0'], true)) {
                    $upd = $conn->prepare("UPDATE usuarios SET nombre=?, password=?, rol=?, estado='activo' WHERE id=?");
                    $upd->bind_param('sssi', $nombre, $hash, $rol, $ex['id']);
                    $upd->execute();
                    enviarExito(['msg' => '✅ Cuenta reactivada con rol ' . $rol]);
                }
                enviarError('El correo ya está registrado.');
            }
            $ins = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, ?, 'activo')");
            $ins->bind_param('ssss', $nombre, $correo, $hash, $rol);
            if ($ins->execute()) enviarExito(['msg' => '✅ Usuario creado correctamente']);
            enviarError('Error al crear el usuario.');
            break;

        case 'eliminar':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) enviarError('ID inválido');
            if ($id == $_SESSION['usuario_id']) enviarError('No puedes eliminar tu propia cuenta.');

            $stmt = $conn->prepare("SELECT foto FROM usuarios WHERE id = ? AND rol != 'admin'");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && !empty($row['foto'])) {
                $fotoPath = __DIR__ . '/../../uploads/' . basename($row['foto']);
                if (is_file($fotoPath)) @unlink($fotoPath);
            }

            $del = $conn->prepare("DELETE FROM usuarios WHERE id = ? AND rol != 'admin'");
            $del->bind_param('i', $id);
            if ($del->execute() && $del->affected_rows > 0) enviarExito(['msg' => '✅ Usuario eliminado. El correo queda libre para nuevo registro.']);
            enviarError('No se pudo eliminar (no existe o es admin).');
            break;

        case 'contar':
            $res = $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE estado = 'activo' AND rol = 'estudiante'");
            enviarExito(['data' => ['total' => (int)($res->fetch_assoc()['total'] ?? 0)]]);
            break;

        default:
            enviarError('Acción no válida.');
            break;
    }

} catch (Exception $e) {
    enviarError('Error interno: ' . $e->getMessage(), 500);
}
?>