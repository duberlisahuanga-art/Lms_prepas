<?php
/**
 * CONFIGURACIÓN DE CORREO - DEVIOZ ACADEMY
 * Configuración de PHPMailer para envío de emails
 * Se mantiene por si se necesita en el futuro (notificaciones, recuperación, etc.)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar PHPMailer desde vendor
require_once __DIR__ . '/../../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';

class Mailer {
    
    // ==========================================
    // CONFIGURACIÓN DEL SERVIDOR SMTP
    // ==========================================
    private $host = 'smtp.gmail.com';      // Servidor SMTP (Gmail, Outlook, etc.)
    private $port = 587;                    // Puerto SMTP
    private $username = 'tu_correo@gmail.com';  // Cambia por tu correo
    private $password = 'tu_app_password';      // Cambia por tu contraseña de app
    private $from_email = 'tu_correo@gmail.com';
    private $from_name = 'Devioz Academy';
    private $debug = false;  // true para ver detalles en desarrollo
    
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        try {
            // Configuración del servidor
            $this->mail->isSMTP();
            $this->mail->Host = $this->host;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->username;
            $this->mail->Password = $this->password;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = $this->port;
            $this->mail->CharSet = 'UTF-8';
            
            // Remitente
            $this->mail->setFrom($this->from_email, $this->from_name);
            $this->mail->isHTML(true);
            
            // Debug (solo en desarrollo)
            if ($this->debug) {
                $this->mail->SMTPDebug = 2;
            }
            
        } catch (Exception $e) {
            error_log('Error al configurar Mailer: ' . $e->getMessage());
            throw new Exception('Error al configurar el servicio de correo');
        }
    }
    
    // ==========================================
    // ENVIAR CORREO GENÉRICO
    // ==========================================
    public function enviar($para, $asunto, $cuerpo, $cuerpoTexto = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($para);
            $this->mail->Subject = $asunto;
            $this->mail->Body = $cuerpo;
            $this->mail->AltBody = $cuerpoTexto ?: strip_tags($cuerpo);
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Error al enviar correo: ' . $e->getMessage());
            throw new Exception('No se pudo enviar el correo');
        }
    }
    
    // ==========================================
    // ENVIAR CÓDIGO DE VERIFICACIÓN (por si se reactiva)
    // ==========================================
    public function enviarCodigoVerificacion($para, $codigo, $tipo = 'registro') {
        $asunto = '';
        $cuerpo = '';
        
        switch ($tipo) {
            case 'registro':
                $asunto = 'Código de verificación - Devioz Academy';
                $cuerpo = "
                    <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto;'>
                        <h2 style='color: #1a5c4a;'>¡Bienvenido a Devioz Academy!</h2>
                        <p>Tu código de verificación es:</p>
                        <h1 style='color: #2d8b6e; font-size: 48px; letter-spacing: 5px;'>{$codigo}</h1>
                        <p>Este código expira en 10 minutos.</p>
                        <p style='color: #666; font-size: 12px;'>Si no solicitaste este código, ignora este correo.</p>
                    </div>
                ";
                break;
                
            case 'recuperar':
                $asunto = 'Recuperación de contraseña - Devioz Academy';
                $cuerpo = "
                    <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto;'>
                        <h2 style='color: #1a5c4a;'>Recuperación de contraseña</h2>
                        <p>Tu código de recuperación es:</p>
                        <h1 style='color: #2d8b6e; font-size: 48px; letter-spacing: 5px;'>{$codigo}</h1>
                        <p>Este código expira en 10 minutos.</p>
                        <p style='color: #666; font-size: 12px;'>Si no solicitaste esta recuperación, ignora este correo.</p>
                    </div>
                ";
                break;
        }
        
        return $this->enviar($para, $asunto, $cuerpo);
    }
    
    // ==========================================
    // ENVIAR BIENVENIDA (nuevo usuario registrado)
    // ==========================================
    public function enviarBienvenida($para, $nombre) {
        $asunto = '¡Bienvenido a Devioz Academy!';
        $cuerpo = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto;'>
                <h2 style='color: #1a5c4a;'>¡Hola {$nombre}!</h2>
                <p>Tu cuenta ha sido creada exitosamente en <strong>Devioz Academy</strong>.</p>
                <p>Ya puedes iniciar sesión y comenzar a explorar nuestros cursos, biblioteca y recursos.</p>
                <a href='http://localhost/lms_prepa/frontend/pages/index.html' 
                   style='background: #2d8b6e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin-top: 20px;'>
                   Iniciar Sesión
                </a>
                <p style='color: #666; font-size: 12px; margin-top: 30px;'>© Devioz Academy - Todos los derechos reservados</p>
            </div>
        ";
        
        return $this->enviar($para, $asunto, $cuerpo);
    }
}
?>