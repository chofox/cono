<?php
require_once __DIR__ . '/error_config.php';

/**
 * Configuración de Base de Datos
 * Sistema de Conocimiento de Entrega de Insumos
 */

class Database {
    private $host = 'mysql.us.cloudlogin.co';
    private $db_name = 'chofoxr_informatica';
    private $username = 'chofoxr_informatica';
    private $password = 'o0zp66PUO@';
    private $charset = 'utf8mb4';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-06:00'", // Establecer la zona horaria de la conexión
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
        
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}

// Configuraciones adicionales
define('DB_HOST', 'localhost');
define('DB_NAME', 'chofoxr_informatica');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'Sistema de Conocimiento de Entrega de Insumos');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/cono');

// Configuración de sesiones
define('SESSION_LIFETIME', 3600); // 1 hora en segundos
define('SESSION_NAME', 'CONOCIMIENTO_SESSION');

// Configuración de archivos
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('PDF_PATH', __DIR__ . '/../pdfs/');
define('TCPDF_PATH', __DIR__ . '/../vendor/tcpdf/');

// Configuración de seguridad
define('HASH_ALGO', PASSWORD_DEFAULT);
define('CSRF_TOKEN_LENGTH', 32);

// Zona horaria
date_default_timezone_set('America/Guatemala');

// Configuración de errores (cambiar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>