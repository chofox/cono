<?php
/**
 * Reportes y Historial de Entregas
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'includes/functions.php';

// Verificar autenticación
require_auth();

$auth = new Auth();
$current_user = $auth->getCurrentUser();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener datos para filtros
    $query = "SELECT id, nombre FROM distritos WHERE activo = 1 ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $distritos = $stmt->fetchAll();
    
    $query = "SELECT id, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
    
    $query = "SELECT id, nombre_completo FROM receptores WHERE activo = 1 ORDER BY nombre_completo";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $receptores = $stmt->fetchAll();
    
    $query = "SELECT id, nombre FROM insumos WHERE activo = 1 ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $insumos = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error cargando datos: " . $e->getMessage());
    $error_message = "Error cargando los datos";
    $distritos = [];
    $usuarios = [];
    $receptores = [];
    $insumos = [];
}

// Obtener parámetros de filtro
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$filter_distrito = isset($_GET['distrito']) ? (int)$_GET['distrito'] : 0;
$filter_entregante = isset($_GET['entregante']) ? (int)$_GET['entregante'] : 0;
$filter_receptor = isset($_GET['receptor']) ? (int)$_GET['receptor'] : 0;
$filter_insumo = isset($_GET['insumo']) ? (int)$_GET['insumo'] : 0;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Construir query con filtros
$where_conditions = ["YEAR(c.fecha_entrega) = :year"];
$params = [':year' => $filter_year];

if ($filter_month > 0) {
    $where_conditions[] = "MONTH(c.fecha_entrega) = :month";
    $params[':month'] = $filter_month;
}

if ($filter_distrito > 0) {
    $where_conditions[] = "(entregante.distrito_id = :distrito OR receptor.distrito_id = :distrito)";
    $params[':distrito'] = $filter_distrito;
}

if ($filter_entregante > 0) {
    $where_conditions[] = "c.entregante_id = :entregante";
    $params[':entregante'] = $filter_entregante;
}

if ($filter_receptor > 0) {
    $where_conditions[] = "c.receptor_id = :receptor";
    $params[':receptor'] = $filter_receptor;
}

if ($filter_insumo > 0) {
    $where_conditions[] = "EXISTS (SELECT 1 FROM detalle_conocimientos ci WHERE ci.conocimiento_id = c.id AND ci.insumo_id = :insumo)";
    $params[':insumo'] = $filter_insumo;
}

if (!empty($search)) {
    $where_conditions[] = "(c.numero_conocimiento LIKE :search OR c.lugar_entrega LIKE :search OR c.observaciones_generales LIKE :search)";
    $params[':search'] = "%{$search}%";
}

// Restricción por rol
if ($current_user['rol'] == 'Técnico') {
    $where_conditions[] = "(c.entregante_id = :user_id OR c.creado_por = :user_id)";
    $params[':user_id'] = $current_user['id'];
} elseif ($current_user['rol'] == 'RRHH') {
    $where_conditions[] = "(entregante.distrito_id = :user_distrito OR receptor.distrito_id = :user_distrito)";
    $params[':user_distrito'] = $current_user['distrito_id'];
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Contar total de conocimientos
    $count_query = "SELECT COUNT(*) as total 
                    FROM conocimientos c
                    INNER JOIN usuarios entregante ON c.entregante_id = entregante.id
                    INNER JOIN receptores receptor ON c.receptor_id = receptor.id
                    LEFT JOIN puestos pr ON receptor.puesto_id = pr.id
                    LEFT JOIN detalle_conocimientos ci ON c.id = ci.conocimiento_id
                    {$where_clause}";
    
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $total_conocimientos = $stmt->fetch()['total'];
    $total_pages = ceil($total_conocimientos / $per_page);    // Obtener conocimientos
    $query = "SELECT c.*,
                     c.observaciones_generales as observaciones,
                     entregante.nombre_completo as entregante_nombre,
                     entregante.puesto as entregante_puesto,
                     d_entregante.nombre as entregante_distrito,
                     receptor.nombre_completo as receptor_nombre,
                     COALESCE(pr.nombre, receptor.puesto) as receptor_puesto,
                     d_receptor.nombre as receptor_distrito,
                     DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') as fecha_formateada,
                     DATE_FORMAT(c.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion,
                     (SELECT COUNT(*) FROM detalle_conocimientos ci WHERE ci.conocimiento_id = c.id) as total_insumos
              FROM conocimientos c
              INNER JOIN usuarios entregante ON c.entregante_id = entregante.id
              INNER JOIN receptores receptor ON c.receptor_id = receptor.id
              LEFT JOIN puestos pr ON receptor.puesto_id = pr.id
              INNER JOIN distritos d_entregante ON entregante.distrito_id = d_entregante.id
              INNER JOIN distritos d_receptor ON receptor.distrito_id = d_receptor.id
              {$where_clause}
              ORDER BY c.fecha_entrega DESC, c.id DESC
              LIMIT :per_page OFFSET :offset";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $conocimientos = $stmt->fetchAll();
    
    // Obtener estadísticas
    $stats_query = "SELECT 
                        COUNT(*) as total_entregas,
                        COUNT(DISTINCT c.entregante_id) as total_entregantes,
                        COUNT(DISTINCT c.receptor_id) as total_receptores,
                        COALESCE(SUM(ci.cantidad), 0) as total_insumos_entregados
                    FROM conocimientos c
                    INNER JOIN usuarios entregante ON c.entregante_id = entregante.id
                    INNER JOIN receptores receptor ON c.receptor_id = receptor.id
                    LEFT JOIN puestos pr ON receptor.puesto_id = pr.id
                    LEFT JOIN detalle_conocimientos ci ON c.id = ci.conocimiento_id
                    {$where_clause}";
    
    $stmt = $conn->prepare($stats_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $estadisticas = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Error cargando conocimientos: " . $e->getMessage());
    $error_message = "Error cargando el historial de entregas";
    $conocimientos = [];
    $total_conocimientos = 0;
    $total_pages = 0;
    $estadisticas = ['total_entregas' => 0, 'total_entregantes' => 0, 'total_receptores' => 0, 'total_insumos_entregados' => 0];
}

// Procesar exportación
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    try {
        // Obtener todos los datos para exportar (sin paginación)
                $export_query = "SELECT c.*,
                                c.observaciones_generales as observaciones,
                                entregante.nombre_completo as entregante_nombre,
                                entregante.puesto as entregante_puesto,
                                distrito_entregante.nombre as entregante_distrito,
                                receptor.nombre_completo as receptor_nombre,
                                COALESCE(pr.nombre, receptor.puesto) as receptor_puesto,
                                distrito_receptor.nombre as receptor_distrito,
                                DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') as fecha_formateada,
                                DATE_FORMAT(c.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion
                         FROM conocimientos c
                         INNER JOIN usuarios entregante ON c.entregante_id = entregante.id
                         INNER JOIN receptores receptor ON c.receptor_id = receptor.id
                         LEFT JOIN puestos pr ON receptor.puesto_id = pr.id
                         INNER JOIN distritos distrito_entregante ON entregante.distrito_id = distrito_entregante.id
                         INNER JOIN distritos distrito_receptor ON receptor.distrito_id = distrito_receptor.id
                         {$where_clause}
                         ORDER BY c.numero_conocimiento DESC";
        
        $stmt = $conn->prepare($export_query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $export_data = $stmt->fetchAll();
        
        // Configurar headers para descarga
        $filename = "reporte_entregas_" . $filter_year . "_" . date('Y-m-d_H-i-s') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // Crear archivo CSV
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, [
            'Número',
            'Fecha Entrega',
            'Lugar',
            'Entregante',
            'Puesto Entregante',
            'Distrito Entregante',
            'Receptor',
            'Puesto Receptor',
            'Distrito Receptor',
            'Observaciones',
            'Fecha Creación'
        ]);
        
        // Datos
        foreach ($export_data as $row) {
            fputcsv($output, [
                $row['numero_conocimiento'],
                $row['fecha_formateada'],
                $row['lugar_entrega'],
                $row['entregante_nombre'],
                $row['entregante_puesto'],
                $row['entregante_distrito'],
                $row['receptor_nombre'],
                $row['receptor_puesto'],
                $row['receptor_distrito'],
                $row['observaciones'],
                $row['fecha_creacion']
            ]);
        }
        
        fclose($output);
        
        // Log de actividad
        log_user_activity($current_user['id'], 'report_exported', "Reporte exportado: {$filename}");
        
        exit();
        
    } catch (Exception $e) {
        error_log("Error exportando reporte: " . $e->getMessage());
        $error_message = "Error exportando el reporte";
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Historial - <?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">

    <!-- CSS Global -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid mt-4">
      <div class="row">
        <div class="col-md-3 col-lg-2 px-0">
          <?php include 'includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10">
          <div class="container mt-2">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Reportes y Historial</li>
            </ol>
        </nav>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format((int)($estadisticas['total_entregas'] ?? 0)); ?></div>
                    <div class="stats-label">Total Entregas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format((int)($estadisticas['total_entregantes'] ?? 0)); ?></div>
                    <div class="stats-label">Entregantes Únicos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format((int)($estadisticas['total_receptores'] ?? 0)); ?></div>
                    <div class="stats-label">Receptores Únicos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format((float)($estadisticas['total_insumos_entregados'] ?? 0)); ?></div>
                    <div class="stats-label">Insumos Entregados</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="main-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Reportes y Historial de Entregas
                                </h4>
                                <p class="mb-0 mt-2 opacity-75">
                                    Consulte y exporte el historial de conocimientos de entrega
                                </p>
                            </div>
                            <div>
                                <?php
                                $export_url = "?" . http_build_query(array_merge($_GET, ['export' => 'excel']));
                                ?>
                                <a href="<?php echo $export_url; ?>" class="btn btn-light">
                                    <i class="fas fa-download me-2"></i>Exportar Excel
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo escape_html($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo escape_html($success_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Filtros -->
                        <div class="filters-card">
                            <form method="GET" action="">
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label for="year" class="form-label">Año</label>
                                        <select class="form-select" id="year" name="year">
                                            <?php
                                            $years = get_available_years();
                                            foreach ($years as $year):
                                            ?>
                                                <option value="<?php echo $year; ?>" <?php echo $filter_year == $year ? 'selected' : ''; ?>>
                                                    <?php echo $year; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="month" class="form-label">Mes</label>
                                        <select class="form-select" id="month" name="month">
                                            <option value="0">Todos</option>
                                            <?php
                                            $meses = [
                                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                                            ];
                                            foreach ($meses as $num => $nombre):
                                            ?>
                                                <option value="<?php echo $num; ?>" <?php echo $filter_month == $num ? 'selected' : ''; ?>>
                                                    <?php echo $nombre; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="distrito" class="form-label">Distrito</label>
                                        <select class="form-select" id="distrito" name="distrito">
                                            <option value="0">Todos</option>
                                            <?php foreach ($distritos as $distrito): ?>
                                                <option value="<?php echo $distrito['id']; ?>"
                                                        <?php echo $filter_distrito == $distrito['id'] ? 'selected' : ''; ?>>
                                                    <?php echo escape_html($distrito['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="entregante" class="form-label">Entregante</label>
                                        <select class="form-select" id="entregante" name="entregante">
                                            <option value="0">Todos</option>
                                            <?php foreach ($usuarios as $usuario): ?>
                                                <option value="<?php echo $usuario['id']; ?>"
                                                        <?php echo $filter_entregante == $usuario['id'] ? 'selected' : ''; ?>>
                                                    <?php echo escape_html($usuario['nombre_completo']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="receptor" class="form-label">Receptor</label>
                                        <select class="form-select" id="receptor" name="receptor">
                                            <option value="0">Todos</option>
                                            <?php foreach ($receptores as $receptor): ?>
                                                <option value="<?php echo $receptor['id']; ?>"
                                                        <?php echo $filter_receptor == $receptor['id'] ? 'selected' : ' '; ?>>
                                                    <?php echo escape_html($receptor['nombre_completo']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="insumo" class="form-label">Insumo</label>
                                        <select class="form-select" id="insumo" name="insumo">
                                            <option value="0">Todos</option>
                                            <?php foreach ($insumos as $insumo): ?>
                                                <option value="<?php echo $insumo['id']; ?>"
                                                        <?php echo $filter_insumo == $insumo['id'] ? 'selected' : ''; ?>>
                                                    <?php echo escape_html($insumo['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <label for="search" class="form-label">Buscar</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="search" 
                                               name="search" 
                                               value="<?php echo escape_html($search); ?>"
                                               placeholder="Número de conocimiento, lugar o observaciones">
                                    </div>
                                    
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-search me-1"></i>Filtrar
                                        </button>
                                        <a href="reportes.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tabla de conocimientos -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Fecha</th>
                                        <th>Lugar</th>
                                        <th>Entregante</th>
                                        <th>Receptor</th>
                                        <th>Insumos</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($conocimientos)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No se encontraron conocimientos</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($conocimientos as $conocimiento): ?>
                                            <tr>
                                                <td>
                                                    <span class="conocimiento-number"><?php echo escape_html($conocimiento['numero_conocimiento']); ?></span>
                                                </td>
                                                <td><?php echo escape_html($conocimiento['fecha_formateada']); ?></td>
                                                <td><?php echo escape_html($conocimiento['lugar_entrega']); ?></td>
                                                <td class="user-info">
                                                    <div class="user-name"><?php echo escape_html($conocimiento['entregante_nombre']); ?></div>
                                                    <div class="user-details">
                                                        <?php echo escape_html($conocimiento['entregante_puesto']); ?><br>
                                                        <small><?php echo escape_html($conocimiento['entregante_distrito']); ?></small>
                                                    </div>
                                                </td>
                                                <td class="user-info">
                                                    <div class="user-name"><?php echo escape_html($conocimiento['receptor_nombre']); ?></div>
                                                    <div class="user-details">
                                                        <?php echo escape_html($conocimiento['receptor_puesto']); ?><br>
                                                        <small><?php echo escape_html($conocimiento['receptor_distrito']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $conocimiento['total_insumos']; ?> items</span>
                                                </td>
                                                <td>
                                                    <a href="generar_pdf.php?id=<?php echo $conocimiento['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       title="Ver PDF"
                                                       target="_blank">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-info"
                                                            onclick="verDetalle(<?php echo $conocimiento['id']; ?>)"
                                                            title="Ver detalle">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Paginación de conocimientos">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                Anterior
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                Siguiente
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            
                            <div class="text-center text-muted">
                                Mostrando <?php echo count($conocimientos); ?> de <?php echo $total_conocimientos; ?> conocimientos
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal Detalle -->
    <div class="modal fade" id="detalleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Detalle del Conocimiento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalleContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function verDetalle(conocimientoId) {
            $('#detalleModal').modal('show');
            
            $.ajax({
                url: 'ajax/detalle_conocimiento.php',
                method: 'GET',
                data: { id: conocimientoId },
                success: function(response) {
                    $('#detalleContent').html(response);
                },
                error: function() {
                    $('#detalleContent').html('<div class="alert alert-danger">Error cargando el detalle</div>');
                }
            });
        }
        
        // Auto-submit filtros con delay
        let filterTimeout;
        $('#search').on('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(function() {
                $('form').first().submit();
            }, 500);
        });
        
        // Cambio automático en selects
        $('.form-select').on('change', function() {
            if ($(this).attr('id') !== 'search') {
                $('form').first().submit();
            }
        });
    </script>
</body>
</html>



