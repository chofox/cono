<?php
/**
 * Funciones Utilitarias
 * Sistema de Conocimiento de Entrega de Insumos
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Función para sanitizar datos de entrada
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Función para validar email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Función para generar token CSRF
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Función para verificar token CSRF
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Función para verificar si el usuario está logueado
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Función para verificar rol de usuario
 */
function has_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }

    $user_role = $_SESSION['user_role'];

    // El administrador tiene acceso a todo
    if ($user_role === 'Administrador') {
        return true;
    }

    return $user_role === $required_role;
}

/**
 * Verifica si el usuario tiene alguno de los roles especificados.
 */
function has_any_role(array $roles): bool {
    if (!is_logged_in()) {
        return false;
    }

    $user_role = $_SESSION['user_role'];
    if ($user_role === 'Administrador') {
        return true;
    }

    return in_array($user_role, $roles, true);
}

/**
 * Requiere al menos uno de los roles indicados para acceder a un recurso.
 */
function require_any_role(array $roles): void {
    if (!has_any_role($roles)) {
        header('Location: dashboard.php?error=no_permission');
        exit();
    }
}

/**
 * Función para redirigir si no está autorizado
 */
function require_auth($required_role = null) {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
    
    if ($required_role && !has_role($required_role)) {
        header('Location: dashboard.php?error=no_permission');
        exit();
    }
}

/**
 * Función para formatear fecha
 */
function format_date($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Función para formatear fecha y hora
 */
function format_datetime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * Función para generar número de conocimiento
 */
function generate_conocimiento_number($year = null) {
    if (!$year) {
        $year = date('Y');
    }
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "SELECT COALESCE(MAX(numero_secuencial), 0) + 1 as next_number 
                  FROM conocimientos 
                  WHERE año = :year";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':year', $year);
        $stmt->execute();
        
        $result = $stmt->fetch();
        $next_number = $result['next_number'];
        
        return $next_number . '-' . $year;
        
    } catch (Exception $e) {
        error_log("Error generando número de conocimiento: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para registrar actividad del usuario
 */
function log_user_activity($user_id, $action, $details = '') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "INSERT INTO log_actividades (usuario_id, accion, detalles, ip_address, user_agent) 
                  VALUES (:user_id, :action, :details, :ip, :user_agent)";
        
        $stmt = $conn->prepare($query);
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
 * Función para mostrar mensajes flash
 */
function show_flash_message() {
    if (!isset($_SESSION['flash_message'])) {
        return;
    }

    $message = $_SESSION['flash_message'];
    $type = $_SESSION['flash_type'] ?? 'info';

    $classMap = [
        'success' => 'is-success',
        'danger' => 'is-danger',
        'warning' => 'is-warning',
        'info' => 'is-info',
    ];

    $bulmaClass = $classMap[$type] ?? 'is-info';

    echo "<div class='notification {$bulmaClass}'>" .
        "<button class='delete' onclick=\"this.parentElement.remove()\" aria-label='Cerrar notificación'></button>" .
        $message .
        "</div>";

    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

/**
 * Función para establecer mensaje flash
 */
function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Función para validar fecha
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Función para obtener lista de años disponibles
 */
function get_available_years() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "SELECT DISTINCT año FROM conocimientos ORDER BY año DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $years = [];
        while ($row = $stmt->fetch()) {
            $years[] = $row['año'];
        }
        
        // Agregar año actual si no existe
        $current_year = date('Y');
        if (!in_array($current_year, $years)) {
            array_unshift($years, $current_year);
        }
        
        return $years;
        
    } catch (Exception $e) {
        error_log("Error obteniendo años: " . $e->getMessage());
        return [date('Y')];
    }
}

/**
 * Función para limpiar sesiones expiradas
 */
function clean_expired_sessions() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "DELETE FROM sesiones_usuario WHERE fecha_expiracion < NOW()";
        $stmt = $conn->prepare($query);
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error limpiando sesiones: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para crear directorio si no existe
 */
function create_directory_if_not_exists($path) {
    if (!is_dir($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

/**
 * Función para obtener extensión de archivo
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Función para generar nombre único de archivo
 */
function generate_unique_filename($original_name) {
    $extension = get_file_extension($original_name);
    $name = pathinfo($original_name, PATHINFO_FILENAME);
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    
    return sanitize_input($name) . '_' . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * Función para convertir bytes a formato legible
 */
function format_bytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Función para validar número de teléfono guatemalteco
 */
function validate_guatemala_phone($phone) {
    // Formato: +502 XXXX-XXXX o XXXX-XXXX
    $pattern = '/^(\+502\s?)?[2-7]\d{3}-?\d{4}$/';
    return preg_match($pattern, $phone);
}

/**
 * Función para escapar salida HTML
 */
function escape_html($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Función para truncar texto
 */
function truncate_text($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Función para registrar mensajes de depuración
 */
function debug_log($message, $level = 'INFO') {
    error_log("[DEBUG] [{$level}] " . $message);
}

/**
 * Función para obtener todos los insumos
 */
function get_all_insumos() {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $query = "SELECT id, nombre, unidad_medida FROM insumos ORDER BY nombre";
        $stmt = $conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo todos los insumos: " . $e->getMessage());
        return [];
    }
}

/**
 * Función para obtener todos los lugares de entrega
 */
// function get_all_lugares_entrega() {
//     $database = new Database();
//     $conn = $database->getConnection();
//     $query = "SELECT id, nombre FROM lugares_entrega WHERE activo = 1 ORDER BY nombre ASC";
//     $stmt = $conn->prepare($query);
//     $stmt->execute();
//     return $stmt->fetchAll(PDO::FETCH_ASSOC);
// }

function get_all_receptores() {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT id, nombre_completo FROM receptores WHERE activo = 1 ORDER BY nombre_completo";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene usuarios por rol del sistema principal.
 */
function get_users_by_role(string $role): array {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $query = "SELECT u.id, u.nombre_completo
                  FROM usuarios u
                  INNER JOIN roles r ON u.rol_id = r.id
                  WHERE r.nombre = :rol AND u.activo = 1
                  ORDER BY u.nombre_completo";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':rol', $role);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error obteniendo usuarios por rol: ' . $e->getMessage());
        return [];
    }
}

function mantenimiento_estados(): array {
    return [
        'en_recepcion' => 'En recepción',
        'en_diagnostico' => 'En diagnóstico',
        'en_mantenimiento' => 'En mantenimiento',
        'listo_para_entrega' => 'Listo para entrega',
        'entregado' => 'Entregado',
        'cerrado' => 'Cerrado',
    ];
}

function mantenimiento_estado_label(string $estado): string {
    $estados = mantenimiento_estados();
    return $estados[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}

function mantenimiento_estado_badge_class(string $estado): string {
    return match ($estado) {
        'en_recepcion' => 'secondary',
        'en_diagnostico' => 'info',
        'en_mantenimiento' => 'warning',
        'listo_para_entrega' => 'primary',
        'entregado' => 'success',
        'cerrado' => 'dark',
        default => 'light',
    };
}

function mantenimiento_tipo_options(): array {
    return [
        'preventivo' => 'Preventivo',
        'correctivo' => 'Correctivo',
    ];
}

function generar_url_estado_publico(string $token): string {
    return APP_URL . '/modules/mantenimiento/pages/estado.php?token=' . urlencode($token);
}

function generar_qr_url(string $token): string {
    $url = generar_url_estado_publico($token);
    return 'https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=' . urlencode($url);
}

?>