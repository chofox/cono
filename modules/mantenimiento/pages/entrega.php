<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_auth();
require_any_role(['Recepcionista', 'Supervisor', 'Administrador']);

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
            header('Location: ' . APP_URL . '/modules/mantenimiento/pages/entrega.php?folio=' . urlencode($folio));
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
$estadoPublico = generar_url_estado_publico($mantenimiento['public_token']);
$csrf_token = generate_csrf_token();

$page_title = 'Entrega y cierre';
$page_subtitle = 'Folio ' . htmlspecialchars($mantenimiento['folio']);
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Mantenimiento', 'href' => APP_URL . '/modules/mantenimiento/index.php'],
    ['label' => 'Entrega'],
];
$page_actions = [
    [
        'label' => 'Listado',
        'href' => APP_URL . '/modules/mantenimiento/index.php',
        'icon' => 'fas fa-arrow-left',
        'class' => 'button is-light',
    ],
    [
        'label' => 'Ejecución',
        'href' => APP_URL . '/modules/mantenimiento/pages/ejecucion.php?folio=' . urlencode($mantenimiento['folio']),
        'icon' => 'fas fa-tools',
        'class' => 'button is-light',
    ],
    [
        'label' => 'Vista pública',
        'href' => APP_URL . '/modules/mantenimiento/pages/estado.php?folio=' . urlencode($mantenimiento['folio']),
        'icon' => 'fas fa-external-link-alt',
        'class' => 'button is-light',
        'target' => '_blank',
        'rel' => 'noopener',
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
                <p class="heading">Última actualización</p>
                <p class="title is-6"><?php echo format_datetime($mantenimiento['fecha_actualizacion']); ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="notification is-danger is-light">
        <strong>No se pudo registrar la entrega:</strong>
        <ul class="mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <div class="columns is-variable is-6">
        <div class="column is-12-tablet is-7-desktop">
            <div class="card">
                <header class="card-header">
                    <p class="card-header-title">Registrar entrega</p>
                </header>
                <div class="card-content">
                    <div class="columns is-multiline">
                        <div class="column is-12-tablet is-6-desktop">
                            <div class="field">
                                <label class="label">Fecha y hora *</label>
                                <div class="control">
                                    <input type="datetime-local" name="fecha_entrega" class="input" value="<?php echo htmlspecialchars($mantenimiento['fecha_fin'] ? date('Y-m-d\TH:i', strtotime($mantenimiento['fecha_fin'])) : date('Y-m-d\TH:i')); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="column is-12-tablet is-6-desktop">
                            <div class="field">
                                <label class="label">Entregado por</label>
                                <div class="control">
                                    <input type="text" class="input" value="<?php echo htmlspecialchars($current_user['nombre_completo'] ?? ''); ?>" disabled>
                                </div>
                            </div>
                        </div>
                        <div class="column is-12">
                            <div class="field">
                                <label class="label">Recibido por *</label>
                                <div class="control">
                                    <input type="text" name="recibido_por" class="input" value="<?php echo htmlspecialchars($_POST['recibido_por'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="column is-12">
                            <div class="field">
                                <label class="label">Observaciones</label>
                                <div class="control">
                                    <textarea name="observaciones_entrega" class="textarea" rows="3" placeholder="Notas, pruebas realizadas frente al usuario o recomendaciones."><?php echo htmlspecialchars($_POST['observaciones_entrega'] ?? ($mantenimiento['observaciones_finales'] ?? '')); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="column is-12-tablet is-6-desktop">
                            <div class="field">
                                <label class="label">Estado final</label>
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select name="estado">
                                            <option value="entregado" <?php echo ($mantenimiento['estado'] === 'entregado') ? 'selected' : ''; ?>>Entregado</option>
                                            <option value="cerrado" <?php echo ($mantenimiento['estado'] === 'cerrado') ? 'selected' : ''; ?>>Cerrado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="column is-12-tablet is-6-desktop">
                            <div class="field">
                                <label class="label">Enlace de seguimiento</label>
                                <div class="control">
                                    <input type="text" class="input" value="<?php echo htmlspecialchars($estadoPublico); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="column is-12-tablet is-5-desktop">
            <div class="card mb-4">
                <header class="card-header">
                    <p class="card-header-title">Código QR de seguimiento</p>
                </header>
                <div class="card-content has-text-centered">
                    <figure class="image is-128x128 is-inline-block">
                        <img src="<?php echo htmlspecialchars(generar_qr_url($mantenimiento['public_token'])); ?>" alt="QR seguimiento">
                    </figure>
                    <p class="is-size-7 has-text-grey mt-3">Escanee para consultar el estado en línea.</p>
                </div>
            </div>
            <div class="card mb-4">
                <header class="card-header">
                    <p class="card-header-title">Seguimiento post-servicio</p>
                </header>
                <div class="card-content">
                    <?php if (empty($seguimientos)): ?>
                        <p class="has-text-grey">Aún no se han registrado seguimientos.</p>
                    <?php else: ?>
                        <ul class="menu-list">
                            <?php foreach ($seguimientos as $seguimiento): ?>
                                <li class="mb-3">
                                    <p class="has-text-weight-semibold"><?php echo htmlspecialchars($seguimiento['supervisor_nombre'] ?? 'Supervisor'); ?></p>
                                    <p class="is-size-7 has-text-grey"><?php echo format_datetime($seguimiento['fecha_seguimiento']); ?></p>
                                    <p class="is-size-7 has-text-grey">Estado: <?php echo mantenimiento_estado_label($seguimiento['estado']); ?></p>
                                    <p><?php echo nl2br(htmlspecialchars($seguimiento['descripcion'])); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <header class="card-header">
                    <p class="card-header-title">Historial de estados</p>
                </header>
                <div class="card-content">
                    <?php if (empty($historial)): ?>
                        <p class="has-text-grey">Aún no hay eventos registrados.</p>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach ($historial as $evento): ?>
                                <li class="timeline-item">
                                    <p class="is-size-7 has-text-grey"><?php echo format_datetime($evento['fecha_registro']); ?></p>
                                    <p class="has-text-weight-semibold"><?php echo mantenimiento_estado_label($evento['estado']); ?></p>
                                    <p class="is-size-7 has-text-grey"><?php echo htmlspecialchars($evento['nombre_completo'] ?? 'Sistema'); ?></p>
                                    <?php if (!empty($evento['comentario'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($evento['comentario'])); ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="field is-grouped is-justify-content-flex-end mt-5">
        <div class="control">
            <button type="submit" class="button is-success">
                <span class="icon"><i class="fas fa-check"></i></span>
                <span>Registrar entrega</span>
            </button>
        </div>
    </div>
</form>

<?php include dirname(__DIR__, 3) . '/includes/page_end.php'; ?>
