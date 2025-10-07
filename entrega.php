<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'classes/MantenimientoRepository.php';
require_once 'includes/functions.php';

require_auth();
require_any_role(['Recepcionista', 'Supervisor', 'Administrador']);

$auth = new Auth();
$current_user = $auth->getCurrentUser();
$repository = new MantenimientoRepository();

$folio = $_GET['folio'] ?? '';
if ($folio === '') {
    header('Location: mantenimientos.php?error=folio_requerido');
    exit();
}

$mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
if (!$mantenimiento) {
    set_flash_message('No se encontró el mantenimiento solicitado.', 'danger');
    header('Location: mantenimientos.php');
    exit();
}

$historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);
$seguimientos = $repository->obtenerSeguimientos((int) $mantenimiento['id']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido.';
    }

    $fechaEntrega = $_POST['fecha_entrega'] ?? '';
    $recibidoPor = trim($_POST['recibido_por'] ?? '');
    $observaciones = trim($_POST['observaciones_entrega'] ?? '');
    $estadoFinal = $_POST['estado'] ?? 'entregado';

    if (!validate_date($fechaEntrega, 'Y-m-d\TH:i')) {
        $errors[] = 'Debe indicar una fecha y hora válidas de entrega.';
    }

    if ($recibidoPor === '') {
        $errors[] = 'Debe especificar quién recibe el equipo.';
    }

    if (!in_array($estadoFinal, ['entregado', 'cerrado'], true)) {
        $errors[] = 'Estado final inválido.';
    }

    if (empty($errors)) {
        try {
            $qrUrl = generar_qr_url($mantenimiento['public_token']);
            $repository->registrarEntrega((int) $mantenimiento['id'], [
                'fecha_entrega' => str_replace('T', ' ', $fechaEntrega),
                'observaciones_entrega' => $observaciones,
                'entregado_por' => $current_user['id'],
                'recibido_por' => $recibidoPor,
                'qr_code_url' => $qrUrl,
                'estado' => $estadoFinal,
            ]);

            set_flash_message('Entrega registrada correctamente.', 'success');
            header('Location: entrega.php?folio=' . urlencode($folio));
            exit();
        } catch (Throwable $th) {
            error_log('Error registrando entrega: ' . $th->getMessage());
            $errors[] = 'Ocurrió un error al registrar la entrega.';
        }
    }
}

$mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
$historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);
$seguimientos = $repository->obtenerSeguimientos((int) $mantenimiento['id']);

$csrf_token = generate_csrf_token();
$page_title = 'Entrega de Equipo';
$estadoPublico = generar_url_estado_publico($mantenimiento['public_token']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrega - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h3 mb-0">Entrega y cierre</h1>
                    <p class="text-muted mb-0">Folio <?php echo htmlspecialchars($mantenimiento['folio']); ?> — Estado: <span class="badge bg-<?php echo mantenimiento_estado_badge_class($mantenimiento['estado']); ?>"><?php echo mantenimiento_estado_label($mantenimiento['estado']); ?></span></p>
                </div>
                <div class="btn-group">
                    <a href="mantenimientos.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Regresar
                    </a>
                    <a href="mantenimiento.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-tools me-2"></i>Mantenimiento
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
                            <h2 class="h5 mb-0">Registrar entrega</h2>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Fecha y hora *</label>
                                    <input type="datetime-local" name="fecha_entrega" class="form-control" value="<?php echo htmlspecialchars($mantenimiento['fecha_fin'] ? date('Y-m-d\\TH:i', strtotime($mantenimiento['fecha_fin'])) : date('Y-m-d\\TH:i')); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Entregado por</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['nombre_completo'] ?? ''); ?>" disabled>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Recibido por *</label>
                                    <input type="text" name="recibido_por" class="form-control" value="<?php echo htmlspecialchars($_POST['recibido_por'] ?? ''); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Observaciones</label>
                                    <textarea name="observaciones_entrega" class="form-control" rows="3" placeholder="Notas, pruebas realizadas frente al usuario o recomendaciones. "><?php echo htmlspecialchars($_POST['observaciones_entrega'] ?? ($mantenimiento['observaciones_finales'] ?? '')); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Estado final</label>
                                    <select name="estado" class="form-select">
                                    <option value="entregado" <?php echo ($mantenimiento['estado'] === 'entregado') ? 'selected' : ''; ?>>Entregado</option>
                                    <option value="cerrado" <?php echo ($mantenimiento['estado'] === 'cerrado') ? 'selected' : ''; ?>>Cerrado</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Enlace de seguimiento</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($estadoPublico); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end bg-white">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-2"></i>Registrar entrega
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="card mb-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h2 class="h6 mb-0">Código QR de seguimiento</h2>
                            <span class="badge bg-secondary">Folio <?php echo htmlspecialchars($mantenimiento['folio']); ?></span>
                        </div>
                        <div class="card-body text-center">
                            <img src="<?php echo htmlspecialchars(generar_qr_url($mantenimiento['public_token'])); ?>" alt="QR seguimiento" width="180" height="180" class="mb-2">
                            <p class="small mb-0">Escanee para consultar el estado en línea.</p>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Seguimiento post-servicio</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($seguimientos)): ?>
                                <p class="text-muted mb-0">Aún no se han registrado seguimientos.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($seguimientos as $seguimiento): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <span class="fw-semibold"><?php echo htmlspecialchars($seguimiento['supervisor_nombre'] ?? 'Supervisor'); ?></span>
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
