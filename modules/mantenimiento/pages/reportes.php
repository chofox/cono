<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_auth();
require_any_role(['Supervisor', 'Administrador']);

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

$exportQuery = fn(string $type): string => http_build_query(array_merge($filtros, ['export' => $type]));

$page_title = 'Reportes de mantenimiento';
$page_subtitle = 'Genere filtros dinámicos y exporte los resultados en PDF o Excel';
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Mantenimiento', 'href' => APP_URL . '/modules/mantenimiento/index.php'],
    ['label' => 'Reportes'],
];
$page_actions = [
    [
        'label' => 'Exportar PDF',
        'href' => APP_URL . '/modules/mantenimiento/pages/reportes.php?' . $exportQuery('pdf'),
        'icon' => 'fas fa-file-pdf',
        'class' => 'button is-light',
        'target' => '_blank',
        'rel' => 'noopener',
    ],
    [
        'label' => 'Exportar Excel',
        'href' => APP_URL . '/modules/mantenimiento/pages/reportes.php?' . $exportQuery('excel'),
        'icon' => 'fas fa-file-excel',
        'class' => 'button is-light',
    ],
];

include dirname(__DIR__, 3) . '/includes/page_start.php';
?>

<div class="card mb-5">
    <header class="card-header">
        <p class="card-header-title">Filtros</p>
        <a class="card-header-icon" href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/reportes.php" title="Limpiar filtros">
            <span class="icon"><i class="fas fa-rotate-left"></i></span>
        </a>
    </header>
    <div class="card-content">
        <form method="get">
            <div class="columns is-multiline">
                <div class="column is-12-tablet is-6-desktop is-4-widescreen">
                    <div class="field">
                        <label class="label">Estado</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="estado">
                                    <option value="">Todos</option>
                                    <?php foreach ($estados as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($filtros['estado'] === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-12-tablet is-6-desktop is-4-widescreen">
                    <div class="field">
                        <label class="label">Tipo</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="tipo_mantenimiento">
                                    <option value="">Todos</option>
                                    <?php foreach ($tipos as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($filtros['tipo_mantenimiento'] === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-12-tablet is-6-desktop is-4-widescreen">
                    <div class="field">
                        <label class="label">Técnico</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="tecnico_id">
                                    <option value="">Todos</option>
                                    <?php foreach ($tecnicos as $tecnico): ?>
                                        <option value="<?php echo $tecnico['id']; ?>" <?php echo ($filtros['tecnico_id'] == $tecnico['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tecnico['nombre_completo']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-12-tablet is-6-desktop is-4-widescreen">
                    <div class="field">
                        <label class="label">Folio</label>
                        <div class="control">
                            <input type="text" name="folio" class="input" value="<?php echo htmlspecialchars($filtros['folio']); ?>">
                        </div>
                    </div>
                </div>
                <div class="column is-12-tablet is-6-desktop is-4-widescreen">
                    <div class="field">
                        <label class="label">Desde</label>
                        <div class="control">
                            <input type="date" name="fecha_inicio" class="input" value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                        </div>
                    </div>
                </div>
                <div class="column is-12-tablet is-6-desktop is-4-widescreen">
                    <div class="field">
                        <label class="label">Hasta</label>
                        <div class="control">
                            <input type="date" name="fecha_fin" class="input" value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                        </div>
                    </div>
                </div>
                <div class="column is-12">
                    <div class="field is-grouped is-justify-content-flex-end">
                        <div class="control">
                            <button type="submit" class="button is-primary">
                                <span class="icon"><i class="fas fa-filter"></i></span>
                                <span>Aplicar filtros</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <header class="card-header">
        <div class="card-header-title is-justify-content-space-between is-align-items-center">
            <p class="title is-5 mb-0">Resultados</p>
            <span class="tag is-info is-light">Total: <?php echo $totalRegistros; ?> — Q<?php echo number_format($totalCosto, 2); ?></span>
        </div>
    </header>
    <div class="card-content p-0">
        <?php if (empty($mantenimientos)): ?>
            <div class="notification is-light has-text-centered m-4">
                <span class="icon-text">
                    <span class="icon"><i class="fas fa-info-circle"></i></span>
                    <span>No se encontraron registros con los filtros seleccionados.</span>
                </span>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Equipo</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Técnico</th>
                            <th>Recepción</th>
                            <th class="has-text-right">Costo total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mantenimientos as $registro): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($registro['folio']); ?></td>
                                <td><?php echo htmlspecialchars($registro['equipo_descripcion']); ?></td>
                                <td><?php echo $tipos[$registro['tipo_mantenimiento']] ?? $registro['tipo_mantenimiento']; ?></td>
                                <td>
                                    <span class="tag <?php echo mantenimiento_estado_badge_class($registro['estado']); ?>">
                                        <?php echo mantenimiento_estado_label($registro['estado']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($registro['tecnico_nombre'] ?? 'Sin asignar'); ?></td>
                                <td><?php echo format_date($registro['fecha_recepcion']); ?></td>
                                <td class="has-text-right">Q<?php echo number_format((float) $registro['costo_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 3) . '/includes/page_end.php'; ?>
