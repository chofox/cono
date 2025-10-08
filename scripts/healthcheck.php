<?php
require_once __DIR__ . '/../config/database.php';

$results = [];

function record_result(array &$results, string $label, bool $status, string $details = ''): void {
    $results[] = [
        'label' => $label,
        'status' => $status,
        'details' => $details,
    ];
}

record_result($results, 'Versión de PHP >= 7.4', version_compare(PHP_VERSION, '7.4', '>='), PHP_VERSION);

$required_extensions = ['pdo', 'pdo_mysql', 'mbstring'];
$missing_extensions = array_filter($required_extensions, static fn(string $ext): bool => !extension_loaded($ext));
record_result(
    $results,
    'Extensiones requeridas cargadas',
    empty($missing_extensions),
    empty($missing_extensions) ? 'OK' : 'Faltan: ' . implode(', ', $missing_extensions)
);

$database_ok = false;
$timezone = 'desconocida';
$session_table = 'no verificada';

try {
    $database = new Database();
    $connection = $database->getConnection();
    $database_ok = true;

    $timezone_stmt = $connection->query("SELECT @@session.time_zone AS tz");
    if ($timezone_stmt !== false) {
        $timezone_row = $timezone_stmt->fetch();
        if ($timezone_row && isset($timezone_row['tz'])) {
            $timezone = $timezone_row['tz'];
        }
    }

    $session_stmt = $connection->query("SHOW TABLES LIKE 'sesiones_usuario'");
    if ($session_stmt !== false) {
        $session_table = $session_stmt->rowCount() > 0 ? 'disponible' : 'no encontrada';
    }

    $database->closeConnection();
} catch (Throwable $exception) {
    record_result($results, 'Conexión a base de datos', false, $exception->getMessage());
}

if ($database_ok) {
    record_result($results, 'Conexión a base de datos', true, 'Conexión establecida correctamente');
    record_result($results, 'Zona horaria de sesión', true, $timezone);
    record_result($results, 'Tabla de sesiones', $session_table === 'disponible', $session_table);
}

$all_ok = true;
foreach ($results as $result) {
    $status_icon = $result['status'] ? '[OK]' : '[FALLO]';
    printf("%s %s - %s\n", $status_icon, $result['label'], $result['details']);
    if (!$result['status']) {
        $all_ok = false;
    }
}

exit($all_ok ? 0 : 1);
