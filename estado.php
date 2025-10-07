<?php
session_start();
require_once 'config/database.php';
require_once 'classes/MantenimientoRepository.php';
require_once 'includes/functions.php';

$repository = new MantenimientoRepository();

$folio = $_GET['folio'] ?? ($_POST['folio'] ?? '');
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

$mantenimiento = null;
$diagnostico = null;
$seguimientos = [];
$historial = [];
$error = '';

try {
    if ($token !== '') {
        $mantenimiento = $repository->obtenerMantenimientoPorToken($token);
    } elseif ($folio !== '') {
        $mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
    }

    if ($mantenimiento) {
        $diagnostico = $repository->obtenerDiagnostico((int) $mantenimiento['id']);
        $seguimientos = $repository->obtenerSeguimientos((int) $mantenimiento['id']);
        $historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);
        $token = $mantenimiento['public_token'];
        $folio = $mantenimiento['folio'];
    } elseif (($folio !== '') || ($token !== '')) {
        $error = 'No se encontró información para el número de ingreso proporcionado.';
    }
} catch (Throwable $th) {
    error_log('Error consultando estado: ' . $th->getMessage());
    $error = 'No fue posible consultar el estado en este momento.';
}

$page_title = 'Consulta de estado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de mantenimiento - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fb; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-tools me-2 text-primary"></i>Seguimiento de Mantenimiento
        </a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h1 class="h4 mb-3">Consulta pública de estado</h1>
                    <p class="text-muted">Ingrese el número de ingreso (folio) o escanee el código QR entregado en la boleta para conocer el estado actual de su equipo.</p>
                    <form method="get" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Número de ingreso</label>
                            <input type="text" name="folio" class="form-control" value="<?php echo htmlspecialchars($folio); ?>" placeholder="MT-2025-0001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Código de seguimiento</label>
                            <input type="text" name="token" class="form-control" value="<?php echo htmlspecialchars($token); ?>" placeholder="Código QR">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Consultar estado
                            </button>
                        </div>
                    </form>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger mt-3 mb-0"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($mantenimiento): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h5 mb-0">Estado actual</h2>
                            <div class="small text-muted">Actualizado al <?php echo format_datetime($mantenimiento['fecha_actualizacion']); ?></div>
                        </div>
                        <span class="badge bg-<?php echo mantenimiento_estado_badge_class($mantenimiento['estado']); ?> fs-6"><?php echo mantenimiento_estado_label($mantenimiento['estado']); ?></span>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Número de ingreso</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($mantenimiento['folio']); ?></dd>
                            <dt class="col-sm-4">Equipo</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($mantenimiento['equipo_descripcion']); ?> (<?php echo htmlspecialchars($mantenimiento['codigo']); ?>)</dd>
                            <dt class="col-sm-4">Tipo de mantenimiento</dt>
                            <dd class="col-sm-8"><?php echo mantenimiento_tipo_options()[$mantenimiento['tipo_mantenimiento']] ?? $mantenimiento['tipo_mantenimiento']; ?></dd>
                            <dt class="col-sm-4">Fecha de recepción</dt>
                            <dd class="col-sm-8"><?php echo format_date($mantenimiento['fecha_recepcion']); ?></dd>
                            <dt class="col-sm-4">Técnico asignado</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($mantenimiento['tecnico_nombre'] ?? 'Por asignar'); ?></dd>
                        </dl>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h2 class="h6 mb-0">Línea de tiempo</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historial)): ?>
                            <p class="text-muted mb-0">Aún no hay eventos registrados.</p>
                        <?php else: ?>
                            <ul class="timeline list-unstyled mb-0">
                                <?php foreach ($historial as $evento): ?>
                                    <li class="mb-3">
                                        <div class="small text-muted"><?php echo format_datetime($evento['fecha_registro']); ?></div>
                                        <strong><?php echo mantenimiento_estado_label($evento['estado']); ?></strong>
                                        <?php if (!empty($evento['comentario'])): ?>
                                            <div><?php echo nl2br(htmlspecialchars($evento['comentario'])); ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($diagnostico): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Diagnóstico técnico</h2>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Descripción:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['descripcion_falla'])); ?></p>
                            <?php if (!empty($diagnostico['accion_recomendada'])): ?>
                                <p class="mb-0"><strong>Acción recomendada:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['accion_recomendada'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="h6 mb-0">Seguimientos recientes</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($seguimientos)): ?>
                            <p class="text-muted mb-0">Aún no se han registrado seguimientos adicionales.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($seguimientos as $seguimiento): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-semibold">Seguimiento</span>
                                            <span class="small text-muted"><?php echo format_datetime($seguimiento['fecha_seguimiento']); ?></span>
                                        </div>
                                        <div class="small text-muted mb-1">Estado: <?php echo mantenimiento_estado_label($seguimiento['estado']); ?></div>
                                        <div><?php echo nl2br(htmlspecialchars($seguimiento['descripcion'])); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
