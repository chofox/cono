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

if (!$current_user) {
    header('Location: login.php');
    exit();
}

// Obtener estadísticas del dashboard
try {
    $database = new Database();
    $conn = $database->getConnection();

    $stats = [];

    // Total de conocimientos
    $query = "SELECT COUNT(*) as total FROM conocimientos WHERE estado != 'anulado'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_conocimientos'] = (int) $stmt->fetch()['total'];

    // Conocimientos del año actual
    $current_year = date('Y');
    $query = "SELECT COUNT(*) as total FROM conocimientos WHERE año = :year AND estado != 'anulado'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':year', $current_year);
    $stmt->execute();
    $stats['conocimientos_año'] = (int) $stmt->fetch()['total'];

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
    $stats['conocimientos_mes'] = (int) $stmt->fetch()['total'];

    // Total de usuarios activos
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['usuarios_activos'] = (int) $stmt->fetch()['total'];

    // Total de insumos activos
    $query = "SELECT COUNT(*) as total FROM insumos WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['insumos_activos'] = (int) $stmt->fetch()['total'];

    // Últimos conocimientos
    $where_clause = '';
    if ($current_user['rol'] !== 'Administrador') {
        $where_clause = "AND (c.entregante_id = {$current_user['id']} OR c.receptor_id = {$current_user['id']})";
    }

    $query_recent_conocimientos = "SELECT c.id, c.numero_conocimiento, c.fecha_entrega, c.lugar_entrega,
                                    c.observaciones_generales, c.estado, c.fecha_creacion,
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
        'insumos_activos' => 0,
    ];
    $recent_conocimientos = [];
    $current_year = date('Y');
}

$page_title = 'Dashboard';
$page_subtitle = 'Resumen general del sistema';
$breadcrumbs = [
    ['label' => 'Inicio'],
];

$page_actions = [];
if (has_role('Técnico') || has_role('Administrador')) {
    $page_actions[] = [
        'label' => 'Nuevo conocimiento',
        'href' => APP_URL . '/modules/conocimientos/pages/crear.php',
        'icon' => 'fas fa-plus-circle',
        'class' => 'button is-primary',
    ];
}
$page_actions[] = [
    'label' => 'Panel de mantenimientos',
    'href' => APP_URL . '/modules/mantenimiento/index.php',
    'icon' => 'fas fa-tools',
    'class' => 'button is-light',
];

$app_name_json = json_encode(APP_NAME, JSON_UNESCAPED_UNICODE);
$inline_scripts = <<<'HTML'
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const refreshInterval = 300000; // 5 minutos
        setInterval(() => window.location.reload(), refreshInterval);

        const appName = {$app_name_json};
        const updateTitle = () => {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-GT');
            document.title = `Dashboard - ${timeString} - ${appName}`;
        };

        updateTitle();
        setInterval(updateTitle, 1000);
    });
</script>
HTML;

include 'includes/page_start.php';
?>

<section class="app-section welcome-section">
    <div class="columns is-vcentered">
        <div class="column is-8">
            <h2 class="title is-3">¡Bienvenido, <?php echo escape_html($current_user['nombre_completo']); ?>!</h2>
            <p class="subtitle is-6 has-text-grey">
                <span class="icon-text">
                    <span class="icon"><i class="fas fa-user-tag"></i></span>
                    <span><?php echo escape_html($current_user['rol']); ?> · <?php echo escape_html($current_user['distrito']); ?></span>
                </span>
            </p>
            <p class="has-text-grey">
                <span class="icon-text">
                    <span class="icon"><i class="fas fa-calendar-alt"></i></span>
                    <span><?php echo format_date(date('Y-m-d'), 'd/m/Y'); ?></span>
                </span>
            </p>
        </div>
        <div class="column is-4 has-text-right">
            <span class="icon welcome-icon">
                <i class="fas fa-clipboard-list"></i>
            </span>
        </div>
    </div>
</section>

<section class="app-section">
    <div class="dashboard-tiles">
        <div class="dashboard-tile">
            <div class="icon is-medium has-text-primary"><i class="fas fa-file-alt"></i></div>
            <strong><?php echo number_format($stats['total_conocimientos']); ?></strong>
            <span class="has-text-grey">Total de conocimientos</span>
        </div>
        <div class="dashboard-tile">
            <div class="icon is-medium has-text-success"><i class="fas fa-calendar-check"></i></div>
            <strong><?php echo number_format($stats['conocimientos_año']); ?></strong>
            <span class="has-text-grey">Conocimientos en <?php echo $current_year; ?></span>
        </div>
        <div class="dashboard-tile">
            <div class="icon is-medium has-text-warning"><i class="fas fa-calendar-day"></i></div>
            <strong><?php echo number_format($stats['conocimientos_mes']); ?></strong>
            <span class="has-text-grey">Este mes</span>
        </div>
        <div class="dashboard-tile">
            <div class="icon is-medium has-text-info"><i class="fas fa-boxes"></i></div>
            <strong><?php echo number_format($stats['insumos_activos']); ?></strong>
            <span class="has-text-grey">Insumos disponibles</span>
        </div>
    </div>
</section>

<div class="columns is-variable is-5">
    <div class="column is-4-desktop is-12-tablet">
        <section class="app-section is-full-height">
            <h3 class="title is-5 has-text-primary">
                <span class="icon-text">
                    <span class="icon"><i class="fas fa-bolt"></i></span>
                    <span>Acciones rápidas</span>
                </span>
            </h3>
            <div class="quick-actions">
                <?php if (has_role('Técnico') || has_role('Administrador')): ?>
                    <a class="button is-primary is-light is-fullwidth" href="<?php echo APP_URL; ?>/modules/conocimientos/pages/crear.php">
                        <span class="icon"><i class="fas fa-plus"></i></span>
                        <span>Nuevo conocimiento</span>
                    </a>
                <?php endif; ?>
                <a class="button is-link is-light is-fullwidth" href="<?php echo APP_URL; ?>/modules/conocimientos/index.php">
                    <span class="icon"><i class="fas fa-search"></i></span>
                    <span>Buscar conocimientos</span>
                </a>
                <a class="button is-info is-light is-fullwidth" href="<?php echo APP_URL; ?>/modules/conocimientos/pages/reportes.php">
                    <span class="icon"><i class="fas fa-chart-line"></i></span>
                    <span>Reportes</span>
                </a>
                <?php if (has_role('Administrador')): ?>
                    <a class="button is-warning is-light is-fullwidth" href="<?php echo APP_URL; ?>/usuarios.php">
                        <span class="icon"><i class="fas fa-users"></i></span>
                        <span>Gestionar usuarios</span>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="column is-8-desktop is-12-tablet">
        <section class="app-section is-full-height">
            <div class="section-header is-justify-content-space-between">
                <div>
                    <h3 class="title is-5 has-text-primary mb-1">
                        <span class="icon-text">
                            <span class="icon"><i class="fas fa-clock"></i></span>
                            <span>Conocimientos recientes</span>
                        </span>
                    </h3>
                    <p class="has-text-grey is-size-7">Últimas actividades registradas</p>
                </div>
                <a class="button is-small is-light" href="<?php echo APP_URL; ?>/modules/conocimientos/index.php">
                    <span class="icon"><i class="fas fa-list"></i></span>
                    <span>Ver todos</span>
                </a>
            </div>

            <?php if (empty($recent_conocimientos)): ?>
                <div class="empty-state">
                    <span class="icon is-large has-text-grey"><i class="fas fa-inbox"></i></span>
                    <p class="has-text-grey">No hay conocimientos recientes para mostrar.</p>
                </div>
            <?php else: ?>
                <ul class="modern-list">
                    <?php foreach ($recent_conocimientos as $conocimiento): ?>
                        <li class="modern-list-item">
                            <div>
                                <a class="modern-list-title" href="<?php echo APP_URL; ?>/modules/conocimientos/pages/ver.php?id=<?php echo $conocimiento['id']; ?>">
                                    Conocimiento #<?php echo escape_html($conocimiento['numero_conocimiento']); ?>
                                </a>
                                <p class="modern-list-meta">
                                    <span><i class="fas fa-user"></i> Entregante: <?php echo escape_html($conocimiento['entregante']); ?></span>
                                    <span><i class="fas fa-user-check"></i> Receptor: <?php echo escape_html($conocimiento['receptor']); ?></span>
                                </p>
                                <p class="modern-list-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo format_date($conocimiento['fecha_creacion'], 'd/m/Y H:i'); ?></span>
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo escape_html($conocimiento['lugar_entrega']); ?></span>
                                </p>
                            </div>
                            <span class="tag is-primary is-light">Ver</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php
include 'includes/page_end.php';
