<?php
/**
 * AJAX - Detalle de Conocimiento
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../includes/functions.php';

// Verificar autenticación
require_auth();

$auth = new Auth();
$current_user = $auth->getCurrentUser();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID de conocimiento inválido</div>';
    exit();
}

$conocimiento_id = (int)$_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener datos del conocimiento
    // Obtener datos del conocimiento
    $query = "SELECT c.*,
                     c.observaciones_generales as observaciones,
                     entregante.nombre_completo as entregante_nombre,
                     '' AS entregante_telefono,
                     '' AS entregante_email,
                     receptor.nombre_completo as receptor_nombre,
                     receptor.telefono as receptor_telefono,
                     receptor.email as receptor_email,
                     DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') as fecha_formateada,
                     DATE_FORMAT(c.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion,
                     creador.nombre_completo as creado_por_nombre
              FROM conocimientos c
              INNER JOIN usuarios entregante ON c.entregante_id = entregante.id
              INNER JOIN receptores receptor ON c.receptor_id = receptor.id
              INNER JOIN usuarios creador ON c.creado_por = creador.id
              WHERE c.id = :conocimiento_id";
    
    // Verificar permisos según rol
    if ($current_user['rol'] == 'Técnico') {
        $query .= " AND (c.entregante_id = :user_id OR c.creado_por = :user_id)";
    } elseif ($current_user['rol'] == 'RRHH') {
        $query .= " AND (entregante.distrito_id = :user_distrito OR receptor.distrito_id = :user_distrito)";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':conocimiento_id', $conocimiento_id);
    
    if ($current_user['rol'] == 'Técnico') {
        $stmt->bindParam(':user_id', $current_user['id']);
    } elseif ($current_user['rol'] == 'RRHH') {
        $stmt->bindParam(':user_distrito', $current_user['distrito_id']);
    }
    
    $stmt->execute();
    $conocimiento = $stmt->fetch();
    
    if (!$conocimiento) {
        echo '<div class="alert alert-warning">Conocimiento no encontrado o sin permisos para verlo</div>';
        exit();
    }
    
    // Obtener insumos del conocimiento
    $query = "SELECT ci.*,
                     i.codigo,
                     i.nombre as insumo_nombre,
                     i.descripcion,
                     i.unidad_medida,
                     c.nombre as categoria
              FROM detalle_conocimientos ci
              INNER JOIN insumos i ON ci.insumo_id = i.id
              LEFT JOIN categorias_insumos c ON i.categoria_id = c.id
              WHERE ci.conocimiento_id = :conocimiento_id
              ORDER BY i.nombre";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':conocimiento_id', $conocimiento_id);
    $stmt->execute();
    $insumos = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error cargando detalle: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error cargando el detalle del conocimiento</div>';
    exit();
}
?>

<div class="row">
    <!-- Información General -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Información General
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="fw-bold">Número:</td>
                                <td>
                                    <span class="badge bg-primary fs-6"><?php echo escape_html($conocimiento['numero_conocimiento']); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Fecha de Entrega:</td>
                                <td><?php echo escape_html($conocimiento['fecha_formateada']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Lugar de Entrega:</td>
                                <td><?php echo escape_html($conocimiento['lugar_entrega']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="fw-bold">Fecha de Creación:</td>
                                <td><?php echo escape_html($conocimiento['fecha_creacion']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Creado por:</td>
                                <td><?php echo escape_html($conocimiento['creado_por_nombre']); ?></td>
                            </tr>
                            <?php if (!empty($conocimiento['observaciones'])): ?>
                            <tr>
                                <td class="fw-bold">Observaciones:</td>
                                <td><?php echo escape_html($conocimiento['observaciones']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Datos del Entregante y Receptor -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="fas fa-user-check me-2"></i>
                    Datos del Entregante
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="fw-bold">Nombre:</td>
                        <td><?php echo escape_html($conocimiento['entregante_nombre']); ?></td>
                    </tr>
                    <?php if (!empty($conocimiento['entregante_telefono'])): ?>
                    <tr>
                        <td class="fw-bold">Teléfono:</td>
                        <td><?php echo escape_html($conocimiento['entregante_telefono']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($conocimiento['entregante_email'])): ?>
                    <tr>
                        <td class="fw-bold">Email:</td>
                        <td><?php echo escape_html($conocimiento['entregante_email']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>
                    Datos del Receptor
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="fw-bold">Nombre:</td>
                        <td><?php echo escape_html($conocimiento['receptor_nombre']); ?></td>
                    </tr>
                    <?php if (!empty($conocimiento['receptor_telefono'])): ?>
                    <tr>
                        <td class="fw-bold">Teléfono:</td>
                        <td><?php echo escape_html($conocimiento['receptor_telefono']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($conocimiento['receptor_email'])): ?>
                    <tr>
                        <td class="fw-bold">Email:</td>
                        <td><?php echo escape_html($conocimiento['receptor_email']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Detalle de Insumos -->
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-boxes me-2"></i>
                    Detalle de Insumos (<?php echo count($insumos); ?> items)
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($insumos)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-box-open fa-2x mb-2"></i>
                        <p>No hay insumos registrados</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nº</th>
                                    <th>Código</th>
                                    <th>Insumo</th>
                                    <th>Cantidad</th>
                                    <th>Unidad</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($insumos)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">No hay insumos registrados para este conocimiento.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($insumos as $index => $insumo): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <code><?php echo escape_html($insumo['codigo']); ?></code>
                                            </td>
                                            <td>
                                                <strong><?php echo escape_html($insumo['insumo_nombre']); ?></strong><br>
                                                <small class="text-muted"><?php echo escape_html($insumo['categoria']); ?></small>
                                            </td>
                                            <td><?php echo escape_html($insumo['cantidad']); ?> <?php echo escape_html($insumo['unidad_medida']); ?></td>
                                            <td><?php echo escape_html($insumo['observaciones']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>

                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12 text-center">
        <a href="generar_pdf.php?id=<?php echo $conocimiento['id']; ?>" 
           class="btn btn-danger me-2"
           target="_blank">
            <i class="fas fa-file-pdf me-2"></i>Ver PDF
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>Cerrar
        </button>
    </div>
</div>
