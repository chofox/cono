<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'classes/MantenimientoRepository.php';
require_once 'includes/functions.php';

require_auth();
require_any_role(['Supervisor', 'Administrador']);

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

$seguimientos = $repository->obtenerSeguimientos((int) $mantenimiento['id']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido.';
    }

    $descripcion = trim($_POST['descripcion'] ?? '');
    $estadoFinal = $_POST['estado_final'] ?? 'cerrado';
    $fechaSeguimiento = $_POST['fecha_seguimiento'] ?? date('Y-m-d\TH:i');

    if ($descripcion === '') {
        $errors[] = 'Debe ingresar la descripción del seguimiento.';
    }

    if (!validate_date($fechaSeguimiento, 'Y-m-d\TH:i')) {
        $errors[] = 'La fecha del seguimiento es inválida.';
    }

    $estadosPermitidos = ['en_mantenimiento', 'listo_para_entrega', 'entregado', 'cerrado'];
    if (!in_array($estadoFinal, $estadosPermitidos, true)) {
        $errors[] = 'Estado destino no permitido para seguimiento.';
    }

    if (empty($errors)) {
        try {
            $repository->registrarSeguimiento((int) $mantenimiento['id'], [
                'supervisor_id' => $current_user['id'],
                'descripcion' => $descripcion,
                'fecha' => str_replace('T', ' ', $fechaSeguimiento),
                'estado' => $estadoFinal,
                'estado_final' => $estadoFinal,
            ]);

            set_flash_message('Seguimiento registrado correctamente.', 'success');
            header('Location: seguimiento.php?folio=' . urlencode($folio));
            exit();
        } catch (Throwable $th) {
            error_log('Error registrando seguimiento: ' . $th->getMessage());
            $errors[] = 'Ocurrió un error guardando el seguimiento.';
        }
    }
}

$mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
$seguimientos = $repository->obtenerSeguimientos((int) $mantenimiento['id']);

$csrf_token = generate_csrf_token();
$page_title = 'Seguimiento';
$estados = ['en_mantenimiento' => 'En mantenimiento', 'listo_para_entrega' => 'Listo para entrega', 'entregado' => 'Entregado', 'cerrado' => 'Cerrado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h3 mb-0">Seguimiento post-servicio</h1>
                    <p class="text-muted mb-0">Folio <?php echo htmlspecialchars($mantenimiento['folio']); ?> — Estado: <span class="badge bg-<?php echo mantenimiento_estado_badge_class($mantenimiento['estado']); ?>"><?php echo mantenimiento_estado_label($mantenimiento['estado']); ?></span></p>
                </div>
                <div class="btn-group">
                    <a href="mantenimientos.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Regresar
                    </a>
                    <a href="entrega.php?folio=<?php echo urlencode($mantenimiento['folio']); ?>" class="btn btn-outline-secondary">
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
                <div class="col-lg-6">
                    <form method="post" class="card">
                        <div class="card-header bg-white">
                            <h2 class="h5 mb-0">Registrar seguimiento</h2>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="mb-3">
                                <label class="form-label">Fecha y hora *</label>
                                <input type="datetime-local" name="fecha_seguimiento" class="form-control" value="<?php echo htmlspecialchars($_POST['fecha_seguimiento'] ?? date('Y-m-d\\TH:i')); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción *</label>
                                <textarea name="descripcion" class="form-control" rows="4" required placeholder="Resultados de la verificación, satisfacción del usuario, incidencias posteriores."><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Actualizar estado</label>
                                <select name="estado_final" class="form-select">
                                    <?php foreach ($estados as $clave => $label): ?>
                                        <option value="<?php echo $clave; ?>" <?php echo (($mantenimiento['estado'] === $clave) ? 'selected' : ''); ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer text-end bg-white">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar seguimiento
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Historial de seguimientos</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($seguimientos)): ?>
                                <p class="text-muted mb-0">Aún no se han registrado seguimientos para este mantenimiento.</p>
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
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
