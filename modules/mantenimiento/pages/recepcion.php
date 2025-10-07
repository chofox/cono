<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/classes/Auth.php';
require_once dirname(__DIR__) . '/services/MantenimientoRepository.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

require_auth();
require_any_role(['Recepcionista', 'Administrador']);

$auth = new Auth();
$current_user = $auth->getCurrentUser();
$repository = new MantenimientoRepository();
$tipos = mantenimiento_tipo_options();
$tecnicos = get_users_by_role('Técnico');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido. Intente nuevamente.';
    }

    $codigo = sanitize_input($_POST['codigo_equipo'] ?? '');
    $descripcion = sanitize_input($_POST['descripcion_equipo'] ?? '');
    $serie = sanitize_input($_POST['serie_equipo'] ?? '');
    $ubicacion = sanitize_input($_POST['ubicacion'] ?? '');
    $usuarioEntrega = sanitize_input($_POST['usuario_entrega'] ?? '');
    $tipo = sanitize_input($_POST['tipo_mantenimiento'] ?? '');
    $fechaRecepcion = $_POST['fecha_recepcion'] ?? date('Y-m-d');
    $observaciones = sanitize_input($_POST['observaciones_recepcion'] ?? '');
    $tecnicoId = !empty($_POST['tecnico_id']) ? (int) $_POST['tecnico_id'] : null;

    if ($codigo === '' || $descripcion === '') {
        $errors[] = 'El código y la descripción del equipo son obligatorios.';
    }

    if (!array_key_exists($tipo, $tipos)) {
        $errors[] = 'Debe seleccionar un tipo de mantenimiento válido.';
    }

    if (!validate_date($fechaRecepcion, 'Y-m-d')) {
        $errors[] = 'La fecha de recepción es inválida.';
    }

    if (empty($errors)) {
        try {
            $resultado = $repository->registrarRecepcion([
                'codigo_equipo' => $codigo,
                'descripcion_equipo' => $descripcion,
                'serie_equipo' => $serie,
                'ubicacion' => $ubicacion,
                'usuario_entrega' => $usuarioEntrega,
                'tipo_mantenimiento' => $tipo,
                'fecha_recepcion' => $fechaRecepcion,
                'observaciones_recepcion' => $observaciones,
                'recepcionista_id' => $current_user['id'],
            ]);

            if ($tecnicoId) {
                $repository->asignarTecnico($resultado['id'], $tecnicoId, $current_user['id']);
            }

            $qrUrl = generar_qr_url($resultado['token']);
            set_flash_message('Equipo registrado con folio ' . $resultado['folio'] . '. Código QR generado automáticamente.', 'success');

            $_SESSION['ultimo_mantenimiento_publico'] = [
                'folio' => $resultado['folio'],
                'token' => $resultado['token'],
                'qr' => $qrUrl,
            ];

            header('Location: ' . APP_URL . '/modules/mantenimiento/pages/diagnostico.php?folio=' . urlencode($resultado['folio']));
            exit();
        } catch (Throwable $th) {
            error_log('Error registrando mantenimiento: ' . $th->getMessage());
            $errors[] = 'Ocurrió un error registrando la recepción. Intente nuevamente.';
        }
    }
}

$csrf_token = generate_csrf_token();
$page_title = 'Recepción de Equipo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recepción de Equipo - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h3 mb-0">Registrar recepción de equipo</h1>
                    <p class="text-muted">Ingrese los datos básicos para iniciar el proceso de mantenimiento.</p>
                </div>
                <a href="<?php echo APP_URL; ?>/modules/mantenimiento/index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver al listado
                </a>
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

            <form method="post" class="card">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Datos de recepción</h2>
                </div>
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Código del equipo *</label>
                            <input type="text" name="codigo_equipo" class="form-control" value="<?php echo htmlspecialchars($_POST['codigo_equipo'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Serie</label>
                            <input type="text" name="serie_equipo" class="form-control" value="<?php echo htmlspecialchars($_POST['serie_equipo'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ubicación</label>
                            <input type="text" name="ubicacion" class="form-control" value="<?php echo htmlspecialchars($_POST['ubicacion'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descripción del equipo *</label>
                            <input type="text" name="descripcion_equipo" class="form-control" value="<?php echo htmlspecialchars($_POST['descripcion_equipo'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario que entrega</label>
                            <input type="text" name="usuario_entrega" class="form-control" value="<?php echo htmlspecialchars($_POST['usuario_entrega'] ?? ''); ?>" placeholder="Nombre del colaborador">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de mantenimiento *</label>
                            <select name="tipo_mantenimiento" class="form-select" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($tipos as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (($_POST['tipo_mantenimiento'] ?? '') === $key) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de recepción *</label>
                            <input type="date" name="fecha_recepcion" class="form-control" value="<?php echo htmlspecialchars($_POST['fecha_recepcion'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Asignar técnico</label>
                            <select name="tecnico_id" class="form-select">
                                <option value="">Seleccionar después</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id']; ?>" <?php echo (($_POST['tecnico_id'] ?? '') == $tecnico['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observaciones iniciales</label>
                            <textarea name="observaciones_recepcion" rows="3" class="form-control" placeholder="Describe brevemente el estado del equipo o accesorios entregados."><?php echo htmlspecialchars($_POST['observaciones_recepcion'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end bg-white">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Registrar ingreso
                    </button>
                </div>
            </form>

            <?php if (!empty($_SESSION['ultimo_mantenimiento_publico'])): ?>
                <?php $ultimo = $_SESSION['ultimo_mantenimiento_publico']; ?>
                <div class="alert alert-info mt-4">
                    <div class="d-flex align-items-center">
                        <div>
                            <strong>Último folio generado:</strong> <?php echo htmlspecialchars($ultimo['folio']); ?><br>
                            <a href="<?php echo htmlspecialchars(generar_url_estado_publico($ultimo['token'])); ?>" target="_blank">Ver estado público</a>
                        </div>
                        <div class="ms-auto text-center">
                            <img src="<?php echo htmlspecialchars($ultimo['qr']); ?>" alt="QR seguimiento" width="120" height="120">
                            <div class="small text-muted">Escanee para seguimiento</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
