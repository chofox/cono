<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'classes/MantenimientoRepository.php';
require_once 'includes/functions.php';

require_auth();
require_any_role(['Recepcionista', 'Supervisor', 'Técnico', 'Administrador']);

$auth = new Auth();
$current_user = $auth->getCurrentUser();
$repository = new MantenimientoRepository();

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

$page_title = 'Mantenimientos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimientos - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include_once 'includes/navbar.php'; ?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3 col-lg-2 px-0">
            <?php include 'includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h3 mb-0">Módulo de Mantenimiento</h1>
                    <p class="text-muted">Gestione el ciclo completo de mantenimiento institucional.</p>
                </div>
                <?php if (has_any_role(['Recepcionista', 'Administrador'])): ?>
                    <a href="recepcion_nueva.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Nuevo ingreso
                    </a>
                <?php endif; ?>
            </div>

            <?php show_flash_message(); ?>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <h2 class="h5 mb-0">Filtros</h2>
                        <a href="mantenimientos.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-undo"></i> Limpiar
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form class="row g-3" method="get">
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($estados as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($filtros['estado'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo de mantenimiento</label>
                            <select name="tipo_mantenimiento" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($tipos as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($filtros['tipo_mantenimiento'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Técnico</label>
                            <select name="tecnico_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id']; ?>" <?php echo ($filtros['tecnico_id'] == $tecnico['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Folio</label>
                            <input type="text" name="folio" class="form-control" value="<?php echo htmlspecialchars($filtros['folio']); ?>" placeholder="MT-2025-0001">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                        </div>
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Buscar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Resumen</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Equipo</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Técnico</th>
                                    <th>Recepción</th>
                                    <th class="text-end">Costo total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mantenimientos)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No se encontraron mantenimientos con los criterios seleccionados.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mantenimientos as $mantenimiento): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($mantenimiento['folio']); ?></strong>
                                                <div class="text-muted small">Ingreso: <?php echo format_date($mantenimiento['fecha_recepcion']); ?></div>
                                            </td>
                                            <td>
                                                <span class="fw-semibold"><?php echo htmlspecialchars($mantenimiento['equipo_codigo']); ?></span><br>
                                                <span class="text-muted small"><?php echo htmlspecialchars($mantenimiento['equipo_descripcion']); ?></span>
                                            </td>
                                            <td><?php echo $tipos[$mantenimiento['tipo_mantenimiento']] ?? $mantenimiento['tipo_mantenimiento']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo mantenimiento_estado_badge_class($mantenimiento['estado']); ?>">
                                                    <?php echo mantenimiento_estado_label($mantenimiento['estado']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($mantenimiento['tecnico_nombre'] ?? 'Sin asignar'); ?></td>
                                            <td><?php echo format_date($mantenimiento['fecha_recepcion']); ?></td>
                                            <td class="text-end">Q<?php echo number_format((float)$mantenimiento['costo_total'], 2); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if (has_any_role(['Supervisor', 'Técnico', 'Administrador'])): ?>
                                                        <a class="btn btn-outline-secondary" href="diagnostico.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>">
                                                            <i class="fas fa-stethoscope"></i>
                                                        </a>
                                                        <a class="btn btn-outline-secondary" href="mantenimiento.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>">
                                                            <i class="fas fa-tools"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (has_any_role(['Recepcionista', 'Administrador'])): ?>
                                                        <a class="btn btn-outline-secondary" href="entrega.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>">
                                                            <i class="fas fa-truck"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a class="btn btn-outline-secondary" href="estado.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" target="_blank">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
