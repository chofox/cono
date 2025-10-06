<?php
/**
 * Clase de Autenticación
 * Sistema de Conocimiento de Entrega de Insumos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class Auth {
    private $conn;
    private $database;
    
    public function __construct() {
        $this->database = new Database();
        $this->conn = $this->database->getConnection();
    }
    
    /**
     * Iniciar sesión de usuario
     */
    public function login($username, $password) {
        try {
            // Limpiar sesiones expiradas
            $this->cleanExpiredSessions();
            
            $query = "SELECT u.id, u.nombre_completo, u.usuario, u.password_hash, u.puesto, 
                             u.activo, r.nombre as rol, d.nombre as distrito
                      FROM usuarios u 
                      INNER JOIN roles r ON u.rol_id = r.id 
                      INNER JOIN distritos d ON u.distrito_id = d.id
                      WHERE u.usuario = :username AND u.activo = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                if (password_verify($password, $user['password_hash'])) {
                    // Crear sesión
                    $session_token = $this->createSession($user['id']);
                    
                    if ($session_token) {
                        // Establecer variables de sesión
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['nombre_completo'];
                        $_SESSION['username'] = $user['usuario'];
                        $_SESSION['user_role'] = $user['rol'];
                        $_SESSION['user_district'] = $user['distrito'];
                        $_SESSION['user_position'] = $user['puesto'];
                        error_log("Auth::login - User role set in session: " . $_SESSION['user_role']);
                        $_SESSION['session_token'] = $session_token;
                        $_SESSION['login_time'] = time();
                        
                        // Registrar login exitoso
                        $this->logActivity($user['id'], 'login_success', 'Inicio de sesión exitoso');
                        
                        return [
                            'success' => true,
                            'message' => 'Inicio de sesión exitoso',
                            'user' => $user
                        ];
                    }
                }
            }
            
            // Registrar intento fallido
            $this->logActivity(null, 'login_failed', "Intento de login fallido para usuario: $username");
            
            return [
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ];
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del sistema'
            ];
        }
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        try {
            if (isset($_SESSION['session_token'])) {
                // Marcar sesión como inactiva
                $query = "UPDATE sesiones_usuario SET activa = 0 WHERE token_sesion = :token";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':token', $_SESSION['session_token']);
                $stmt->execute();
            }
            
            if (isset($_SESSION['user_id'])) {
                $this->logActivity($_SESSION['user_id'], 'logout', 'Cierre de sesión');
            }
            
            // Destruir sesión
            session_destroy();
            
            return [
                'success' => true,
                'message' => 'Sesión cerrada correctamente'
            ];
            
        } catch (Exception $e) {
            error_log("Error en logout: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error cerrando sesión'
            ];
        }
    }
    
    /**
     * Verificar si la sesión es válida
     */
    public function verifySession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            error_log("verifySession: user_id o session_token no están seteados en SESSION.");
            return false;
        }
        
        error_log("verifySession: user_id: " . $_SESSION['user_id'] . ", session_token: " . $_SESSION['session_token']);
    
        try {
            $query = "SELECT id FROM sesiones_usuario 
                      WHERE token_sesion = :token 
                      AND usuario_id = :user_id 
                      AND activa = 1";
            
            error_log("verifySession: Query a ejecutar: " . $query);
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $_SESSION['session_token']);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            
             // Construct the query for logging purposes
             $log_query = str_replace(':token', "'" . $_SESSION['session_token'] . "'", $query);
             $log_query = str_replace(':user_id', $_SESSION['user_id'], $log_query);
             error_log("verifySession: Query a ejecutar: " . $log_query);

             error_log("verifySession: Ejecutando query con token: " . $_SESSION['session_token'] . " y user_id: " . $_SESSION['user_id']);
             
             $stmt->execute();
             
             error_log("verifySession: rowCount() después de ejecutar la query principal: " . $stmt->rowCount());

             if ($stmt->rowCount() == 1) {
                 error_log("verifySession: Sesión encontrada y válida para user_id: " . $_SESSION['user_id']);
                 // Actualizar tiempo de expiración
                 $this->extendSession($_SESSION['session_token']);
                 return true;
             }
             
             // Añadir logs para depuración si la sesión no se encuentra
             $check_query = "SELECT fecha_expiracion, activa, NOW() as db_now FROM sesiones_usuario 
                             WHERE token_sesion = :token AND usuario_id = :user_id";
             $check_stmt = $this->conn->prepare($check_query);
             $check_stmt->bindParam(':token', $_SESSION['session_token']);
             $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
             $check_stmt->execute();
             $session_data = $check_stmt->fetch();

             if ($session_data) {
                 error_log("verifySession: Datos de sesión en DB: Expiración=" . $session_data['fecha_expiracion'] . ", Activa=" . $session_data['activa'] . ", DB_NOW=" . $session_data['db_now'] . ", PHP_NOW=" . date('Y-m-d H:i:s'));
             } else {
                 error_log("verifySession: No se encontraron datos de sesión en DB para el token y user_id proporcionados.");
             }

             error_log("verifySession: Sesión no encontrada, inactiva o expirada para user_id: " . $_SESSION['user_id']);
             return false;
             
         } catch (Exception $e) {
             error_log("Error verificando sesión: " . $e->getMessage());
             return false;
         }
     }
    
    /**
     * Crear nueva sesión
     */
    private function createSession($user_id) {
        try {
            $token = bin2hex(random_bytes(32));
            $expiration = gmdate('Y-m-d H:i:s', time() + SESSION_LIFETIME);
            $current_time = gmdate('Y-m-d H:i:s');
            
            // Eliminar logs adicionales que ya no son necesarios
            // error_log("Creando sesión para usuario: " . $user_id . ", token: " . $token . ", expiración: " . $expiration);
            // error_log("Valor de fecha_expiracion antes de insertar en DB: " . $expiration);
            // error_log("Valor de fecha_inicio antes de insertar en DB: " . $current_time);
            
            $query = "INSERT INTO sesiones_usuario (usuario_id, token_sesion, fecha_inicio, fecha_expiracion, ip_address, user_agent, activa)
                      VALUES (:user_id, :token, :fecha_inicio, :expiration, :ip, :user_agent, 1)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':fecha_inicio', $current_time);
            $stmt->bindParam(':expiration', $expiration);
            $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
            
            if ($stmt->execute()) {
                error_log("Sesión creada exitosamente en DB para usuario: " . $user_id);
                return $token;
            }
            
            error_log("Fallo al crear sesión en DB para usuario: " . $user_id);
            return false;
            
        } catch (Exception $e) {
            error_log("Error creando sesión: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Extender sesión
     */
    private function extendSession($token) {
        try {
            $new_expiration = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
            
            $query = "UPDATE sesiones_usuario 
                      SET fecha_expiracion = :expiration 
                      WHERE token_sesion = :token";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':expiration', $new_expiration);
            $stmt->bindParam(':token', $token);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error extendiendo sesión: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpiar sesiones expiradas
     */
    private function cleanExpiredSessions() {
        try {
            $query = "DELETE FROM sesiones_usuario WHERE fecha_expiracion < NOW() OR activa = 0";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error limpiando sesiones: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cambiar contraseña
     */
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            // Verificar contraseña actual
            $query = "SELECT password_hash FROM usuarios WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                if (password_verify($current_password, $user['password_hash'])) {
                    // Actualizar contraseña
                    $new_hash = password_hash($new_password, HASH_ALGO);
                    
                    $update_query = "UPDATE usuarios SET password_hash = :new_hash WHERE id = :user_id";
                    $update_stmt = $this->conn->prepare($update_query);
                    $update_stmt->bindParam(':new_hash', $new_hash);
                    $update_stmt->bindParam(':user_id', $user_id);
                    
                    if ($update_stmt->execute()) {
                        $this->logActivity($user_id, 'password_change', 'Cambio de contraseña exitoso');
                        
                        return [
                            'success' => true,
                            'message' => 'Contraseña actualizada correctamente'
                        ];
                    }
                }
            }
            
            return [
                'success' => false,
                'message' => 'Contraseña actual incorrecta'
            ];
            
        } catch (Exception $e) {
            error_log("Error cambiando contraseña: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del sistema'
            ];
        }
    }
    
    /**
     * Registrar actividad
     */
    private function logActivity($user_id, $action, $details = '') {
        try {
            // Crear tabla de log si no existe
            $create_table = "CREATE TABLE IF NOT EXISTS log_actividades (
                id INT PRIMARY KEY AUTO_INCREMENT,
                usuario_id INT,
                accion VARCHAR(100) NOT NULL,
                detalles TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            )";
            $this->conn->exec($create_table);
            
            $query = "INSERT INTO log_actividades 
                      (usuario_id, accion, detalles, ip_address, user_agent) 
                      VALUES (:user_id, :action, :details, :ip, :user_agent)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error registrando actividad: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener información del usuario actual
     */
    public function getCurrentUser() {
        if (!$this->verifySession()) {
            return null;
        }
        
        try {
            $query = "SELECT u.id, u.nombre_completo, u.usuario, u.puesto, 
                             r.nombre as rol, d.nombre as distrito
                      FROM usuarios u 
                      INNER JOIN roles r ON u.rol_id = r.id 
                      INNER JOIN distritos d ON u.distrito_id = d.id
                      WHERE u.id = :user_id AND u.activo = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                return $stmt->fetch();
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Error obteniendo usuario actual: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verificar permisos de rol
     */
    public function hasPermission($required_role) {
        if (!$this->verifySession()) {
            return false;
        }
        
        $user_role = trim($_SESSION['user_role']);
        $required_role = trim($required_role);
        
        error_log("Auth::hasPermission - User role: '" . $user_role . "', Required role: '" . $required_role . "'");
        
        // El administrador tiene acceso a todo
        if (strcasecmp($user_role, 'Administrador') === 0) {
            return true;
        }
        
        return strcasecmp($user_role, $required_role) === 0;
    }

    /**
     * Requiere un rol específico para acceder a la página.
     * Redirige si el usuario no tiene el rol o no está logueado.
     */
    public function require_role($required_role) {
        if (!$this->verifySession()) {
            header("Location: login.php");
            exit();
        }

        error_log("Auth::require_role - Checking permission for role: '" . $required_role . "'");
        if (!$this->hasPermission($required_role)) {
            // Redirigir a una página de acceso denegado o al dashboard
            header("Location: dashboard.php?error=unauthorized"); // O a una página de error 403
            exit();
        }
    }
}
?>