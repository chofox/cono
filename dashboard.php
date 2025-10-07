<?php
/**
 * Dashboard Principal
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

// debug_log('DEBUG: dashboard.php - Current user rol_id: ' . ($current_user['rol_id'] ?? 'N/A'));

if (!$current_user) {
    header('Location: login.php');
    exit();
}

// Obtener estadísticas del dashboard
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Estadísticas generales
    $stats = [];
    
    // Total de conocimientos
    $query = "SELECT COUNT(*) as total FROM conocimientos WHERE estado != 'anulado'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_conocimientos'] = $stmt->fetch()['total'];
    
    // Conocimientos del año actual
    $current_year = date('Y');
    $query = "SELECT COUNT(*) as total FROM conocimientos WHERE año = :year AND estado != 'anulado'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':year', $current_year);
    $stmt->execute();
    $stats['conocimientos_año'] = $stmt->fetch()['total'];
    
    // Conocimientos del mes actual
    $query = "SELECT COUNT(*) as total FROM conocimientos 
              WHERE YEAR(fecha_entrega) = :year 
              AND MONTH(fecha_entrega) = :month 
              AND estado != 'anulado'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':year', $current_year);
    $current_month = date('n');
    $stmt->bindParam(':month', $current_month);
    $stmt->execute();
    $stats['conocimientos_mes'] = $stmt->fetch()['total'];
    
    // Total de usuarios activos
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['usuarios_activos'] = $stmt->fetch()['total'];
    
    // Total de insumos activos
    $query = "SELECT COUNT(*) as total FROM insumos WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['insumos_activos'] = $stmt->fetch()['total'];
    
    // Últimos conocimientos (solo para el usuario actual si no es admin)
    $where_clause = '';
    if ($current_user['rol'] !== 'Administrador') {
        $where_clause = "AND (c.entregante_id = {$current_user['id']} OR c.receptor_id = {$current_user['id']})";
    }
    
    $query_recent_conocimientos = "SELECT c.id, c.numero_conocimiento, c.fecha_entrega, c.lugar_entrega, c.observaciones_generales, c.estado,
                                    c.fecha_creacion, /* Agregado para resolver la advertencia */
                                    u_ent.nombre_completo AS entregante,
                                    r.nombre_completo AS receptor
                                    FROM conocimientos c
              INNER JOIN usuarios u_ent ON c.entregante_id = u_ent.id
              INNER JOIN receptores r ON c.receptor_id = r.id
              WHERE c.estado != 'anulado' $where_clause
              ORDER BY c.fecha_creacion DESC
              LIMIT 5";
    
    $stmt = $conn->prepare($query_recent_conocimientos);
    $stmt->execute();
    $recent_conocimientos = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas: " . $e->getMessage());
    $stats = [
        'total_conocimientos' => 0,
        'conocimientos_año' => 0,
        'conocimientos_mes' => 0,
        'usuarios_activos' => 0,
        'insumos_activos' => 0
    ];
    $recent_conocimientos = [];
}

$page_title = "Dashboard";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">

    <!-- CSS Global -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navbar -->
    <?php include_once 'includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'includes/sidebar.php'; ?>
                <div class="sidebar d-none">
                    <div class="p-3">
                        <h6 class="text-muted text-uppercase mb-3">Menú Principal</h6>
                        <nav class="nav flex-column">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                            
                            <?php if (has_role('Técnico') || has_role('Administrador')): ?>
                            <a class="nav-link" href="<?php echo APP_URL; ?>/modules/conocimientos/pages/crear.php">
                                <i class="fas fa-plus-circle me-2"></i>Nuevo Conocimiento
                            </a>
                            <?php endif; ?>
                            
                            <a class="nav-link" href="<?php echo APP_URL; ?>/modules/conocimientos/index.php">
                                <i class="fas fa-list me-2"></i>Ver Conocimientos
                            </a>
                            
                            <a class="nav-link" href="reportes.php">
                                <i class="fas fa-chart-bar me-2"></i>Reportes
                            </a>
                            
                            <?php if (has_role('Administrador')): ?>
                            <hr class="my-3">
                            <h6 class="text-muted text-uppercase mb-3">Administración</h6>
                            
                            <a class="nav-link" href="usuarios.php">
                                <i class="fas fa-users me-2"></i>Usuarios
                            </a>
                            <a class="nav-link" href="receptores.php">
                                <i class="fas fa-users me-2"></i>Receptores
                            </a>
                            
                            <a class="nav-link" href="insumos.php">
                                <i class="fas fa-boxes me-2"></i>Catálogo de Insumos
                            </a>
                            
                            <a class="nav-link" href="configuracion.php">
                                <i class="fas fa-cog me-2"></i>Configuración
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Welcome Card -->
                    <div class="welcome-card p-4 mb-4 rounded-3 shadow-sm">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-2 text-primary">¡Bienvenido, <?php echo escape_html($current_user['nombre_completo']); ?>!</h3>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="fas fa-user-tag me-2 text-secondary"></i>
                                    <p class="mb-0 text-muted"><?php echo escape_html($current_user['rol']); ?> - <?php echo escape_html($current_user['distrito']); ?></p>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt me-2 text-secondary"></i>
                                    <p class="mb-0 text-muted"><?php echo format_date(date('Y-m-d'), 'd/m/Y'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <i class="fas fa-clipboard-list fa-5x text-light-blue opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card p-3 rounded-3 shadow-sm text-center">
                                <div class="stat-icon mx-auto mb-2" style="background: var(--accent-color);">
                                    <i class="fas fa-file-alt text-white"></i>
                                </div>
                                <h4 class="stat-number mb-1 text-primary"><?php echo number_format($stats['total_conocimientos']); ?></h4>
                                <p class="stat-label text-muted">Total Conocimientos</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stat-card p-3 rounded-3 shadow-sm text-center">
                                <div class="stat-icon mx-auto mb-2" style="background: var(--success-color);">
                                    <i class="fas fa-calendar-check text-white"></i>
                                </div>
                                <h4 class="stat-number mb-1 text-primary"><?php echo number_format($stats['conocimientos_año']); ?></h4>
                                <p class="stat-label text-muted">Este Año (<?php echo $current_year; ?>)</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stat-card p-3 rounded-3 shadow-sm text-center">
                                <div class="stat-icon mx-auto mb-2" style="background: var(--warning-color);">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <h3 class="stat-number"><?php echo number_format($stats['conocimientos_mes']); ?></h3>
                                <p class="stat-label">Este Mes</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: var(--danger-color);">
                                    <i class="fas fa-boxes"></i>
                                </div>
                                <h3 class="stat-number"><?php echo number_format($stats['insumos_activos']); ?></h3>
                                <p class="stat-label">Insumos Disponibles</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Quick Actions -->
                        <div class="col-md-4 mb-4">
                            <div class="card quick-actions h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title mb-3 text-primary">
                                        <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                                    </h5>
                                    
                                    <?php if (has_role('Técnico') || has_role('Administrador')): ?>
                                    <a href="<?php echo APP_URL; ?>/modules/conocimientos/pages/crear.php" class="btn btn-outline-primary w-100 mb-2 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-plus me-2"></i>Nuevo Conocimiento
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo APP_URL; ?>/modules/conocimientos/index.php" class="btn btn-outline-info w-100 mb-2 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-search me-2"></i>Buscar Conocimientos
                                    </a>
                                    
                                    <a href="reportes.php" class="btn btn-outline-success w-100 mb-2 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-chart-line me-2"></i>Ver Reportes
                                    </a>
                                    
                                    <?php if (has_role('Administrador')): ?>
                                    <a href="usuarios.php" class="btn btn-outline-warning w-100 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user-plus me-2"></i>Gestionar Usuarios
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="col-md-8 mb-4">
                            <div class="card recent-activity h-100 shadow-sm">
                                <div class="card-header bg-white border-bottom">
                                    <h5 class="card-title mb-0 text-primary">
                                        <i class="fas fa-clock me-2"></i>Conocimientos Recientes
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($recent_conocimientos)): ?>
                                        <div class="p-4 text-center text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p class="mb-0">No hay conocimientos recientes para mostrar.</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($recent_conocimientos as $conocimiento): ?>
                                                <?php // debug_log('Conocimiento en dashboard: ' . print_r($conocimiento, true)); ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <a href="<?php echo APP_URL; ?>/modules/conocimientos/pages/ver.php?id=<?php echo $conocimiento['id']; ?>" 
                                                           class="text-decoration-none text-dark">
                                                           Conocimiento #<?php echo escape_html($conocimiento['numero_conocimiento']); ?>
                                                        </a>
                                                    </h6>
                                                    <p class="mb-1 text-muted small">
                                                        <i class="fas fa-user me-1"></i>
                                                        Entregante: <?php echo escape_html($conocimiento['entregante']); ?> |
                                                        Receptor: <?php echo escape_html($conocimiento['receptor']); ?>
                                                    </p>
                                                    <p class="mb-0 text-muted small">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?php echo format_date($conocimiento['fecha_creacion'], 'd/m/Y H:i'); ?>
                                                    </p>
                                                </div>
                                                <span class="badge bg-primary rounded-pill">Ver</span>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3 text-center border-top">
                                    <a href="<?php echo APP_URL; ?>/modules/conocimientos/index.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-list me-2"></i>Ver Todos los Conocimientos
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bootstrap JS -->
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                    
                    <script>
                        // Auto-refresh estadísticas cada 5 minutos
                        setInterval(function() {
                            location.reload();
                        }, 300000);
                        
                        // Mostrar hora actual
                        function updateTime() {
                            const now = new Date();
                            const timeString = now.toLocaleTimeString('es-GT');
                            document.title = `Dashboard - ${timeString} - <?php echo APP_NAME; ?>`;
                        }
                        
                        updateTime();
                        setInterval(updateTime, 1000);
                    </script>
                </body>
                </html>
