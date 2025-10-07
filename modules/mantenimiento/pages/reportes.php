<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/classes/Auth.php';
require_once dirname(__DIR__) . '/services/MantenimientoRepository.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

require_auth();
require_any_role(['Supervisor', 'Administrador']);

$auth = new Auth();
$current_user = $auth->getCurrentUser();
$repository = new MantenimientoRepository();

$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'tipo_mantenimiento' => $_GET['tipo_mantenimiento'] ?? '',
    'tecnico_id' => $_GET['tecnico_id'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? '',
    'folio' => $_GET['folio'] ?? '',
];

$tecnicos = get_users_by_role('Técnico');
$estados = mantenimiento_estados();
$tipos = mantenimiento_tipo_options();

$mantenimientos = $repository->buscarMantenimientos($filtros);
$totalRegistros = count($mantenimientos);
$totalCosto = array_sum(array_map(fn($item) => (float) $item['costo_total'], $mantenimientos));

$export = $_GET['export'] ?? '';
if ($export === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_mantenimientos.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Folio', 'Equipo', 'Tipo', 'Estado', 'Técnico', 'Fecha recepción', 'Costo total']);
    foreach ($mantenimientos as $registro) {
        fputcsv($output, [
            $registro['folio'],
            $registro['equipo_descripcion'],
            $tipos[$registro['tipo_mantenimiento']] ?? $registro['tipo_mantenimiento'],
            mantenimiento_estado_label($registro['estado']),
            $registro['tecnico_nombre'] ?? 'Sin asignar',
            $registro['fecha_recepcion'],
            number_format((float) $registro['costo_total'], 2),
        ]);
    }
    fclose($output);
    exit();
}

if ($export === 'pdf') {
    $tcpdfPaths = [
        dirname(__DIR__, 3) . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        dirname(__DIR__, 3) . '/tcpdf/tcpdf.php',
    ];
    $loaded = false;
    foreach ($tcpdfPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    if (!$loaded) {
        die('No se encontró TCPDF para generar el PDF.');
    }

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema de Mantenimiento');
    $pdf->SetAuthor($current_user['nombre_completo'] ?? 'Sistema');
    $pdf->SetTitle('Reporte de mantenimientos');
    $pdf->SetMargins(12, 20, 12);
    $pdf->AddPage();

    $html = '<h2>Reporte de Mantenimientos</h2>';
    $html .= '<p>Generado el ' . date('d/m/Y H:i') . ' — Total registros: ' . $totalRegistros . ' — Costo total: Q' . number_format($totalCosto, 2) . '</p>';
    $html .= '<table border="1" cellpadding="4" cellspacing="0">';
    $html .= '<thead><tr style="background-color:#f0f0f0; font-weight:bold;"><th>Folio</th><th>Equipo</th><th>Tipo</th><th>Estado</th><th>Técnico</th><th>Recepción</th><th>Costo total</th></tr></thead><tbody>';
    foreach ($mantenimientos as $registro) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($registro['folio']) . '</td>';
        $html .= '<td>' . htmlspecialchars($registro['equipo_descripcion']) . '</td>';
        $html .= '<td>' . htmlspecialchars($tipos[$registro['tipo_mantenimiento']] ?? $registro['tipo_mantenimiento']) . '</td>';
        $html .= '<td>' . htmlspecialchars(mantenimiento_estado_label($registro['estado'])) . '</td>';
        $html .= '<td>' . htmlspecialchars($registro['tecnico_nombre'] ?? 'Sin asignar') . '</td>';
        $html .= '<td>' . htmlspecialchars(format_date($registro['fecha_recepcion'])) . '</td>';
        $html .= '<td>Q' . number_format((float) $registro['costo_total'], 2) . '</td>';
        $html .= '</tr>';
    }
    if (empty($mantenimientos)) {
        $html .= '<tr><td colspan="7">No se encontraron registros con los filtros seleccionados.</td></tr>';
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('reporte_mantenimientos.pdf', 'I');
    exit();
}

$page_title = 'Reportes de Mantenimiento';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h3 mb-0">Reportes de mantenimiento</h1>
                    <p class="text-muted mb-0">Genere reportes filtrables y exportables en PDF o Excel.</p>
                </div>
                <div class="btn-group">
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/reportes.php?<?php echo http_build_query(array_merge($filtros, ['export' => 'pdf'])); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-file-pdf me-2"></i>Exportar PDF
                    </a>
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/reportes.php?<?php echo http_build_query(array_merge($filtros, ['export' => 'excel'])); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-file-excel me-2"></i>Exportar Excel
                    </a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Filtros</h2>
                </div>
                <div class="card-body">
                    <form class="row g-3" method="get">
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($estados as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($filtros['estado'] === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo_mantenimiento" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($tipos as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($filtros['tipo_mantenimiento'] === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Técnico</label>
                            <select name="tecnico_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id']; ?>" <?php echo ($filtros['tecnico_id'] == $tecnico['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tecnico['nombre_completo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Folio</label>
                            <input type="text" name="folio" class="form-control" value="<?php echo htmlspecialchars($filtros['folio']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                        </div>
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Aplicar filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Resultados</h2>
                    <span class="badge bg-primary">Total: <?php echo $totalRegistros; ?> | Q<?php echo number_format($totalCosto, 2); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Equipo</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Técnico</th>
                                    <th>Recepción</th>
                                    <th class="text-end">Costo total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mantenimientos)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No se encontraron mantenimientos con los filtros seleccionados.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mantenimientos as $registro): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($registro['folio']); ?></td>
                                            <td><?php echo htmlspecialchars($registro['equipo_descripcion']); ?></td>
                                            <td><?php echo $tipos[$registro['tipo_mantenimiento']] ?? $registro['tipo_mantenimiento']; ?></td>
                                            <td><span class="badge bg-<?php echo mantenimiento_estado_badge_class($registro['estado']); ?>"><?php echo mantenimiento_estado_label($registro['estado']); ?></span></td>
                                            <td><?php echo htmlspecialchars($registro['tecnico_nombre'] ?? 'Sin asignar'); ?></td>
                                            <td><?php echo format_date($registro['fecha_recepcion']); ?></td>
                                            <td class="text-end">Q<?php echo number_format((float) $registro['costo_total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
