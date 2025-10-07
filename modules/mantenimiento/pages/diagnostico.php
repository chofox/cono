<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/classes/Auth.php';
require_once dirname(__DIR__) . '/services/MantenimientoRepository.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

require_auth();
require_any_role(['Supervisor', 'Técnico', 'Administrador']);

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
$historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);
$tecnicos = get_users_by_role('Técnico');
$supervisores = get_users_by_role('Supervisor');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido.';
    }

    $tecnicoId = (int) ($_POST['tecnico_id'] ?? 0);
    $descripcion = trim($_POST['descripcion_falla'] ?? '');
    $causa = trim($_POST['causa'] ?? '');
    $accion = trim($_POST['accion_recomendada'] ?? '');
    $fechaDiagnostico = $_POST['fecha_diagnostico'] ?? date('Y-m-d');
    $aprobadoPor = !empty($_POST['aprobado_por']) ? (int) $_POST['aprobado_por'] : null;
    $observacionesSupervisor = trim($_POST['observaciones_supervisor'] ?? '');

    if ($tecnicoId <= 0) {
        $errors[] = 'Debe seleccionar un técnico responsable.';
    }

    if ($descripcion === '') {
        $errors[] = 'Debe registrar la descripción de la falla.';
    }

    if (!validate_date($fechaDiagnostico, 'Y-m-d')) {
        $errors[] = 'La fecha de diagnóstico no es válida.';
    }

    if (empty($errors)) {
        try {
            if ($mantenimiento['tecnico_id'] != $tecnicoId) {
                $repository->asignarTecnico((int) $mantenimiento['id'], $tecnicoId, $current_user['id']);
                $mantenimiento['tecnico_id'] = $tecnicoId;
            }

            $repository->registrarDiagnostico((int) $mantenimiento['id'], [
                'tecnico_id' => $tecnicoId,
                'descripcion_falla' => $descripcion,
                'causa' => $causa,
                'accion_recomendada' => $accion,
                'fecha_diagnostico' => $fechaDiagnostico,
                'aprobado_por' => $aprobadoPor,
                'observaciones_supervisor' => $observacionesSupervisor,
            ]);

            set_flash_message('Diagnóstico registrado correctamente.', 'success');
            header('Location: ' . APP_URL . '/modules/mantenimiento/pages/ejecucion.php?folio=' . urlencode($folio));
            exit();
        } catch (Throwable $th) {
            error_log('Error guardando diagnóstico: ' . $th->getMessage());
            $errors[] = 'Ocurrió un error al guardar el diagnóstico.';
        }
    }
}

$csrf_token = generate_csrf_token();
$page_title = 'Diagnóstico Técnico';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h3 mb-0">Diagnóstico técnico</h1>
                    <p class="text-muted mb-0">Folio <?php echo htmlspecialchars($mantenimiento['folio']); ?> - <?php echo htmlspecialchars($mantenimiento['equipo_descripcion']); ?></p>
                </div>
                <div class="btn-group">
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Regresar
                    </a>
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/estado.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" class="btn btn-outline-secondary" target="_blank">
                        <i class="fas fa-external-link-alt me-2"></i>Vista pública
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
                <div class="col-lg-7">
                    <form method="post" class="card">
                        <div class="card-header bg-white">
                            <h2 class="h5 mb-0">Detalle del diagnóstico</h2>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="mb-3">
                                <label class="form-label">Técnico responsable *</label>
                                <select name="tecnico_id" class="form-select" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($tecnicos as $tecnico): ?>
                                        <option value="<?php echo $tecnico['id']; ?>" <?php echo (($diagnostico['tecnico_id'] ?? $mantenimiento['tecnico_id']) == $tecnico['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción de la falla *</label>
                                <textarea name="descripcion_falla" class="form-control" rows="4" required><?php echo htmlspecialchars($diagnostico['descripcion_falla'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Causa identificada</label>
                                <textarea name="causa" class="form-control" rows="3"><?php echo htmlspecialchars($diagnostico['causa'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Acción recomendada *</label>
                                <textarea name="accion_recomendada" class="form-control" rows="3" required><?php echo htmlspecialchars($diagnostico['accion_recomendada'] ?? ''); ?></textarea>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Fecha de diagnóstico *</label>
                                    <input type="date" name="fecha_diagnostico" class="form-control" value="<?php echo htmlspecialchars($diagnostico['fecha_diagnostico'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Supervisor que aprueba</label>
                                    <select name="aprobado_por" class="form-select">
                                        <option value="">Por asignar</option>
                                        <?php foreach ($supervisores as $supervisor): ?>
                                            <option value="<?php echo $supervisor['id']; ?>" <?php echo (($diagnostico['aprobado_por'] ?? $mantenimiento['supervisor_id']) == $supervisor['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supervisor['nombre_completo']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Observaciones del supervisor</label>
                                <textarea name="observaciones_supervisor" class="form-control" rows="3"><?php echo htmlspecialchars($diagnostico['observaciones_supervisor'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="card-footer text-end bg-white">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar diagnóstico
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Información del equipo</h2>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Código</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($mantenimiento['codigo']); ?></dd>
                                <dt class="col-sm-5">Descripción</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($mantenimiento['equipo_descripcion']); ?></dd>
                                <dt class="col-sm-5">Serie</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($mantenimiento['serie'] ?? 'N/D'); ?></dd>
                                <dt class="col-sm-5">Ubicación</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($mantenimiento['ubicacion'] ?? ''); ?></dd>
                                <dt class="col-sm-5">Tipo</dt>
                                <dd class="col-sm-7"><?php echo mantenimiento_tipo_options()[$mantenimiento['tipo_mantenimiento']] ?? $mantenimiento['tipo_mantenimiento']; ?></dd>
                                <dt class="col-sm-5">Recepcionista</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($mantenimiento['recepcionista_nombre'] ?? ''); ?></dd>
                            </dl>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Historial de estados</h2>
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
