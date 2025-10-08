<?php
require_once __DIR__ . '/includes/bootstrap.php';

require_auth();
require_any_role(['Recepcionista', 'Supervisor', 'Técnico', 'Administrador']);

$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'tipo_mantenimiento' => $_GET['tipo_mantenimiento'] ?? '',
    'tecnico_id' => $_GET['tecnico_id'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? '',
    'folio' => $_GET['folio'] ?? '',
];

$mantenimientos = $repository->buscarMantenimientos($filtros);
$tecnicos = get_users_by_role('Técnico');
$estados = mantenimiento_estados();
$tipos = mantenimiento_tipo_options();

$page_title = 'Mantenimiento de equipos';
$page_subtitle = 'Registre, supervise y entregue equipos institucionales';
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Mantenimiento'],
];

$page_actions = [];
if (has_any_role(['Recepcionista', 'Administrador'])) {
    $page_actions[] = [
        'label' => 'Nuevo ingreso',
        'href' => APP_URL . '/modules/mantenimiento/pages/recepcion.php',
        'icon' => 'fas fa-plus-circle',
        'class' => 'button is-primary',
    ];
}

include dirname(__DIR__, 2) . '/includes/page_start.php';
?>

<div class="card mb-6">
    <header class="card-header">
        <p class="card-header-title">Filtros</p>
        <a class="card-header-icon" href="<?php echo APP_URL; ?>/modules/mantenimiento/index.php" title="Limpiar filtros">
            <span class="icon"><i class="fas fa-rotate-left"></i></span>
        </a>
    </header>
    <div class="card-content">
        <form method="get">
            <div class="columns is-multiline">
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Estado</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="estado">
                                    <option value="">Todos</option>
                                    <?php foreach ($estados as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($filtros['estado'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Tipo de mantenimiento</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="tipo_mantenimiento">
                                    <option value="">Todos</option>
                                    <?php foreach ($tipos as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($filtros['tipo_mantenimiento'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Técnico</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="tecnico_id">
                                    <option value="">Todos</option>
                                    <?php foreach ($tecnicos as $tecnico): ?>
                                        <option value="<?php echo $tecnico['id']; ?>" <?php echo ($filtros['tecnico_id'] == $tecnico['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Folio</label>
                        <div class="control">
                            <input class="input" type="text" name="folio" value="<?php echo htmlspecialchars($filtros['folio']); ?>" placeholder="MT-2025-0001">
                        </div>
                    </div>
                </div>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Desde</label>
                        <div class="control">
                            <input class="input" type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                        </div>
                    </div>
                </div>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="field">
                        <label class="label">Hasta</label>
                        <div class="control">
                            <input class="input" type="date" name="fecha_fin" value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                        </div>
                    </div>
                </div>
                <div class="column is-12">
                    <div class="field is-grouped is-justify-content-flex-end">
                        <div class="control">
                            <button class="button is-primary" type="submit">
                                <span class="icon"><i class="fas fa-search"></i></span>
                                <span>Buscar</span>
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
        <p class="card-header-title">Resumen de mantenimientos</p>
    </header>
    <div class="card-content">
        <?php if (empty($mantenimientos)): ?>
            <div class="notification is-light has-text-centered">
                <span class="icon-text">
                    <span class="icon"><i class="fas fa-info-circle"></i></span>
                    <span>No se encontraron mantenimientos con los criterios seleccionados.</span>
                </span>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Equipo</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Técnico</th>
                            <th>Recepción</th>
                            <th class="has-text-centered">Repuestos</th>
                            <th class="has-text-centered">Conocimiento</th>
                            <th class="has-text-centered">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mantenimientos as $mantenimiento): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($mantenimiento['folio']); ?></strong>
                                    <p class="is-size-7 has-text-grey">Ingreso: <?php echo format_date($mantenimiento['fecha_recepcion']); ?></p>
                                </td>
                                <td>
                                    <span class="has-text-weight-semibold"><?php echo htmlspecialchars($mantenimiento['equipo_codigo']); ?></span>
                                    <p class="is-size-7 has-text-grey"><?php echo htmlspecialchars($mantenimiento['equipo_descripcion']); ?></p>
                                </td>
                                <td><?php echo $tipos[$mantenimiento['tipo_mantenimiento']] ?? $mantenimiento['tipo_mantenimiento']; ?></td>
                                <td>
                                    <span class="tag <?php echo mantenimiento_estado_badge_class($mantenimiento['estado']); ?>">
                                        <?php echo mantenimiento_estado_label($mantenimiento['estado']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($mantenimiento['tecnico_nombre'] ?? 'Sin asignar'); ?></td>
                                <td><?php echo format_date($mantenimiento['fecha_recepcion']); ?></td>
                                <td class="has-text-centered">
                                    <p class="has-text-weight-semibold mb-0"><?php echo (int) ($mantenimiento['total_repuestos'] ?? 0); ?></p>
                                    <p class="is-size-7 has-text-grey">Registros &middot; <?php echo number_format((float) ($mantenimiento['total_unidades'] ?? 0), 2); ?> uds.</p>
                                </td>
                                <td class="has-text-centered">
                                    <?php if (!empty($mantenimiento['conocimiento_id'])): ?>
                                        <a class="has-text-weight-semibold" href="<?php echo APP_URL; ?>/modules/conocimientos/pages/editar.php?id=<?php echo (int) $mantenimiento['conocimiento_id']; ?>">
                                            #<?php echo htmlspecialchars($mantenimiento['conocimiento_numero'] ?? ''); ?>
                                        </a>
                                        <p class="is-size-7 has-text-grey"><?php echo htmlspecialchars($mantenimiento['conocimiento_estado'] ?? ''); ?></p>
                                    <?php else: ?>
                                        <span class="tag is-warning is-light">Sin generar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="has-text-centered">
                                    <div class="buttons are-small is-centered">
                                        <?php if (has_any_role(['Supervisor', 'Técnico', 'Administrador'])): ?>
                                            <a class="button is-light" href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/diagnostico.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" title="Diagnóstico">
                                                <span class="icon"><i class="fas fa-stethoscope"></i></span>
                                            </a>
                                            <a class="button is-light" href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/ejecucion.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" title="Ejecución">
                                                <span class="icon"><i class="fas fa-tools"></i></span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (has_any_role(['Recepcionista', 'Administrador'])): ?>
                                            <a class="button is-light" href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/entrega.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" title="Entrega">
                                                <span class="icon"><i class="fas fa-truck"></i></span>
                                            </a>
                                        <?php endif; ?>
                                        <a class="button is-light" href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/estado.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" target="_blank" title="Ver estado público">
                                            <span class="icon"><i class="fas fa-external-link-alt"></i></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/page_end.php'; ?>
