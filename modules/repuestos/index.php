<?php
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth();
require_any_role(['Técnico', 'Supervisor', 'Administrador']);

$auth = new Auth();
$current_user = $auth->getCurrentUser();

$search = sanitize_input($_GET['search'] ?? '');
$estado = sanitize_input($_GET['estado'] ?? '');
$conocimientoFiltro = sanitize_input($_GET['conocimiento'] ?? '');

try {
    $database = new Database();
    $conn = $database->getConnection();

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(m.folio LIKE :search OR i.nombre LIKE :search OR i.codigo LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if ($estado !== '') {
        $where[] = 'm.estado = :estado';
        $params[':estado'] = $estado;
    }

    if ($conocimientoFiltro === 'con') {
        $where[] = 'mc.conocimiento_id IS NOT NULL';
    } elseif ($conocimientoFiltro === 'sin') {
        $where[] = 'mc.conocimiento_id IS NULL';
    }

    $query = "SELECT mr.id, mr.cantidad, mr.observaciones, mr.mantenimiento_id,
                     m.folio, m.estado AS estado_mantenimiento, m.fecha_actualizacion,
                     i.nombre AS insumo_nombre, i.codigo AS insumo_codigo, i.unidad_medida,
                     c.id AS conocimiento_id, c.numero_conocimiento, c.estado AS conocimiento_estado
              FROM mantenimiento_repuestos mr
              INNER JOIN mantenimientos m ON mr.mantenimiento_id = m.id
              INNER JOIN insumos i ON mr.insumo_id = i.id
              LEFT JOIN mantenimiento_conocimientos mc ON mc.mantenimiento_id = m.id
              LEFT JOIN conocimientos c ON mc.conocimiento_id = c.id";

    if (!empty($where)) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }

    $query .= ' ORDER BY m.fecha_actualizacion DESC, mr.id DESC';

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $repuestos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $th) {
    error_log('Error cargando repuestos: ' . $th->getMessage());
    $repuestos = [];
}

$estados = mantenimiento_estados();

$page_title = 'Repuestos asociados a mantenimiento';
$page_subtitle = 'Visualice y gestione los insumos utilizados en cada folio';
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Repuestos'],
];
$page_actions = [
    [
        'label' => 'Ir al módulo de mantenimiento',
        'href' => APP_URL . '/modules/mantenimiento/index.php',
        'icon' => 'fas fa-tools',
        'class' => 'button is-light',
    ],
];

include __DIR__ . '/../../includes/page_start.php';
?>

<div class="card mb-5">
    <header class="card-header">
        <p class="card-header-title">Filtros</p>
        <a class="card-header-icon" href="<?php echo APP_URL; ?>/modules/repuestos/index.php" title="Limpiar filtros">
            <span class="icon"><i class="fas fa-rotate-left"></i></span>
        </a>
    </header>
    <div class="card-content">
        <form method="get">
            <div class="columns is-multiline">
                <div class="column is-12-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Buscar</label>
                        <div class="control has-icons-left">
                            <input type="text" name="search" class="input" placeholder="Folio o insumo" value="<?php echo htmlspecialchars($search); ?>">
                            <span class="icon is-small is-left"><i class="fas fa-search"></i></span>
                        </div>
                    </div>
                </div>
                <div class="column is-12-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Estado del mantenimiento</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="estado">
                                    <option value="">Todos</option>
                                    <?php foreach ($estados as $clave => $label): ?>
                                        <option value="<?php echo $clave; ?>" <?php echo ($estado === $clave) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-12-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Conocimiento</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="conocimiento">
                                    <option value="">Todos</option>
                                    <option value="con" <?php echo ($conocimientoFiltro === 'con') ? 'selected' : ''; ?>>Conocimiento generado</option>
                                    <option value="sin" <?php echo ($conocimientoFiltro === 'sin') ? 'selected' : ''; ?>>Sin conocimiento</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-12">
                    <div class="field is-grouped is-justify-content-flex-end">
                        <div class="control">
                            <button type="submit" class="button is-primary">
                                <span class="icon"><i class="fas fa-filter"></i></span>
                                <span>Aplicar filtros</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <header class="card-header">
        <p class="card-header-title">Repuestos vinculados</p>
        <span class="card-header-icon">
            <span class="tag is-info is-light">Total registros: <?php echo count($repuestos); ?></span>
        </span>
    </header>
    <div class="card-content">
        <?php if (empty($repuestos)): ?>
            <div class="notification is-light has-text-centered">
                <span class="icon-text">
                    <span class="icon"><i class="fas fa-info-circle"></i></span>
                    <span>No se encontraron repuestos con los criterios seleccionados.</span>
                </span>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Estado</th>
                            <th>Insumo</th>
                            <th class="has-text-right">Cantidad</th>
                            <th>Observaciones</th>
                            <th>Conocimiento</th>
                            <th class="has-text-centered">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repuestos as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['folio']); ?></strong>
                                    <p class="is-size-7 has-text-grey">Actualizado: <?php echo format_datetime($item['fecha_actualizacion']); ?></p>
                                </td>
                                <td>
                                    <span class="tag <?php echo mantenimiento_estado_badge_class($item['estado_mantenimiento']); ?>">
                                        <?php echo mantenimiento_estado_label($item['estado_mantenimiento']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="has-text-weight-semibold"><?php echo htmlspecialchars($item['insumo_nombre']); ?></span>
                                    <?php if (!empty($item['insumo_codigo'])): ?>
                                        <p class="is-size-7 has-text-grey">Código: <?php echo htmlspecialchars($item['insumo_codigo']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="has-text-right"><?php echo number_format((float) $item['cantidad'], 2); ?> <?php echo htmlspecialchars($item['unidad_medida']); ?></td>
                                <td><?php echo htmlspecialchars($item['observaciones'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($item['conocimiento_id'])): ?>
                                        <a class="has-text-weight-semibold" href="<?php echo APP_URL; ?>/modules/conocimientos/pages/editar.php?id=<?php echo (int) $item['conocimiento_id']; ?>">
                                            #<?php echo htmlspecialchars($item['numero_conocimiento'] ?? ''); ?>
                                        </a>
                                        <p class="is-size-7 has-text-grey"><?php echo htmlspecialchars($item['conocimiento_estado'] ?? ''); ?></p>
                                    <?php else: ?>
                                        <span class="tag is-warning is-light">Sin generar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="has-text-centered">
                                    <a class="button is-small is-light" href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/ejecucion.php?folio=<?php echo urlencode($item['folio']); ?>" title="Abrir ejecución">
                                        <span class="icon"><i class="fas fa-tools"></i></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/page_end.php'; ?>
