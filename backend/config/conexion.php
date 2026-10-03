<?php
/**
 * 🔗 CONEXIÓN A BASE DE DATOS - Devioz Academy
 * ✅ MySQLi con detección automática de puerto y errores detallados
 */

class Conexion {
    
    // ==========================================
    // CONFIGURACIÓN PRINCIPAL
    // ==========================================
    private static $host = 'localhost';
    private static $db_name = 'lms_prepa';
    private static $username = 'root';
    private static $password = '';
    private static $charset = 'utf8mb4';
    
    // Puertos a intentar (en orden de prioridad)
    private static $ports = [3306, 3307, 3308];
    
    private static $conn = null;
    
    // ==========================================
    // OBTENER CONEXIÓN (con auto-detección de puerto)
    // ==========================================
    public static function getConexion() {
        if (self::$conn === null) {
            $connected = false;
            $lastError = '';
            
            // Intentar conectar con cada puerto hasta que funcione
            foreach (self::$ports as $port) {
                try {
                    $conn = new mysqli(
                        self::$host,
                        self::$username,
                        self::$password,
                        self::$db_name,
                        $port
                    );
                    
                    if ($conn->connect_error) {
                        $lastError = $conn->connect_error;
                        continue; // Intentar con el siguiente puerto
                    }
                    
                    // Conexión exitosa
                    $conn->set_charset(self::$charset);
                    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
                    
                    self::$conn = $conn;
                    $connected = true;
                    break;
                    
                } catch (Exception $e) {
                    $lastError = $e->getMessage();
                    continue;
                }
            }
            
            // Si ningún puerto funcionó, mostrar error detallado
            if (!$connected) {
                $error = [
                    'ok' => false,
                    'msg' => 'No se pudo conectar a la base de datos',
                    'detalles' => [
                        'error' => $lastError,
                        'host' => self::$host,
                        'db' => self::$db_name,
                        'user' => self::$username,
                        'puertos_intentados' => self::$ports,
                        'solucion' => '1) Verifica que MySQL esté corriendo | 2) Crea la BD "lms_prepa" en phpMyAdmin | 3) Revisa el puerto en my.ini'
                    ]
                ];
                
                error_log('DB Connection Error: ' . json_encode($error));
                
                // En modo desarrollo, mostrar el error; en producción, solo el mensaje genérico
                if (isset($_GET['debug']) || getenv('APP_ENV') === 'development') {
                    http_response_code(500);
                    echo json_encode($error, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } else {
                    http_response_code(500);
                    echo json_encode(['ok' => false, 'msg' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
                }
                exit;
            }
        }
        
        return self::$conn;
    }
    
    // ==========================================
    // CERRAR CONEXIÓN
    // ==========================================
    public static function cerrarConexion() {
        if (self::$conn !== null) {
            self::$conn->close();
            self::$conn = null;
        }
    }
    
    // ==========================================
    // VERIFICAR CONEXIÓN
    // ==========================================
    public static function estaConectado() {
        return self::$conn !== null && @self::$conn->ping();
    }
    
    // Destructor
    public function __destruct() {
        self::cerrarConexion();
    }
}
?>