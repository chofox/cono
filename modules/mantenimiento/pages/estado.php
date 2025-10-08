<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__) . '/services/MantenimientoRepository.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

$repository = new MantenimientoRepository();

$folio = sanitize_input($_GET['folio'] ?? ($_POST['folio'] ?? ''));
$token = sanitize_input($_GET['token'] ?? ($_POST['token'] ?? ''));

$mantenimiento = null;
$diagnostico = null;
$seguimientos = [];
$historial = [];
$error = '';

try {
    if ($token !== '') {
        $mantenimiento = $repository->obtenerMantenimientoPorToken($token);
    } elseif ($folio !== '') {
        $mantenimiento = $repository->obtenerMantenimientoPorFolio($folio);
    }

    if ($mantenimiento) {
        $diagnostico = $repository->obtenerDiagnostico((int) $mantenimiento['id']);
        $seguimientos = $repository->obtenerSeguimientos((int) $mantenimiento['id']);
        $historial = $repository->obtenerHistorialEstados((int) $mantenimiento['id']);
        $token = $mantenimiento['public_token'];
        $folio = $mantenimiento['folio'];
    } elseif (($folio !== '') || ($token !== '')) {
        $error = 'No se encontró información para el número de ingreso proporcionado.';
    }
} catch (Throwable $th) {
    error_log('Error consultando estado: ' . $th->getMessage());
    $error = 'No fue posible consultar el estado en este momento.';
}

$page_title = 'Consulta de estado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de mantenimiento - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body class="has-background-light">
    <section class="hero is-white">
        <div class="hero-body">
            <div class="container">
                <div class="columns is-vcentered">
                    <div class="column is-8">
                        <h1 class="title is-3 has-text-primary">Seguimiento de mantenimiento</h1>
                        <p class="subtitle is-6 has-text-grey">Consulte el estado de su equipo utilizando el número de ingreso o el código de seguimiento que recibió.</p>
                    </div>
                    <div class="column is-4 has-text-right-tablet">
                        <a class="button is-light" href="<?php echo APP_URL; ?>/index.php">
                            <span class="icon"><i class="fas fa-arrow-left"></i></span>
                            <span>Volver al inicio</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="columns is-centered">
                <div class="column is-10-tablet is-8-desktop">
                    <div class="card mb-5">
                        <header class="card-header">
                            <p class="card-header-title">Consulta pública de estado</p>
                        </header>
                        <div class="card-content">
                            <p class="is-size-6 has-text-grey">Ingrese el número de ingreso (folio) o escanee el código QR entregado en la boleta para conocer el estado actual de su equipo.</p>
                            <form method="get">
                                <div class="columns is-multiline">
                                    <div class="column is-12-tablet is-6-desktop">
                                        <div class="field">
                                            <label class="label">Número de ingreso</label>
                                            <div class="control">
                                                <input type="text" name="folio" class="input" value="<?php echo htmlspecialchars($folio); ?>" placeholder="MT-2025-0001">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="column is-12-tablet is-6-desktop">
                                        <div class="field">
                                            <label class="label">Código de seguimiento</label>
                                            <div class="control">
                                                <input type="text" name="token" class="input" value="<?php echo htmlspecialchars($token); ?>" placeholder="Código QR">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="column is-12 has-text-right">
                                        <div class="field is-grouped is-grouped-right">
                                            <p class="control">
                                                <button type="submit" class="button is-primary">
                                                    <span class="icon"><i class="fas fa-search"></i></span>
                                                    <span>Consultar estado</span>
                                                </button>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <?php if ($error !== ''): ?>
                                <div class="notification is-danger is-light mt-4"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($mantenimiento): ?>
                        <div class="card mb-5">
                            <header class="card-header">
                                <div class="card-header-title is-justify-content-space-between is-align-items-center">
                                    <div>
                                        <p class="title is-5 mb-1">Estado actual</p>
                                        <p class="is-size-7 has-text-grey">Actualizado al <?php echo format_datetime($mantenimiento['fecha_actualizacion']); ?></p>
                                    </div>
                                    <span class="tag is-medium <?php echo mantenimiento_estado_badge_class($mantenimiento['estado']); ?>"><?php echo mantenimiento_estado_label($mantenimiento['estado']); ?></span>
                                </div>
                            </header>
                            <div class="card-content">
                                <div class="columns is-multiline is-variable is-4">
                                    <div class="column is-6">
                                        <p class="heading">Número de ingreso</p>
                                        <p class="has-text-weight-semibold"><?php echo htmlspecialchars($mantenimiento['folio']); ?></p>
                                    </div>
                                    <div class="column is-6">
                                        <p class="heading">Tipo de mantenimiento</p>
                                        <p class="has-text-weight-semibold"><?php echo mantenimiento_tipo_options()[$mantenimiento['tipo_mantenimiento']] ?? $mantenimiento['tipo_mantenimiento']; ?></p>
                                    </div>
                                    <div class="column is-6">
                                        <p class="heading">Equipo</p>
                                        <p class="has-text-weight-semibold"><?php echo htmlspecialchars($mantenimiento['equipo_descripcion']); ?> <span class="is-size-7 has-text-grey">(<?php echo htmlspecialchars($mantenimiento['codigo']); ?>)</span></p>
                                    </div>
                                    <div class="column is-6">
                                        <p class="heading">Técnico asignado</p>
                                        <p class="has-text-weight-semibold"><?php echo htmlspecialchars($mantenimiento['tecnico_nombre'] ?? 'Por asignar'); ?></p>
                                    </div>
                                    <div class="column is-6">
                                        <p class="heading">Fecha de recepción</p>
                                        <p class="has-text-weight-semibold"><?php echo format_date($mantenimiento['fecha_recepcion']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <header class="card-header">
                                <p class="card-header-title">Línea de tiempo</p>
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
                                                <?php if (!empty($evento['comentario'])): ?>
                                                    <p><?php echo nl2br(htmlspecialchars($evento['comentario'])); ?></p>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($diagnostico): ?>
                            <div class="card mb-5">
                                <header class="card-header">
                                    <p class="card-header-title">Diagnóstico técnico</p>
                                </header>
                                <div class="card-content">
                                    <p class="mb-3"><strong>Descripción:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['descripcion_falla'])); ?></p>
                                    <?php if (!empty($diagnostico['accion_recomendada'])): ?>
                                        <p class="mb-0"><strong>Acción recomendada:</strong><br><?php echo nl2br(htmlspecialchars($diagnostico['accion_recomendada'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <header class="card-header">
                                <p class="card-header-title">Seguimientos recientes</p>
                            </header>
                            <div class="card-content">
                                <?php if (empty($seguimientos)): ?>
                                    <p class="has-text-grey">Aún no se han registrado seguimientos adicionales.</p>
                                <?php else: ?>
                                    <ul class="timeline">
                                        <?php foreach ($seguimientos as $seguimiento): ?>
                                            <li class="timeline-item">
                                                <p class="is-size-7 has-text-grey"><?php echo format_datetime($seguimiento['fecha_seguimiento']); ?></p>
                                                <p class="has-text-weight-semibold">Estado: <?php echo mantenimiento_estado_label($seguimiento['estado']); ?></p>
                                                <p><?php echo nl2br(htmlspecialchars($seguimiento['descripcion'])); ?></p>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
