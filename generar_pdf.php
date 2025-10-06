<?php
/**
 * Generador de PDF para Conocimientos de Entrega
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'includes/functions.php';

// Verificar autenticación
require_auth();

// Verificar que se proporcione un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php?error=invalid_id');
    exit();
}

$conocimiento_id = (int)$_GET['id'];
$auth = new Auth();
$current_user = $auth->getCurrentUser();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener datos del conocimiento
    $query = "SELECT c.*, 
                     c.observaciones_generales as observaciones,
                     c.numero_conocimiento,
                     DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') as fecha_formateada,
                     e.nombre_completo as entregante_nombre,
                     e.puesto as entregante_puesto,\n                     e.firma_path as entregante_firma_path,
                     de.nombre as entregante_distrito,
                     r.nombre_completo as receptor_nombre,
                     COALESCE(pr.nombre, r.puesto) AS receptor_puesto
              FROM conocimientos c
              INNER JOIN usuarios e ON c.entregante_id = e.id
              INNER JOIN distritos de ON e.distrito_id = de.id
              INNER JOIN receptores r ON c.receptor_id = r.id
              LEFT JOIN puestos pr ON r.puesto_id = pr.id
              WHERE c.id = :id;";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $conocimiento_id);
    $stmt->execute();
    $conocimiento = $stmt->fetch();
    
    if (!$conocimiento) {
        header('Location: dashboard.php?error=not_found');
        exit();
    }
    
    // Verificar permisos
    if (!has_role('Administrador') && 
        $conocimiento['entregante_id'] != $current_user['id'] && 
        $conocimiento['receptor_id'] != $current_user['id'] &&
        $conocimiento['creado_por'] != $current_user['id']) {
        header('Location: dashboard.php?error=no_permission');
        exit();
    }
    
    // Obtener detalle de insumos
    $query = "SELECT dc.cantidad, 
                     dc.observaciones,
                     i.codigo,
                     i.nombre as insumo_nombre,
                     i.descripcion,
                     i.unidad_medida
              FROM detalle_conocimientos dc
              INNER JOIN insumos i ON dc.insumo_id = i.id
              WHERE dc.conocimiento_id = :id
              ORDER BY i.nombre";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $conocimiento_id);
    $stmt->execute();
    $insumos = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error cargando conocimiento: " . $e->getMessage());
    header('Location: dashboard.php?error=load_error');
    exit();
}

// Verificar si TCPDF está disponible
if (!class_exists('TCPDF')) {
    // Intentar cargar TCPDF desde diferentes ubicaciones posibles
    $tcpdf_paths = [
        'tcpdf/tcpdf.php',
        'vendor/tecnickcom/tcpdf/tcpdf.php',
        'lib/tcpdf/tcpdf.php'
    ];
    
    $tcpdf_loaded = false;
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $tcpdf_loaded = true;
            break;
        }
    }
    
    if (!$tcpdf_loaded) {
        die('Error: TCPDF no está instalado. Por favor instale TCPDF en una de estas ubicaciones: ' . implode(', ', $tcpdf_paths));
    }
}

// Preparar datos para el PDF
$fecha = $conocimiento['fecha_formateada'];
$lugar = $conocimiento['lugar_entrega'];

$entregante = [
    'nombre' => $conocimiento['entregante_nombre'],
    'puesto' => $conocimiento['entregante_puesto'] ?? ''
];

$receptor = [
    'nombre' => $conocimiento['receptor_nombre'],
    'puesto' => $conocimiento['receptor_puesto'] ?? ''
];

// Firmas (opcionales): buscar imágenes locales guardadas por usuario
$firmaEntreganteDataUri = '';
$firmaEntreganteFile = '';
try {
    $eid = (int)$conocimiento['entregante_id'];
    $dir = __DIR__ . '/assets/firmas';
    $candidates = [
        $dir . "/user_{$eid}.png",
        $dir . "/user_{$eid}.jpg",
        $dir . "/user_{$eid}.jpeg",
        $dir . "/user_{$eid}.webp",
    ];
    foreach ($candidates as $pathImg) {
        if (is_file($pathImg)) {
            // Detectar MIME por extensión (compatible con PHP < 8)
            $mime = 'image/png';
            $ext = strtolower(pathinfo($pathImg, PATHINFO_EXTENSION));
            if ($ext === 'jpg' || $ext === 'jpeg') { $mime = 'image/jpeg'; }
            elseif ($ext === 'webp') { $mime = 'image/webp'; }
            $data = @file_get_contents($pathImg);
            if ($data !== false) {
                $firmaEntreganteDataUri = 'data:' . $mime . ';base64,' . base64_encode($data);
                // Guardar ruta absoluta para TCPDF (HTML <img src> con ruta local)
                $abs = realpath($pathImg);
                if ($abs) { $firmaEntreganteFile = str_replace('\\', '/', $abs); }
            }
            break;
        }
    }
} catch (Exception $e) {
    // Ignorar errores de firma
}
// Configuración del PDF
$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('Sistema de Conocimiento');
$pdf->SetAuthor('Sistema');
$pdf->SetTitle('Conocimiento de Entrega de Insumos #' . $conocimiento['numero_conocimiento']);
$pdf->SetSubject('Conocimiento de Entrega');
$pdf->SetKeywords('conocimiento, entrega, insumos, salud');

// Configurar márgenes
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Agregar página
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

$html_observaciones = '';
if (!empty($conocimiento['observaciones'])) {
    $html_observaciones = '
    <div class="section-header">OBSERVACIONES GENERALES</div>
    <div style="border: 1px solid #000; padding: 8px; margin-bottom: 15px;">
        ' . nl2br(htmlspecialchars($conocimiento['observaciones'])) . '
    </div>';
}

// Construir HTML del PDF
$html = '
<style>
    .header {
        text-align: center;
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 10px;
    }
    .subheader {
        text-align: center;
        font-size: 10px;
        margin-bottom: 20px;
    }
    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }
    .info-table td {
        border: 1px solid #000;
        padding: 8px;
        font-size: 10px;
    }
    .section-header {
        background-color: #f2f2f2;
        font-weight: bold;
        text-align: center;
    }
    .insumos-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .insumos-table th, .insumos-table td {
        border: 1px solid #000;
        padding: 6px;
        font-size: 9px;
    }
    .insumos-table th {
        background-color: #f2f2f2;
        font-weight: bold;
        text-align: center;
    }
    .signatures-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .signatures-table td {
        border: 1px solid #000;
        padding: 20px;
        text-align: center;
        font-size: 10px;
        height: 80px;
        vertical-align: top;
    }
    .footer {
        text-align: center;
        font-size: 8px;
        margin-top: 20px;
        color: #666;
    }
    .numero-conocimiento {
        text-align: right;
        font-size: 12px;
        font-weight: bold;
        margin-bottom: 10px;
        color: #333;
    }
</style>

<div class="numero-conocimiento">
    Conocimiento No. ' . $conocimiento['numero_conocimiento'] . '
</div>

<div class="header">
    FORMATO DE CONOCIMIENTO PARA ENTREGA DE INSUMOS
</div>

<div class="subheader">
    Dirección Departamental de Redes Integradas de Servicios de Salud de Alta Verapaz<br>
    Unidad de Informática
</div>

<hr style="margin-bottom: 15px;">

<table class="info-table">
    <tr>
        <td width="50%"><strong>Fecha:</strong> ' . $fecha . '</td>
        <td width="50%"><strong>Lugar de Entrega:</strong> ' . htmlspecialchars($lugar) . '</td>
    </tr>
</table>

<table class="info-table">
    <tr>
        <td width="50%" class="section-header">DATOS DEL ENTREGANTE</td>
        <td width="50%" class="section-header">DATOS DEL RECEPTOR</td>
    </tr>
    <tr>
        <td width="50%">
            <strong>Nombre:</strong> ' . htmlspecialchars($entregante['nombre']) . '<br>
            <strong>Puesto:</strong> ' . htmlspecialchars($entregante['puesto'] ?? '') . '
        </td>
        <td width="50%">
            <strong>Nombre:</strong> ' . htmlspecialchars($receptor['nombre']) . '<br>
            <strong>Puesto:</strong> ' . htmlspecialchars($receptor['puesto'] ?? '') . '
        </td>
    </tr>
</table>

' . $html_observaciones . '

<table class="insumos-table">
    <tr>
        <th width="8%">Nº</th>
        <th width="44%">Descripción del Insumo</th>
        <th width="16%">Cantidad</th>
        <th width="12%">Unidad</th>
        <th width="20%">Observaciones</th>
    </tr>';

// Agregar insumos
$i = 1;
foreach ($insumos as $item) {
    $descripcion = $item['codigo'] . ' - ' . $item['insumo_nombre'];
    if (!empty($item['descripcion'])) {
        $descripcion .= ' (' . $item['descripcion'] . ')';
    }
    
    $html .= '
    <tr>
        <td style="text-align: center;">' . $i++ . '</td>
        <td>' . htmlspecialchars($descripcion) . '</td>
        <td style="text-align: center;">' . number_format($item['cantidad'], 2) . '</td>
        <td style="text-align: center;">' . htmlspecialchars($item['unidad_medida']) . '</td>
        <td>' . htmlspecialchars($item['observaciones']) . '</td>
    </tr>';
}

// Agregar filas vacías para mantener el formato (mínimo 4 filas)
for ($j = $i; $j <= 4; $j++) {
    $html .= '
    <tr>
        <td style="text-align: center;">' . $j . '</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>';
}

$html .= '</table>';

// Agregar observaciones generales si existen
if (!empty($conocimiento['observaciones_generales'])) {
    $html .= '
    <table class="info-table">
        <tr>
            <td class="section-header">OBSERVACIONES GENERALES</td>
        </tr>
        <tr>
            <td>' . htmlspecialchars($conocimiento['observaciones_generales']) . '</td>
        </tr>
    </table>';
}

$html .= '
<table class="signatures-table">
    <tr>
        <td width="50%">
            <strong>Firma del Entregante</strong><br><br><br>
            ' . (!empty($firmaEntreganteFile) ? '<img src="' . $firmaEntreganteFile . '" style="max-height:80px; max-width:220px;" />' : '') . '
        </td>
        <td width="50%">
            <strong>Firma del Receptor</strong><br><br><br>
           
        </td>
    </tr>
</table>

<div class="footer">
    Dirección Departamental de Redes Integradas de Servicios de Salud de Alta Verapaz<br>
    Sistema de Conocimiento de Entrega de Insumos – Generado automáticamente<br>
    Fecha de generación: ' . date('d/m/Y H:i:s') . '
</div>';

// Generar PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Registrar la generación del PDF
try {
    log_user_activity($current_user['id'], 'pdf_generated', "PDF generado para conocimiento #{$conocimiento['numero_conocimiento']}");
} catch (Exception $e) {
    // No detener la generación del PDF por un error de log
    error_log("Error logging PDF generation: " . $e->getMessage());
}

// Determinar el nombre del archivo
$filename = 'conocimiento_' . $conocimiento['numero_conocimiento'] . '.pdf';

// Limpiar cualquier salida previa
if (ob_get_level()) {
    ob_end_clean();
}

// Configurar headers para PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Generar y enviar el PDF
$pdf->Output($filename, 'I'); // 'I' = visualizar en navegador
exit();
?>



