<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_auth();
require_any_role(['Técnico', 'Supervisor', 'Administrador']);

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
$repuestos = $repository->obtenerRepuestos((int) $mantenimiento['id']);
$conocimientoAsociado = $repository->obtenerConocimientoAsociado((int) $mantenimiento['id']);
$insumosCatalogo = [];
try {
    $database = new Database();
    $conn = $database->getConnection();
    $stmtInsumos = $conn->prepare("SELECT id, codigo, nombre, unidad_medida FROM insumos WHERE activo = 1 ORDER BY nombre");
    $stmtInsumos->execute();
    $insumosCatalogo = $stmtInsumos->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $th) {
    error_log('No se pudieron cargar los insumos para repuestos: ' . $th->getMessage());
    $insumosCatalogo = [];
}
$insumosIndice = [];
foreach ($insumosCatalogo as $insumo) {
    $insumosIndice[(int) $insumo['id']] = $insumo;
}
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
    if (!empty($_POST['repuestos_insumo_id']) && is_array($_POST['repuestos_insumo_id'])) {
        foreach ($_POST['repuestos_insumo_id'] as $index => $insumoIdRaw) {
            $insumoId = (int) $insumoIdRaw;
            $cantidad = (float) ($_POST['repuestos_cantidad'][$index] ?? 0);
            $nota = trim($_POST['repuestos_observaciones'][$index] ?? '');

            if ($insumoId <= 0 || $cantidad <= 0) {
                continue;
            }

            if (!isset($insumosIndice[$insumoId])) {
                $errors[] = 'Uno de los repuestos seleccionados no es válido.';
                continue;
            }

            $repuestosRegistrados[] = [
                'insumo_id' => $insumoId,
                'cantidad' => $cantidad,
                'observaciones' => $nota !== '' ? $nota : null,
            ];
        }
    }
    $estadosDisponibles = mantenimiento_estados();

    if (!array_key_exists($estadoDestino, $estadosDisponibles)) {
        $errors[] = 'El estado seleccionado no es válido.';
    }

    if (empty($errors)) {
        try {
            $repository->registrarMantenimiento((int) $mantenimiento['id'], [
                'fecha_inicio' => str_replace('T', ' ', $fechaInicio),
                'fecha_fin' => $fechaFin ? str_replace('T', ' ', $fechaFin) : null,
                'duracion' => $duracion !== '' ? $duracion : null,
                'observaciones' => $observaciones,
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
$conocimientoAsociado = $repository->obtenerConocimientoAsociado((int) $mantenimiento['id']);
$historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);
$estados = mantenimiento_estados();
$csrf_token = generate_csrf_token();

$page_title = 'Ejecución del mantenimiento';
$page_subtitle = 'Folio ' . htmlspecialchars($mantenimiento['folio']);
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Mantenimiento', 'href' => APP_URL . '/modules/mantenimiento/index.php'],
    ['label' => 'Ejecución'],
];
$page_actions = [
    [
        'label' => 'Listado',
        'href' => APP_URL . '/modules/mantenimiento/index.php',
        'icon' => 'fas fa-arrow-left',
        'class' => 'button is-light',
    ],
    [
        'label' => 'Diagnóstico',
        'href' => APP_URL . '/modules/mantenimiento/pages/diagnostico.php?folio=' . urlencode($mantenimiento['folio']),
        'icon' => 'fas fa-stethoscope',
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
                <p class="heading">Repuestos registrados</p>
                <p class="title is-6"><?php echo count($repuestos); ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="notification is-danger is-light">
        <strong>No se pudo guardar la información:</strong>
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
        <div class="column is-12-tablet is-8-desktop">
            <div class="card">
                <header class="card-header">
                    <p class="card-header-title">Datos de ejecución</p>
                </header>
                <div class="card-content">
                    <div class="columns is-multiline">
                        <div class="column is-12-tablet is-6-desktop">
                            <div class="field">
                                <label class="label">Inicio *</label>
                                <div class="control">
                                    <input type="datetime-local" name="fecha_inicio" class="input" value="<?php echo htmlspecialchars($mantenimiento['fecha_inicio'] ? date('Y-m-d\TH:i', strtotime($mantenimiento['fecha_inicio'])) : date('Y-m-d\TH:i')); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="column is-12-tablet is-6-desktop">
                            <div class="field">
                                <label class="label">Fin</label>
                                <div class="control">
                                    <input type="datetime-local" name="fecha_fin" class="input" value="<?php echo htmlspecialchars($mantenimiento['fecha_fin'] ? date('Y-m-d\TH:i', strtotime($mantenimiento['fecha_fin'])) : ''); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="column is-12-tablet is-4-desktop">
                            <div class="field">
                                <label class="label">Duración (hrs)</label>
                                <div class="control">
                                    <input type="number" step="0.1" name="duracion" class="input" value="<?php echo htmlspecialchars($mantenimiento['duracion_horas'] ?? ''); ?>" placeholder="Ej. 3.5">
                                </div>
                            </div>
                        </div>
                        <div class="column is-12-tablet is-4-desktop">
                            <div class="field">
                                <label class="label">Estado del proceso</label>
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select name="estado">
                                            <?php foreach ($estados as $clave => $label): ?>
                                                <?php if (in_array($clave, ['en_recepcion', 'entregado', 'cerrado']) && $clave !== $mantenimiento['estado']) continue; ?>
                                                <option value="<?php echo $clave; ?>" <?php echo ($mantenimiento['estado'] === $clave) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="column is-12">
                            <div class="field">
                                <label class="label">Observaciones</label>
                                <div class="control">
                                    <textarea name="observaciones" class="textarea" rows="4" placeholder="Describa el trabajo realizado, pruebas efectuadas y hallazgos adicionales."><?php echo htmlspecialchars($mantenimiento['observaciones_finales'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="column is-12-tablet is-4-desktop">
            <div class="card mb-4">
                <header class="card-header">
                    <p class="card-header-title">Repuestos y materiales</p>
                    <span class="card-header-icon has-text-primary">
                        <span class="icon"><i class="fas fa-toolbox"></i></span>
                    </span>
                </header>
                <div class="card-content">
                    <p class="is-size-7 has-text-grey">Registre hasta cinco insumos de inventario utilizados en este mantenimiento. Se sincronizarán con un conocimiento en borrador.</p>
                    <?php $maxRepuestos = 5; ?>
                    <?php for ($i = 0; $i < $maxRepuestos; $i++): ?>
                        <?php $rep = $repuestos[$i] ?? ['insumo_id' => '', 'cantidad' => '', 'observaciones' => '']; ?>
                        <div class="box is-shadowless has-background-white-bis mb-3">
                            <div class="field">
                                <label class="label is-size-7">Insumo</label>
                                <div class="control">
                                    <div class="select is-small is-fullwidth">
                                        <select name="repuestos_insumo_id[]">
                                            <option value="">Seleccione</option>
                                            <?php foreach ($insumosCatalogo as $insumo): ?>
                                                <option value="<?php echo (int) $insumo['id']; ?>" <?php echo ((int)($rep['insumo_id'] ?? 0) === (int) $insumo['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($insumo['nombre']); ?>
                                                    <?php if (!empty($insumo['codigo'])): ?>
                                                        (<?php echo htmlspecialchars($insumo['codigo']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="columns is-gapless">
                                <div class="column pr-2">
                                    <div class="field">
                                        <label class="label is-size-7">Cantidad</label>
                                        <div class="control">
                                            <input type="number" step="0.01" name="repuestos_cantidad[]" class="input is-small" value="<?php echo htmlspecialchars($rep['cantidad'] ?? ''); ?>" min="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="column pl-2">
                                    <div class="field">
                                        <label class="label is-size-7">Observaciones</label>
                                        <div class="control">
                                            <input type="text" name="repuestos_observaciones[]" class="input is-small" value="<?php echo htmlspecialchars($rep['observaciones'] ?? ''); ?>" placeholder="Opcional">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                    <?php if ($conocimientoAsociado): ?>
                        <div class="notification is-link is-light is-size-7">
                            <span class="icon"><i class="fas fa-scroll"></i></span>
                            <span>
                                Conocimiento borrador sincronizado:
                                <a class="has-text-weight-semibold" href="<?php echo APP_URL . '/modules/conocimientos/pages/editar.php?id=' . (int) $conocimientoAsociado['conocimiento_id']; ?>">
                                    #<?php echo htmlspecialchars($conocimientoAsociado['numero_conocimiento'] ?? ''); ?>
                                </a>
                                (estado: <?php echo htmlspecialchars($conocimientoAsociado['estado']); ?>).
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card mb-4">
                <header class="card-header">
                    <p class="card-header-title">Diagnóstico</p>
                </header>
                <div class="card-content">
                    <?php if (!$diagnostico): ?>
                        <p class="has-text-grey">Aún no se ha registrado un diagnóstico para este mantenimiento.</p>
                    <?php else: ?>
                        <p class="mb-2"><strong>Falla:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['descripcion_falla'])); ?></p>
                        <p class="mb-2"><strong>Causa:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['causa'] ?? '')); ?></p>
                        <p class="mb-2"><strong>Acción recomendada:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['accion_recomendada'] ?? '')); ?></p>
                        <p class="is-size-7 has-text-grey">Registrado el <?php echo format_date($diagnostico['fecha_diagnostico']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <header class="card-header">
                    <p class="card-header-title">Historial</p>
                </header>
                <div class="card-content">
                    <?php if (empty($historial)): ?>
                        <p class="has-text-grey">Aún no hay registros.</p>
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
            <button type="submit" class="button is-primary">
                <span class="icon"><i class="fas fa-save"></i></span>
                <span>Guardar cambios</span>
            </button>
        </div>
    </div>
</form>

<?php include dirname(__DIR__, 3) . '/includes/page_end.php'; ?>
