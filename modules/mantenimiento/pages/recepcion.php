<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_auth();
require_any_role(['Recepcionista', 'Administrador']);

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

$page_title = 'Recepción de equipo';
$page_subtitle = 'Ingrese los datos básicos para iniciar el proceso de mantenimiento.';
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Mantenimiento', 'href' => APP_URL . '/modules/mantenimiento/index.php'],
    ['label' => 'Recepción'],
];
$page_actions = [
    [
        'label' => 'Volver al listado',
        'href' => APP_URL . '/modules/mantenimiento/index.php',
        'icon' => 'fas fa-arrow-left',
        'class' => 'button is-light',
    ],
];

include dirname(__DIR__, 3) . '/includes/page_start.php';
?>

<?php if (!empty($errors)): ?>
    <div class="notification is-danger is-light">
        <strong>Revise los datos ingresados:</strong>
        <ul class="mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="card">
    <header class="card-header">
        <p class="card-header-title">Datos de recepción</p>
    </header>
    <div class="card-content">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="columns is-multiline">
            <div class="column is-12-tablet is-4-desktop">
                <div class="field">
                    <label class="label">Código del equipo *</label>
                    <div class="control">
                        <input type="text" name="codigo_equipo" class="input" value="<?php echo htmlspecialchars($_POST['codigo_equipo'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
            <div class="column is-12-tablet is-4-desktop">
                <div class="field">
                    <label class="label">Serie</label>
                    <div class="control">
                        <input type="text" name="serie_equipo" class="input" value="<?php echo htmlspecialchars($_POST['serie_equipo'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <div class="column is-12-tablet is-4-desktop">
                <div class="field">
                    <label class="label">Ubicación</label>
                    <div class="control">
                        <input type="text" name="ubicacion" class="input" value="<?php echo htmlspecialchars($_POST['ubicacion'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <div class="column is-12-tablet is-6-desktop">
                <div class="field">
                    <label class="label">Descripción del equipo *</label>
                    <div class="control">
                        <input type="text" name="descripcion_equipo" class="input" value="<?php echo htmlspecialchars($_POST['descripcion_equipo'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
            <div class="column is-12-tablet is-6-desktop">
                <div class="field">
                    <label class="label">Usuario que entrega</label>
                    <div class="control">
                        <input type="text" name="usuario_entrega" class="input" value="<?php echo htmlspecialchars($_POST['usuario_entrega'] ?? ''); ?>" placeholder="Nombre del colaborador">
                    </div>
                </div>
            </div>
            <div class="column is-12-tablet is-4-desktop">
                <div class="field">
                    <label class="label">Tipo de mantenimiento *</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="tipo_mantenimiento" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($tipos as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (($_POST['tipo_mantenimiento'] ?? '') === $key) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="column is-12-tablet is-4-desktop">
                <div class="field">
                    <label class="label">Fecha de recepción *</label>
                    <div class="control">
                        <input type="date" name="fecha_recepcion" class="input" value="<?php echo htmlspecialchars($_POST['fecha_recepcion'] ?? date('Y-m-d')); ?>" required>
                    </div>
                </div>
            </div>
            <div class="column is-12-tablet is-4-desktop">
                <div class="field">
                    <label class="label">Asignar técnico</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="tecnico_id">
                                <option value="">Seleccionar después</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id']; ?>" <?php echo (($_POST['tecnico_id'] ?? '') == $tecnico['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="column is-12">
                <div class="field">
                    <label class="label">Observaciones iniciales</label>
                    <div class="control">
                        <textarea name="observaciones_recepcion" rows="3" class="textarea" placeholder="Describe brevemente el estado del equipo o accesorios entregados."><?php echo htmlspecialchars($_POST['observaciones_recepcion'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="card-footer">
        <div class="card-footer-item is-justify-content-flex-end">
            <button type="submit" class="button is-primary">
                <span class="icon"><i class="fas fa-save"></i></span>
                <span>Registrar ingreso</span>
            </button>
        </div>
    </footer>
</form>

<?php if (!empty($_SESSION['ultimo_mantenimiento_publico'])): ?>
    <?php $ultimo = $_SESSION['ultimo_mantenimiento_publico']; ?>
    <div class="notification is-info is-light mt-5">
        <div class="columns is-vcentered">
            <div class="column is-8">
                <p class="has-text-weight-semibold">Último folio generado: <?php echo htmlspecialchars($ultimo['folio']); ?></p>
                <p class="is-size-7">Comparta el enlace público para seguimiento: <a href="<?php echo htmlspecialchars(generar_url_estado_publico($ultimo['token'])); ?>" target="_blank"><?php echo htmlspecialchars(generar_url_estado_publico($ultimo['token'])); ?></a></p>
            </div>
            <div class="column is-4 has-text-centered">
                <figure class="image is-128x128 is-inline-block">
                    <img src="<?php echo htmlspecialchars($ultimo['qr']); ?>" alt="QR seguimiento">
                </figure>
                <p class="is-size-7 has-text-grey">Escanee para seguimiento</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include dirname(__DIR__, 3) . '/includes/page_end.php'; ?>
