<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/classes/Auth.php';
require_once dirname(__DIR__) . '/services/MantenimientoRepository.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

require_auth();
require_any_role(['Técnico', 'Supervisor', 'Administrador']);

$auth = new Auth();
$current_user = $auth->getCurrentUser();
$repository = new MantenimientoRepository();

$folio = $_GET['folio'] ?? '';
if ($folio === '') {
    header('Location: ' . APP_URL . '/modules/mantenimiento/index.php?error=folio_requerido');
    exit();
}

$mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
if (!$mantenimiento) {
    set_flash_message('No se encontró el mantenimiento solicitado.', 'danger');
    header('Location: ' . APP_URL . '/modules/mantenimiento/index.php');
    exit();
}

$diagnostico = $repository->obtenerDiagnostico((int) $mantenimiento['id']);
$repuestos = $repository->obtenerRepuestos((int) $mantenimiento['id']);
$historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido.';
    }

    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin = $_POST['fecha_fin'] ?? '';
    $duracion = $_POST['duracion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $costoManoObra = (float) ($_POST['costo_mano_obra'] ?? 0);
    $estadoDestino = $_POST['estado'] ?? 'en_mantenimiento';

    if (!validate_date($fechaInicio, 'Y-m-d\TH:i')) {
        $errors[] = 'Debe indicar una fecha y hora de inicio válidas.';
    }

    if ($fechaFin !== '' && !validate_date($fechaFin, 'Y-m-d\TH:i')) {
        $errors[] = 'La fecha y hora de finalización no son válidas.';
    }

    if ($duracion !== '' && !is_numeric($duracion)) {
        $errors[] = 'La duración debe expresarse en horas con formato numérico.';
    }

    $repuestosRegistrados = [];
    $totalRepuestos = 0;
    if (!empty($_POST['repuestos_nombre']) && is_array($_POST['repuestos_nombre'])) {
        foreach ($_POST['repuestos_nombre'] as $index => $nombre) {
            $nombre = trim($nombre);
            $cantidad = (int) ($_POST['repuestos_cantidad'][$index] ?? 0);
            $costoUnitario = (float) ($_POST['repuestos_costo'][$index] ?? 0);

            if ($nombre === '' || $cantidad <= 0) {
                continue;
            }

            $repuestosRegistrados[] = [
                'nombre' => $nombre,
                'cantidad' => $cantidad,
                'costo' => $costoUnitario,
            ];
            $totalRepuestos += $cantidad * $costoUnitario;
        }
    }

    $costoTotal = $costoManoObra + $totalRepuestos;

    if (!array_key_exists($estadoDestino, mantenimiento_estados())) {
        $errors[] = 'El estado seleccionado no es válido.';
    }

    if (empty($errors)) {
        try {
            $repository->registrarMantenimiento((int) $mantenimiento['id'], [
                'fecha_inicio' => str_replace('T', ' ', $fechaInicio),
                'fecha_fin' => $fechaFin ? str_replace('T', ' ', $fechaFin) : null,
                'duracion' => $duracion !== '' ? $duracion : null,
                'observaciones' => $observaciones,
                'costo_mano_obra' => $costoManoObra,
                'costo_repuestos' => $totalRepuestos,
                'costo_total' => $costoTotal,
                'estado' => $estadoDestino,
            ], $repuestosRegistrados, $current_user['id']);

            set_flash_message('Información de mantenimiento actualizada.', 'success');
            header('Location: ' . APP_URL . '/modules/mantenimiento/pages/ejecucion.php?folio=' . urlencode($folio));
            exit();
        } catch (Throwable $th) {
            error_log('Error actualizando mantenimiento: ' . $th->getMessage());
            $errors[] = 'Ocurrió un error al guardar la información del mantenimiento.';
        }
    }
}

$mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
$repuestos = $repository->obtenerRepuestos((int) $mantenimiento['id']);
$historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);

$csrf_token = generate_csrf_token();
$page_title = 'Ejecución de Mantenimiento';
$estados = mantenimiento_estados();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include_once dirname(__DIR__, 3) . '/includes/navbar.php'; ?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3 col-lg-2 px-0">
            <?php include dirname(__DIR__, 3) . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h3 mb-0">Ejecución del mantenimiento</h1>
                    <p class="text-muted mb-0">Folio <?php echo htmlspecialchars($mantenimiento['folio']); ?> — Estado actual: <span class="badge bg-<?php echo mantenimiento_estado_badge_class($mantenimiento['estado']); ?>"><?php echo mantenimiento_estado_label($mantenimiento['estado']); ?></span></p>
                </div>
                <div class="btn-group">
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Regresar
                    </a>
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/diagnostico.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-stethoscope me-2"></i>Diagnóstico
                    </a>
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/entrega.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-truck me-2"></i>Entrega
                    </a>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-lg-8">
                    <form method="post" class="card">
                        <div class="card-header bg-white">
                            <h2 class="h5 mb-0">Datos de ejecución</h2>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Inicio *</label>
                                    <input type="datetime-local" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($mantenimiento['fecha_inicio'] ? date('Y-m-d\\TH:i', strtotime($mantenimiento['fecha_inicio'])) : date('Y-m-d\\TH:i')); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fin</label>
                                    <input type="datetime-local" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($mantenimiento['fecha_fin'] ? date('Y-m-d\\TH:i', strtotime($mantenimiento['fecha_fin'])) : ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Duración (hrs)</label>
                                    <input type="number" step="0.1" name="duracion" class="form-control" value="<?php echo htmlspecialchars($mantenimiento['duracion_horas'] ?? ''); ?>" placeholder="Ej. 3.5">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Costo mano de obra</label>
                                    <input type="number" step="0.01" name="costo_mano_obra" class="form-control" value="<?php echo htmlspecialchars($mantenimiento['costo_mano_obra'] ?? '0'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Estado del proceso</label>
                                    <select name="estado" class="form-select">
                                        <?php foreach ($estados as $clave => $label): ?>
                                            <?php if (in_array($clave, ['en_recepcion', 'entregado', 'cerrado']) && $clave !== $mantenimiento['estado']) continue; ?>
                                            <option value="<?php echo $clave; ?>" <?php echo ($mantenimiento['estado'] === $clave) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Observaciones</label>
                                    <textarea name="observaciones" class="form-control" rows="4" placeholder="Describa el trabajo realizado, pruebas efectuadas y hallazgos adicionales."><?php echo htmlspecialchars($mantenimiento['observaciones_finales'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end bg-white">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h2 class="h6 mb-0">Repuestos y materiales</h2>
                            <span class="badge bg-primary">Costo: Q<?php echo number_format((float)($mantenimiento['costo_repuestos'] ?? 0), 2); ?></span>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Agregue hasta cinco repuestos o materiales utilizados durante el mantenimiento.</p>
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <?php $rep = $repuestos[$i] ?? ['nombre_repuesto' => '', 'cantidad' => '', 'costo_unitario' => '']; ?>
                                <div class="border rounded p-2 mb-2">
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Descripción</label>
                                        <input type="text" name="repuestos_nombre[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($rep['nombre_repuesto'] ?? ''); ?>">
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Cantidad</label>
                                            <input type="number" name="repuestos_cantidad[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($rep['cantidad'] ?? ''); ?>" min="0">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Costo unitario</label>
                                            <input type="number" step="0.01" name="repuestos_costo[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($rep['costo_unitario'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Diagnóstico</h2>
                        </div>
                        <div class="card-body">
                            <?php if (!$diagnostico): ?>
                                <p class="text-muted mb-0">Aún no se ha registrado un diagnóstico para este mantenimiento.</p>
                            <?php else: ?>
                                <p class="mb-1"><strong>Falla:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['descripcion_falla'])); ?></p>
                                <p class="mb-1"><strong>Causa:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['causa'] ?? '')); ?></p>
                                <p class="mb-1"><strong>Acción recomendada:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['accion_recomendada'] ?? '')); ?></p>
                                <p class="mb-0"><strong>Fecha:</strong> <?php echo format_date($diagnostico['fecha_diagnostico']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Historial</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($historial)): ?>
                                <p class="text-muted mb-0">Aún no hay registros.</p>
                            <?php else: ?>
                                <ul class="timeline list-unstyled mb-0">
                                    <?php foreach ($historial as $evento): ?>
                                        <li class="mb-3">
                                            <div class="small text-muted"><?php echo format_datetime($evento['fecha_registro']); ?></div>
                                            <strong><?php echo mantenimiento_estado_label($evento['estado']); ?></strong>
                                            <div class="small text-muted"><?php echo htmlspecialchars($evento['nombre_completo'] ?? 'Sistema'); ?></div>
                                            <?php if (!empty($evento['comentario'])): ?>
                                                <div><?php echo nl2br(htmlspecialchars($evento['comentario'])); ?></div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
