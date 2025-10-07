<?php
/**
 * Exportar Reportes a Excel
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../classes/Auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Verificar autenticación
require_auth();

$auth = new Auth();
$current_user = $auth->getCurrentUser();

// Verificar permisos
if (!in_array($current_user['rol'], ['Administrador', 'RRHH'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener filtros
    $filtros = [
        'anio' => isset($_GET['anio']) && !empty($_GET['anio']) ? (int)$_GET['anio'] : null,
        'mes' => isset($_GET['mes']) && !empty($_GET['mes']) ? (int)$_GET['mes'] : null,
        'distrito_id' => isset($_GET['distrito_id']) && !empty($_GET['distrito_id']) ? (int)$_GET['distrito_id'] : null,
        'entregante_id' => isset($_GET['entregante_id']) && !empty($_GET['entregante_id']) ? (int)$_GET['entregante_id'] : null,
        'receptor_id' => isset($_GET['receptor_id']) && !empty($_GET['receptor_id']) ? (int)$_GET['receptor_id'] : null,
        'insumo_id' => isset($_GET['insumo_id']) && !empty($_GET['insumo_id']) ? (int)$_GET['insumo_id'] : null,
        'buscar' => isset($_GET['buscar']) && !empty($_GET['buscar']) ? trim($_GET['buscar']) : null
    ];
    
    // Construir consulta base
    $query = "SELECT c.id,
                     c.numero_conocimiento,
                     DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') as fecha_entrega,
                     c.lugar_entrega,
                     CONCAT(entregante.nombre, ' ', entregante.apellido) as entregante,
                     entregante.puesto as entregante_puesto,
                     distrito_entregante.nombre as entregante_distrito,
                     CONCAT(receptor.nombre, ' ', receptor.apellido) as receptor,
                     receptor.puesto as receptor_puesto,
                     distrito_receptor.nombre as receptor_distrito,
                     c.observaciones_generales as observaciones,
                     DATE_FORMAT(c.creado_en, '%d/%m/%Y %H:%i') as fecha_creacion,
                     CONCAT(creador.nombre, ' ', creador.apellido) as creado_por,
                     GROUP_CONCAT(
                         CONCAT(i.nombre, ' (', ci.cantidad, ' ', i.unidad_medida, ')')
                         ORDER BY i.nombre SEPARATOR '; '
                     ) as insumos_detalle,
                     COUNT(ci.id) as total_insumos
              FROM conocimientos c
              INNER JOIN usuarios entregante ON c.entregante_id = entregante.id
              INNER JOIN receptores receptor ON c.receptor_id = receptor.id
              INNER JOIN usuarios creador ON c.creado_por = creador.id
              INNER JOIN distritos distrito_entregante ON entregante.distrito_id = distrito_entregante.id
              INNER JOIN distritos distrito_receptor ON receptor.distrito_id = distrito_receptor.id
              LEFT JOIN detalle_conocimientos ci ON c.id = ci.conocimiento_id
              LEFT JOIN insumos i ON ci.insumo_id = i.id
              WHERE 1=1";
    
    $params = [];
    
    // Aplicar filtros
    if ($filtros['anio']) {
        $query .= " AND YEAR(c.fecha_entrega) = :anio";
        $params[':anio'] = $filtros['anio'];
    }
    
    if ($filtros['mes']) {
        $query .= " AND MONTH(c.fecha_entrega) = :mes";
        $params[':mes'] = $filtros['mes'];
    }
    
    if ($filtros['distrito_id']) {
        $query .= " AND (entregante.distrito_id = :distrito_id OR receptor.distrito_id = :distrito_id)";
        $params[':distrito_id'] = $filtros['distrito_id'];
    }
    
    if ($filtros['entregante_id']) {
        $query .= " AND c.entregante_id = :entregante_id";
        $params[':entregante_id'] = $filtros['entregante_id'];
    }
    
    if ($filtros['receptor_id']) {
        $query .= " AND c.receptor_id = :receptor_id";
        $params[':receptor_id'] = $filtros['receptor_id'];
    }
    
    if ($filtros['insumo_id']) {
        $query .= " AND ci.insumo_id = :insumo_id";
        $params[':insumo_id'] = $filtros['insumo_id'];
    }
    
    if ($filtros['buscar']) {
        $query .= " AND (c.numero_conocimiento LIKE :buscar 
                        OR c.lugar_entrega LIKE :buscar
                        OR CONCAT(entregante.nombre, ' ', entregante.apellido) LIKE :buscar
                        OR CONCAT(receptor.nombre, ' ', receptor.apellido) LIKE :buscar
                        OR i.nombre LIKE :buscar
                        OR c.observaciones_generales LIKE :buscar)";
        $params[':buscar'] = '%' . $filtros['buscar'] . '%';
    }
    
    // Restricciones por rol
    if ($current_user['rol'] == 'RRHH') {
        $query .= " AND (entregante.distrito_id = :user_distrito OR receptor.distrito_id = :user_distrito)";
        $params[':user_distrito'] = $current_user['distrito_id'];
    }
    
    $query .= " GROUP BY c.id ORDER BY c.fecha_entrega DESC, c.numero_conocimiento DESC";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $conocimientos = $stmt->fetchAll();
    
    // Generar nombre del archivo
    $fecha_actual = date('Y-m-d_H-i-s');
    $nombre_archivo = "reporte_conocimientos_{$fecha_actual}.csv";
    
    // Configurar headers para descarga
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // Crear archivo CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    $headers = [
        'ID',
        'Número de Conocimiento',
        'Fecha de Entrega',
        'Lugar de Entrega',
        'Entregante',
        'Puesto Entregante',
        'Distrito Entregante',
        'Receptor',
        'Puesto Receptor',
        'Distrito Receptor',
        'Total Insumos',
        'Detalle de Insumos',
        'Observaciones',
        'Fecha de Creación',
        'Creado por'
    ];
    
    fputcsv($output, $headers, ',', '"');
    
    // Datos
    foreach ($conocimientos as $conocimiento) {
        $row = [
            $conocimiento['id'],
            $conocimiento['numero_conocimiento'],
            $conocimiento['fecha_entrega'],
            $conocimiento['lugar_entrega'],
            $conocimiento['entregante'],
            $conocimiento['entregante_puesto'],
            $conocimiento['entregante_distrito'],
            $conocimiento['receptor'],
            $conocimiento['receptor_puesto'],
            $conocimiento['receptor_distrito'],
            $conocimiento['total_insumos'],
            $conocimiento['insumos_detalle'] ?: 'Sin insumos',
            $conocimiento['observaciones'] ?: '',
            $conocimiento['fecha_creacion'],
            $conocimiento['creado_por']
        ];
        
        fputcsv($output, $row, ',', '"');
    }
    
    // Agregar información de filtros aplicados
    fputcsv($output, [], ',', '"'); // Línea vacía
    fputcsv($output, ['FILTROS APLICADOS:'], ',', '"');
    
    if ($filtros['anio']) {
        fputcsv($output, ['Año:', $filtros['anio']], ',', '"');
    }
    if ($filtros['mes']) {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        fputcsv($output, ['Mes:', $meses[$filtros['mes']]], ',', '"');
    }
    if ($filtros['buscar']) {
        fputcsv($output, ['Búsqueda:', $filtros['buscar']], ',', '"');
    }
    
    fputcsv($output, [], ',', '"'); // Línea vacía
    fputcsv($output, ['RESUMEN:'], ',', '"');
    fputcsv($output, ['Total de registros:', count($conocimientos)], ',', '"');
    fputcsv($output, ['Fecha de exportación:', date('d/m/Y H:i:s')], ',', '"');
    fputcsv($output, ['Exportado por:', $current_user['nombre'] . ' ' . $current_user['apellido']], ',', '"');
    
    fclose($output);
    
    // Registrar actividad
    log_user_activity($current_user['id'], 'exportar_excel', 'Exportó reporte de conocimientos a Excel');
    
} catch (Exception $e) {
    error_log("Error exportando a Excel: " . $e->getMessage());
    
    // Redirigir con error
    header('Location: ' . APP_URL . '/modules/conocimientos/pages/reportes.php?error=' . urlencode('Error al exportar el reporte'));
    exit();
}
?>
c.numero_conocimiento, 
c.fecha_entrega, 
u.nombre_completo as entregante, 
r.nombre_completo as receptor, 
d.nombre as distrito, 
c.estado
FROM conocimientos c
LEFT JOIN usuarios u ON c.entregante_id = u.id
LEFT JOIN receptores r ON c.receptor_id = r.id
INNER JOIN usuarios creador ON c.creado_por = creador.id
INNER JOIN distritos distrito_entregante ON entregante.distrito_id = distrito_entregante.id
INNER JOIN distritos distrito_receptor ON receptor.distrito_id = distrito_receptor.id
LEFT JOIN detalle_conocimientos ci ON c.id = ci.conocimiento_id
LEFT JOIN insumos i ON ci.insumo_id = i.id
WHERE 1=1";

$params = [];

// Aplicar filtros
if ($filtros['anio']) {
    $query .= " AND YEAR(c.fecha_entrega) = :anio";
    $params[':anio'] = $filtros['anio'];
}

if ($filtros['mes']) {
    $query .= " AND MONTH(c.fecha_entrega) = :mes";
    $params[':mes'] = $filtros['mes'];
}

if ($filtros['distrito_id']) {
    $query .= " AND (entregante.distrito_id = :distrito_id OR receptor.distrito_id = :distrito_id)";
    $params[':distrito_id'] = $filtros['distrito_id'];
}

if ($filtros['entregante_id']) {
    $query .= " AND c.entregante_id = :entregante_id";
    $params[':entregante_id'] = $filtros['entregante_id'];
}

if ($filtros['receptor_id']) {
    $query .= " AND c.receptor_id = :receptor_id";
    $params[':receptor_id'] = $filtros['receptor_id'];
}

if ($filtros['insumo_id']) {
    $query .= " AND ci.insumo_id = :insumo_id";
    $params[':insumo_id'] = $filtros['insumo_id'];
}

if ($filtros['buscar']) {
    $query .= " AND (c.numero_conocimiento LIKE :buscar 
                   OR c.lugar_entrega LIKE :buscar
                   OR CONCAT(entregante.nombre, ' ', entregante.apellido) LIKE :buscar
                   OR CONCAT(receptor.nombre, ' ', receptor.apellido) LIKE :buscar
                   OR i.nombre LIKE :buscar
                   OR c.observaciones LIKE :buscar)";
    $params[':buscar'] = '%' . $filtros['buscar'] . '%';
}

// Restricciones por rol
if ($current_user['rol'] == 'RRHH') {
    $query .= " AND (entregante.distrito_id = :user_distrito OR receptor.distrito_id = :user_distrito)";
    $params[':user_distrito'] = $current_user['distrito_id'];
}

$query .= " GROUP BY c.id ORDER BY c.fecha_entrega DESC, c.numero_conocimiento DESC";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$conocimientos = $stmt->fetchAll();

// Generar nombre del archivo
$fecha_actual = date('Y-m-d_H-i-s');
$nombre_archivo = "reporte_conocimientos_{$fecha_actual}.csv";

// Configurar headers para descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Crear archivo CSV
$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados
$headers = [
    'ID',
    'Número de Conocimiento',
    'Fecha de Entrega',
    'Lugar de Entrega',
    'Entregante',
    'Puesto Entregante',
    'Distrito Entregante',
    'Receptor',
    'Puesto Receptor',
    'Distrito Receptor',
    'Total Insumos',
    'Detalle de Insumos',
    'Valor Total (Q)',
    'Observaciones',
    'Fecha de Creación',
    'Creado por'
];

fputcsv($output, $headers, ',', '"');

// Datos
foreach ($conocimientos as $conocimiento) {
    $row = [
        $conocimiento['id'],
        $conocimiento['numero_conocimiento'],
        $conocimiento['fecha_entrega'],
        $conocimiento['lugar_entrega'],
        $conocimiento['entregante'],
        $conocimiento['entregante_puesto'],
        $conocimiento['entregante_distrito'],
        $conocimiento['receptor'],
        $conocimiento['receptor_puesto'],
        $conocimiento['receptor_distrito'],
        $conocimiento['total_insumos'],
        $conocimiento['insumos_detalle'] ?: 'Sin insumos',
        number_format($conocimiento['valor_total'], 2),
        $conocimiento['observaciones'] ?: '',
        $conocimiento['fecha_creacion'],
        $conocimiento['creado_por']
    ];
    
    fputcsv($output, $row, ',', '"');
}

// Agregar información de filtros aplicados
fputcsv($output, [], ',', '"'); // Línea vacía
fputcsv($output, ['FILTROS APLICADOS:'], ',', '"');

if ($filtros['anio']) {
    fputcsv($output, ['Año:', $filtros['anio']], ',', '"');
}
if ($filtros['mes']) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    fputcsv($output, ['Mes:', $meses[$filtros['mes']]], ',', '"');
}
if ($filtros['buscar']) {
    fputcsv($output, ['Búsqueda:', $filtros['buscar']], ',', '"');
}

fputcsv($output, [], ',', '"'); // Línea vacía
fputcsv($output, ['RESUMEN:'], ',', '"');
fputcsv($output, ['Total de registros:', count($conocimientos)], ',', '"');
fputcsv($output, ['Fecha de exportación:', date('d/m/Y H:i:s')], ',', '"');
fputcsv($output, ['Exportado por:', $current_user['nombre'] . ' ' . $current_user['apellido']], ',', '"');

fclose($output);

// Registrar actividad
log_user_activity($current_user['id'], 'exportar_excel', 'Exportó reporte de conocimientos a Excel');

} catch (Exception $e) {
error_log("Error exportando a Excel: " . $e->getMessage());

// Redirigir con error
header('Location: ' . APP_URL . '/modules/conocimientos/pages/reportes.php?error=' . urlencode('Error al exportar el reporte'));
exit();
}
?>
