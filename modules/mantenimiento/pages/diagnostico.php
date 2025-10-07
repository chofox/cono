<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_auth();
require_any_role(['Supervisor', 'Técnico', 'Administrador']);

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

$diagnostico = $repository->obtenerDiagnostico((int) $mantenimiento['id']);
$historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);
$tecnicos = get_users_by_role('Técnico');
$supervisores = get_users_by_role('Supervisor');
$tipos = mantenimiento_tipo_options();
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
            if ((int) ($mantenimiento['tecnico_id'] ?? 0) !== $tecnicoId) {
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
$page_title = 'Diagnóstico técnico';
$page_subtitle = 'Folio ' . htmlspecialchars($mantenimiento['folio']) . ' — ' . htmlspecialchars($mantenimiento['equipo_descripcion']);
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Mantenimiento', 'href' => APP_URL . '/modules/mantenimiento/index.php'],
    ['label' => 'Diagnóstico'],
];
$page_actions = [
    [
        'label' => 'Volver al listado',
        'href' => APP_URL . '/modules/mantenimiento/index.php',
        'icon' => 'fas fa-arrow-left',
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

<?php if (!empty($errors)): ?>
    <div class="notification is-danger is-light">
        <strong>No se pudo guardar el diagnóstico:</strong>
        <ul class="mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="columns is-variable is-6">
    <div class="column is-12-tablet is-7-desktop">
        <form method="post" class="card">
            <header class="card-header">
                <p class="card-header-title">Detalle del diagnóstico</p>
            </header>
            <div class="card-content">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="field">
                    <label class="label">Técnico responsable *</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="tecnico_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <?php $seleccionado = ($diagnostico['tecnico_id'] ?? $mantenimiento['tecnico_id']) == $tecnico['id']; ?>
                                    <option value="<?php echo $tecnico['id']; ?>" <?php echo $seleccionado ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Descripción de la falla *</label>
                    <div class="control">
                        <textarea name="descripcion_falla" class="textarea" rows="4" required><?php echo htmlspecialchars($diagnostico['descripcion_falla'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Causa identificada</label>
                    <div class="control">
                        <textarea name="causa" class="textarea" rows="3"><?php echo htmlspecialchars($diagnostico['causa'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Acción recomendada *</label>
                    <div class="control">
                        <textarea name="accion_recomendada" class="textarea" rows="3" required><?php echo htmlspecialchars($diagnostico['accion_recomendada'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="columns">
                    <div class="column is-6">
                        <div class="field">
                            <label class="label">Fecha de diagnóstico *</label>
                            <div class="control">
                                <input type="date" name="fecha_diagnostico" class="input" value="<?php echo htmlspecialchars($diagnostico['fecha_diagnostico'] ?? date('Y-m-d')); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="column is-6">
                        <div class="field">
                            <label class="label">Supervisor que aprueba</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="aprobado_por">
                                        <option value="">Por asignar</option>
                                        <?php foreach ($supervisores as $supervisor): ?>
                                            <?php $seleccionado = ($diagnostico['aprobado_por'] ?? $mantenimiento['supervisor_id']) == $supervisor['id']; ?>
                                            <option value="<?php echo $supervisor['id']; ?>" <?php echo $seleccionado ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supervisor['nombre_completo']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Observaciones del supervisor</label>
                    <div class="control">
                        <textarea name="observaciones_supervisor" class="textarea" rows="3"><?php echo htmlspecialchars($diagnostico['observaciones_supervisor'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            <footer class="card-footer">
                <div class="card-footer-item is-justify-content-flex-end">
                    <button type="submit" class="button is-primary">
                        <span class="icon"><i class="fas fa-save"></i></span>
                        <span>Guardar diagnóstico</span>
                    </button>
                </div>
            </footer>
        </form>
    </div>
    <div class="column is-12-tablet is-5-desktop">
        <div class="card mb-5">
            <header class="card-header">
                <p class="card-header-title">Información del equipo</p>
            </header>
            <div class="card-content">
                <div class="content is-small">
                    <p><strong>Código:</strong> <?php echo htmlspecialchars($mantenimiento['codigo']); ?></p>
                    <p><strong>Descripción:</strong> <?php echo htmlspecialchars($mantenimiento['equipo_descripcion']); ?></p>
                    <p><strong>Serie:</strong> <?php echo htmlspecialchars($mantenimiento['serie'] ?? 'N/D'); ?></p>
                    <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($mantenimiento['ubicacion'] ?? ''); ?></p>
                    <p><strong>Tipo:</strong> <?php echo $tipos[$mantenimiento['tipo_mantenimiento']] ?? $mantenimiento['tipo_mantenimiento']; ?></p>
                    <p><strong>Recepcionista:</strong> <?php echo htmlspecialchars($mantenimiento['recepcionista_nombre'] ?? ''); ?></p>
                </div>
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

<?php include dirname(__DIR__, 3) . '/includes/page_end.php'; ?>
