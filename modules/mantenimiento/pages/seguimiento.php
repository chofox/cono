<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_auth();
require_any_role(['Supervisor', 'Administrador']);

$folio = sanitize_input($_GET['folio'] ?? '');
if ($folio === '') {
    set_flash_message('Debe seleccionar un folio para continuar.', 'warning');
    header('Location: ' . APP_URL . '/modules/mantenimiento/index.php');
    exit();
}

$mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
if (!$mantenimiento) {
    set_flash_message('No se encontró el mantenimiento solicitado.', 'danger');
    header('Location: ' . APP_URL . '/modules/mantenimiento/index.php');
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
            header('Location: ' . APP_URL . '/modules/mantenimiento/pages/seguimiento.php?folio=' . urlencode($folio));
            exit();
        } catch (Throwable $th) {
            error_log('Error registrando seguimiento: ' . $th->getMessage());
            $errors[] = 'Ocurrió un error guardando el seguimiento.';
        }
    }
}

$mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
$seguimientos = $repository->obtenerSeguimientos((int) $mantenimiento['id']);
$estados = [
    'en_mantenimiento' => 'En mantenimiento',
    'listo_para_entrega' => 'Listo para entrega',
    'entregado' => 'Entregado',
    'cerrado' => 'Cerrado',
];
$csrf_token = generate_csrf_token();

$page_title = 'Seguimiento post-servicio';
$page_subtitle = 'Folio ' . htmlspecialchars($mantenimiento['folio']);
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Mantenimiento', 'href' => APP_URL . '/modules/mantenimiento/index.php'],
    ['label' => 'Seguimiento'],
];
$page_actions = [
    [
        'label' => 'Listado',
        'href' => APP_URL . '/modules/mantenimiento/index.php',
        'icon' => 'fas fa-arrow-left',
        'class' => 'button is-light',
    ],
    [
        'label' => 'Entrega',
        'href' => APP_URL . '/modules/mantenimiento/pages/entrega.php?folio=' . urlencode($mantenimiento['folio']),
        'icon' => 'fas fa-truck',
        'class' => 'button is-light',
    ],
];

include dirname(__DIR__, 3) . '/includes/page_start.php';
?>

<div class="box has-background-light mb-5">
    <div class="level is-mobile">
        <div class="level-left">
            <div>
                <p class="heading">Estado actual</p>
                <span class="tag <?php echo mantenimiento_estado_badge_class($mantenimiento['estado']); ?>">
                    <?php echo mantenimiento_estado_label($mantenimiento['estado']); ?>
                </span>
            </div>
        </div>
        <div class="level-right">
            <div class="has-text-right">
                <p class="heading">Último seguimiento</p>
                <p class="title is-6"><?php echo !empty($seguimientos) ? format_datetime($seguimientos[0]['fecha_seguimiento']) : 'Sin registros'; ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="notification is-danger is-light">
        <strong>No se pudo registrar el seguimiento:</strong>
        <ul class="mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="columns is-variable is-6">
    <div class="column is-12-tablet is-6-desktop">
        <form method="post" class="card">
            <header class="card-header">
                <p class="card-header-title">Registrar seguimiento</p>
            </header>
            <div class="card-content">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="field">
                    <label class="label">Fecha y hora *</label>
                    <div class="control">
                        <input type="datetime-local" name="fecha_seguimiento" class="input" value="<?php echo htmlspecialchars($_POST['fecha_seguimiento'] ?? date('Y-m-d\TH:i')); ?>" required>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Descripción *</label>
                    <div class="control">
                        <textarea name="descripcion" class="textarea" rows="4" required placeholder="Resultados de la verificación, satisfacción del usuario, incidencias posteriores."><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Actualizar estado</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="estado_final">
                                <?php foreach ($estados as $clave => $label): ?>
                                    <option value="<?php echo $clave; ?>" <?php echo ($mantenimiento['estado'] === $clave) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="card-footer">
                <div class="card-footer-item is-justify-content-flex-end">
                    <button type="submit" class="button is-primary">
                        <span class="icon"><i class="fas fa-save"></i></span>
                        <span>Guardar seguimiento</span>
                    </button>
                </div>
            </footer>
        </form>
    </div>
    <div class="column is-12-tablet is-6-desktop">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">Historial de seguimientos</p>
            </header>
            <div class="card-content">
                <?php if (empty($seguimientos)): ?>
                    <p class="has-text-grey">Aún no se han registrado seguimientos para este mantenimiento.</p>
                <?php else: ?>
                    <ul class="timeline">
                        <?php foreach ($seguimientos as $seguimiento): ?>
                            <li class="timeline-item">
                                <p class="is-size-7 has-text-grey"><?php echo format_datetime($seguimiento['fecha_seguimiento']); ?></p>
                                <p class="has-text-weight-semibold"><?php echo htmlspecialchars($seguimiento['supervisor_nombre'] ?? 'Supervisor'); ?></p>
                                <p class="is-size-7 has-text-grey">Estado: <?php echo mantenimiento_estado_label($seguimiento['estado']); ?></p>
                                <p><?php echo nl2br(htmlspecialchars($seguimiento['descripcion'])); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 3) . '/includes/page_end.php'; ?>
