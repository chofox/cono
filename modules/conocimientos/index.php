<?php
/**
 * Listado de Conocimientos
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar autenticación
require_auth();

$auth = new Auth();
$current_user = $auth->getCurrentUser();

// Inicializar variables
$error_message = '';
$success_message = '';
$conocimientos = [];
$total_conocimientos = 0;
$total_pages = 0;

// Parámetros de paginación y filtros
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$filter_estado = isset($_GET['estado']) ? sanitize_input($_GET['estado']) : '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Construir query con filtros
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(c.numero_conocimiento LIKE :search OR c.lugar_entrega LIKE :search OR 
                               entregante.nombre_completo LIKE :search OR 
                               COALESCE(p_ent.nombre, entregante.puesto) LIKE :search OR 
                               r.nombre_completo LIKE :search OR 
                               COALESCE(p_rec.nombre, r.puesto) LIKE :search OR 
                               d_ent.nombre LIKE :search OR 
                               d_rec.nombre LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    if ($filter_year > 0) {
        $where_conditions[] = "YEAR(c.fecha_entrega) = :year";
        $params[':year'] = $filter_year;
    }
    
    if ($filter_month > 0) {
        $where_conditions[] = "MONTH(c.fecha_entrega) = :month";
        $params[':month'] = $filter_month;
    }
    
    if (!empty($filter_estado)) {
        $where_conditions[] = "c.estado = :estado";
        $params[':estado'] = $filter_estado;
    }
    
    // Restricciones según rol
    if (isset($current_user['rol_id']) && $current_user['rol_id'] == 2) { // Técnico
        $where_conditions[] = "(c.entregante_id = :user_id OR c.creado_por = :user_id)";
        $params[':user_id'] = $current_user['id'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Contar total de conocimientos
    $count_query = "SELECT COUNT(*) as total 
                    FROM conocimientos c 
                    LEFT JOIN usuarios entregante ON c.entregante_id = entregante.id
                    LEFT JOIN receptores r ON c.receptor_id = r.id
                    LEFT JOIN puestos p_ent ON entregante.puesto_id = p_ent.id
                    LEFT JOIN distritos d_ent ON entregante.distrito_id = d_ent.id
                    LEFT JOIN puestos p_rec ON r.puesto_id = p_rec.id
                    LEFT JOIN distritos d_rec ON r.distrito_id = d_rec.id
                    {$where_clause}";
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_conocimientos = $stmt->fetch()['total'];
    $total_pages = ceil($total_conocimientos / $per_page);
    
    // Obtener años para el filtro
    try {
        $query = "SELECT DISTINCT YEAR(fecha_entrega) AS year FROM conocimientos ORDER BY year DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($years)) { $years = []; }
    } catch (Exception $e) {
        $years = [];
    }
    

    
    // Obtener conocimientos
    $query = "SELECT c.id, c.numero_conocimiento, c.estado,
                     DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') as fecha_entrega,
                     c.lugar_entrega,
                     c.entregante_id,
                     c.receptor_id,
                     entregante.nombre_completo as entregante,
                     COALESCE(p_ent.nombre, entregante.puesto) AS entregante_puesto,
                     d_ent.nombre AS entregante_distrito,
                     r.nombre_completo as receptor,
                     COALESCE(p_rec.nombre, r.puesto) AS receptor_puesto,
                     d_rec.nombre AS receptor_distrito,
                     r.telefono AS receptor_telefono,
                     (SELECT COUNT(*) FROM detalle_conocimientos WHERE conocimiento_id = c.id) as total_insumos,
                     DATE_FORMAT(c.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion
              FROM conocimientos c 
              LEFT JOIN usuarios entregante ON c.entregante_id = entregante.id
              LEFT JOIN receptores r ON c.receptor_id = r.id
              LEFT JOIN puestos p_ent ON entregante.puesto_id = p_ent.id
              LEFT JOIN distritos d_ent ON entregante.distrito_id = d_ent.id
              LEFT JOIN puestos p_rec ON r.puesto_id = p_rec.id
              LEFT JOIN distritos d_rec ON r.distrito_id = d_rec.id
              {$where_clause}
              ORDER BY c.fecha_creacion DESC, c.id DESC
              LIMIT :offset, :per_page";

    // Ejecutar consulta principal
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $conocimientos = $stmt->fetchAll();
} catch (Exception $e) {
    
    error_log("Error cargando conocimientos: " . $e->getMessage());
    $error_message = "Error cargando la lista de conocimientos";
    if (!isset($conocimientos)) { $conocimientos = []; }
    if (!isset($years)) { $years = []; }
}
$csrf_token = generate_csrf_token();
?>
<?php
$page_title = 'Conocimientos';
$page_subtitle = 'Gestione los conocimientos de entrega de insumos';
$breadcrumbs = [
    ['label' => 'Inicio', 'href' => APP_URL . '/dashboard.php'],
    ['label' => 'Conocimientos'],
];
$extra_head_styles = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
];
$extra_body_scripts = [
    'https://code.jquery.com/jquery-3.6.0.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
];
include __DIR__ . '/../../includes/page_start.php';
?>
<section class="app-section">
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>
                        Listado de Conocimientos
                    </h4>
                    <p class="mb-0 mt-2 opacity-75">
                        Gestione los conocimientos de entrega de insumos
                    </p>
                </div>
                <div>
                    <?php if (has_role('crear_conocimiento')): ?>
                        <a href="<?php echo APP_URL; ?>/modules/conocimientos/pages/crear.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Nuevo Conocimiento
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo escape_html($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo escape_html($success_message); ?>
                </div>
            <?php endif; ?>

            <div class="filters-card mb-4">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Buscar</label>
                            <input type="text"
                                   class="form-control"
                                   id="search"
                                   name="search"
                                   value="<?php echo escape_html($search); ?>"
                                   placeholder="Número, lugar, entregante o receptor">
                        </div>

                        <div class="col-md-2">
                            <label for="year" class="form-label">Año</label>
                            <select class="form-select" id="year" name="year">
                                <option value="0">Todos</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>"
                                            <?php echo $filter_year == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="month" class="form-label">Mes</label>
                            <select class="form-select" id="month" name="month">
                                <option value="0">Todos</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>"
                                            <?php echo $filter_month == $i ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="">Todos</option>
                                <option value="borrador" <?php echo $filter_estado == 'borrador' ? 'selected' : ''; ?>>Borrador</option>
                                <option value="finalizado" <?php echo $filter_estado == 'finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                                <option value="anulado" <?php echo $filter_estado == 'anulado' ? 'selected' : ''; ?>>Anulado</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>Filtrar
                            </button>
                            <a href="<?php echo APP_URL; ?>/modules/conocimientos/index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Lugar</th>
                            <th>Entregante</th>
                            <th>Receptor</th>
                            <th>Insumos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conocimientos)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No se encontraron conocimientos con los filtros seleccionados
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($conocimientos as $conocimiento): ?>
                                <tr>
                                    <td><?php echo escape_html($conocimiento['numero_conocimiento']); ?></td>
                                    <td><?php echo escape_html($conocimiento['fecha_entrega']); ?></td>
                                    <td><?php echo escape_html($conocimiento['lugar_entrega']); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo escape_html($conocimiento['entregante']); ?></div>
                                        <?php if (!empty($conocimiento['entregante_puesto']) || !empty($conocimiento['entregante_distrito'])): ?>
                                            <div class="text-muted small">
                                                <?php if (!empty($conocimiento['entregante_puesto'])): ?>
                                                    <?php echo escape_html($conocimiento['entregante_puesto']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($conocimiento['entregante_distrito'])): ?>
                                                    <?php if (!empty($conocimiento['entregante_puesto'])): ?><br><?php endif; ?>
                                                    <?php echo escape_html($conocimiento['entregante_distrito']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo escape_html($conocimiento['receptor']); ?></div>
                                        <?php if (!empty($conocimiento['receptor_puesto']) || !empty($conocimiento['receptor_distrito'])): ?>
                                            <div class="text-muted small">
                                                <?php if (!empty($conocimiento['receptor_puesto'])): ?>
                                                    <?php echo escape_html($conocimiento['receptor_puesto']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($conocimiento['receptor_distrito'])): ?>
                                                    <?php if (!empty($conocimiento['receptor_puesto'])): ?><br><?php endif; ?>
                                                    <?php echo escape_html($conocimiento['receptor_distrito']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($conocimiento['receptor_telefono']) || !empty($conocimiento['receptor_email'])): ?>
                                            <div class="text-muted small mt-1">
                                                <?php if (!empty($conocimiento['receptor_telefono'])): ?>
                                                    <i class="fas fa-phone me-1"></i><?php echo escape_html($conocimiento['receptor_telefono']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($conocimiento['receptor_email'])): ?>
                                                    <?php if (!empty($conocimiento['receptor_telefono'])): ?><br><?php endif; ?>
                                                    <i class="fas fa-envelope me-1"></i><?php echo escape_html($conocimiento['receptor_email']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo (int)$conocimiento['total_insumos']; ?> insumos
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($conocimiento['estado'] == 'finalizado'): ?>
                                            <span class="badge bg-success">Finalizado</span>
                                        <?php elseif ($conocimiento['estado'] == 'borrador'): ?>
                                            <span class="badge bg-warning">Borrador</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Anulado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?php echo APP_URL; ?>/modules/conocimientos/pages/generar_pdf.php?id=<?php echo $conocimiento['id']; ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Ver PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>

                                            <?php if ($conocimiento['estado'] == 'borrador' &&
                                                      (has_role('editar_conocimiento') ||
                                                       $conocimiento['entregante_id'] == $current_user['id'])): ?>
                                                <a href="<?php echo APP_URL; ?>/modules/conocimientos/pages/editar.php?id=<?php echo $conocimiento['id']; ?>"
                                                   class="btn btn-sm btn-outline-secondary"
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#detalleModal"
                                                    data-id="<?php echo $conocimiento['id']; ?>"
                                                    title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Paginación de conocimientos">
                    <ul class="pagination justify-content-center mt-4">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $filter_year; ?>&month=<?php echo $filter_month; ?>&estado=<?php echo urlencode($filter_estado); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $filter_year; ?>&month=<?php echo $filter_month; ?>&estado=<?php echo urlencode($filter_estado); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $filter_year; ?>&month=<?php echo $filter_month; ?>&estado=<?php echo urlencode($filter_estado); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="modal fade" id="detalleModal" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleModalLabel">Detalle de Conocimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalleModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando detalles...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
$inline_scripts = <<<'HTML'
<script>
    $(document).ready(function() {
        $('#detalleModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const id = button.data('id');
            const modalBody = $(this).find('#detalleModalBody');

            modalBody.html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-2">Cargando detalles...</p></div>');

            $.ajax({
                url: '<?php echo APP_URL; ?>/modules/conocimientos/ajax/detalle.php',
                type: 'GET',
                data: { id: id },
                success: function(response) {
                    modalBody.html(response);
                },
                error: function() {
                    modalBody.html('<div class="alert alert-danger">Error cargando los detalles</div>');
                }
            });
        });
    });
</script>
HTML;
include __DIR__ . '/../../includes/page_end.php';
?>
