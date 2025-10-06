<?php
/**
 * Gestión de Insumos
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'includes/functions.php';

// Verificar autenticación y permisos de administrador
require_auth();
if (!has_role('Administrador')) {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$auth = new Auth();
$current_user = $auth->getCurrentUser();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener categorías para el formulario
    $query = "SELECT id, nombre FROM categorias_insumos WHERE activo = 1 ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categorias = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error cargando datos: " . $e->getMessage());
    $error_message = "Error cargando los datos";
    $categorias = [];
}

// Procesar acciones
if ($_POST) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        try {
            $conn->beginTransaction();
            
            if (isset($_POST['crear_insumo'])) {
                // Crear nuevo insumo
                $codigo = sanitize_input($_POST['codigo']);
                $nombre = sanitize_input($_POST['nombre']);
                $descripcion = sanitize_input($_POST['descripcion']);
                $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
                $unidad_medida = sanitize_input($_POST['unidad_medida']);


                
                // Validaciones
                if (empty($codigo) || empty($nombre) || empty($unidad_medida)) {
                    throw new Exception("Código, nombre y unidad de medida son obligatorios");
                }
                
                if (strlen($codigo) > 20) {
                    throw new Exception("El código no puede tener más de 20 caracteres");
                }
                
                if (strlen($nombre) > 100) {
                    throw new Exception("El nombre no puede tener más de 100 caracteres");
                }
                
                // Validación de precio unitario se ha eliminado
                // Validación de stock mínimo ya no se maneja
                
                // Verificar que el código no exista
                $query = "SELECT id FROM insumos WHERE codigo = :codigo";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':codigo', $codigo);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    throw new Exception("Ya existe un insumo con este código");
                }
                
                // Insertar insumo
                $query = "INSERT INTO insumos 
                          (codigo, nombre, descripcion, categoria_id, unidad_medida, activo) 
                          VALUES (:codigo, :nombre, :descripcion, :categoria_id, :unidad_medida, 1)";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':codigo', $codigo);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->bindParam(':categoria_id', $categoria_id);
                $stmt->bindParam(':unidad_medida', $unidad_medida);
                

                
                if (!$stmt->execute()) {
                    throw new Exception("Error creando el insumo");
                }
                
                $conn->commit();
                $success_message = "Insumo creado exitosamente";
                
                // Log de actividad
                log_user_activity($current_user['id'], 'insumo_created', "Insumo creado: {$codigo} - {$nombre}");
                
            } elseif (isset($_POST['editar_insumo'])) {
                // Editar insumo existente
                $insumo_id = (int)$_POST['insumo_id'];
                $codigo = sanitize_input($_POST['codigo']);
                $nombre = sanitize_input($_POST['nombre']);
                $descripcion = sanitize_input($_POST['descripcion']);
                $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
                $unidad_medida = sanitize_input($_POST['unidad_medida']);
                // Precio unitario y stock mínimo ya no se manejan

                $activo = isset($_POST['activo']) ? 1 : 0;
                
                // Validaciones
                if (empty($codigo) || empty($nombre) || empty($unidad_medida)) {
                    throw new Exception("Código, nombre y unidad de medida son obligatorios");
                }
                
                if (strlen($codigo) > 20) {
                    throw new Exception("El código no puede tener más de 20 caracteres");
                }
                
                if (strlen($nombre) > 100) {
                    throw new Exception("El nombre no puede tener más de 100 caracteres");
                }
                
                // Validaciones de precio unitario y stock mínimo eliminadas
                
                // Verificar que el código no exista en otro insumo
                $query = "SELECT id FROM insumos WHERE codigo = :codigo AND id != :insumo_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':codigo', $codigo);
                $stmt->bindParam(':insumo_id', $insumo_id);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    throw new Exception("Ya existe otro insumo con este código");
                }
                
                // Actualizar insumo
                $query = "UPDATE insumos SET 
                          codigo = :codigo,
                          nombre = :nombre,
                          descripcion = :descripcion,
                          categoria_id = :categoria_id,
                          unidad_medida = :unidad_medida,
                          activo = :activo
                          WHERE id = :insumo_id";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':codigo', $codigo);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->bindParam(':categoria_id', $categoria_id);
                $stmt->bindParam(':unidad_medida', $unidad_medida);
                // Precio unitario y stock mínimo ya no se manejan

                $stmt->bindParam(':activo', $activo);
                $stmt->bindParam(':insumo_id', $insumo_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error actualizando el insumo");
                }
                
                $conn->commit();
                $success_message = "Insumo actualizado exitosamente";
                
                // Log de actividad
                log_user_activity($current_user['id'], 'insumo_updated', "Insumo actualizado: {$codigo} - {$nombre} (ID: {$insumo_id})");
                
            } elseif (isset($_POST['crear_categoria'])) {
                // Crear nueva categoría
                $nombre_categoria = sanitize_input($_POST['nombre_categoria']);
                $descripcion_categoria = sanitize_input($_POST['descripcion_categoria']);
                
                if (empty($nombre_categoria)) {
                    throw new Exception("El nombre de la categoría es obligatorio");
                }
                
                // Verificar que la categoría no exista
                $query = "SELECT id FROM categorias_insumos WHERE nombre = :nombre";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':nombre', $nombre_categoria);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    throw new Exception("Ya existe una categoría con este nombre");
                }
                
                // Insertar categoría
                $query = "INSERT INTO categorias_insumos (nombre, descripcion, activo, creado_por) 
                          VALUES (:nombre, :descripcion, 1, :creado_por)";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':nombre', $nombre_categoria);
                $stmt->bindParam(':descripcion', $descripcion_categoria);
                $stmt->bindParam(':creado_por', $current_user['id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error creando la categoría");
                }
                
                $conn->commit();
                $success_message = "Categoría creada exitosamente";
                
                // Recargar categorías
                $query = "SELECT id, nombre FROM categorias_insumos WHERE activo = 1 ORDER BY nombre";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $categorias = $stmt->fetchAll();
                
                // Log de actividad
                log_user_activity($current_user['id'], 'categoria_created', "Categoría creada: {$nombre_categoria}");
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = "Token de seguridad inválido";
    }
}

// Obtener lista de insumos con paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$filter_activo = isset($_GET['activo']) ? (int)$_GET['activo'] : -1;

// Construir query con filtros
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(i.codigo LIKE :search OR i.nombre LIKE :search OR i.descripcion LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($filter_categoria > 0) {
    $where_conditions[] = "i.categoria_id = :categoria_id";
    $params[':categoria_id'] = $filter_categoria;
}

if ($filter_activo >= 0) {
    $where_conditions[] = "i.activo = :activo";
    $params[':activo'] = $filter_activo;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Contar total de insumos
    $count_query = "SELECT COUNT(*) as total 
                    FROM insumos i 
                    LEFT JOIN categorias_insumos c ON i.categoria_id = c.id 
                    {$where_clause}";
    
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_insumos = $stmt->fetch()['total'];
    $total_pages = ceil($total_insumos / $per_page);
    
    // Obtener insumos
    $query = "SELECT i.id, i.codigo, i.nombre, i.descripcion, i.categoria_id, i.unidad_medida, i.activo, c.nombre as categoria,
                     DATE_FORMAT(i.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion, 
                      DATE_FORMAT(i.fecha_actualizacion, '%d/%m/%Y %H:%i') as fecha_actualizacion
              FROM insumos i 
              LEFT JOIN categorias_insumos c ON i.categoria_id = c.id 
              {$where_clause}
              ORDER BY i.codigo 
              LIMIT :offset, :per_page";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $insumos = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error cargando insumos: " . $e->getMessage() . " Query: " . $query);
    $error_message = "Error cargando la lista de insumos";
    $insumos = [];
    $total_insumos = 0;
    $total_pages = 0;
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Insumos - <?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">

    <!-- CSS Global -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid mt-4">
      <div class="row">
        <div class="col-md-3 col-lg-2 px-0">
          <?php include 'includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10">
          <div class="container mt-2">
    <div class="container-fluid py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Gestión de Insumos</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-12">
                <div class="main-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-boxes me-2"></i>
                                    Gestión de Insumos
                                </h4>
                                <p class="mb-0 mt-2 opacity-75">
                                    Administre el catálogo de insumos del sistema
                                </p>
                            </div>
                            <div>
                                <button type="button" class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#nuevaCategoriaModal">
                                    <i class="fas fa-tags me-2"></i>Nueva Categoría
                                </button>
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#nuevoInsumoModal">
                                    <i class="fas fa-plus me-2"></i>Nuevo Insumo
                                </button>
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
                        
                        <!-- Filtros -->
                        <div class="filters-card">
                            <form method="GET" action="">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="search" class="form-label">Buscar</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="search" 
                                               name="search" 
                                               value="<?php echo escape_html($search); ?>"
                                               placeholder="Código, nombre o descripción">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="categoria" class="form-label">Categoría</label>
                                        <select class="form-select" id="categoria" name="categoria">
                                            <option value="0">Todas las categorías</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?php echo $categoria['id']; ?>"
                                                        <?php echo $filter_categoria == $categoria['id'] ? 'selected' : ''; ?>>
                                                    <?php echo escape_html($categoria['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="activo" class="form-label">Estado</label>
                                        <select class="form-select" id="activo" name="activo">
                                            <option value="-1">Todos</option>
                                            <option value="1" <?php echo $filter_activo == 1 ? 'selected' : ''; ?>>Activos</option>
                                            <option value="0" <?php echo $filter_activo == 0 ? 'selected' : ''; ?>>Inactivos</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-search me-1"></i>Filtrar
                                        </button>
                                        <a href="insumos.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tabla de insumos -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Insumo</th>
                                        <th>Categoría</th>
                                        <th>Unidad</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($insumos)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No se encontraron insumos</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($insumos as $insumo): ?>
                                            <tr>
                                                <td>
                                                    <span class="insumo-code"><?php echo escape_html($insumo['codigo']); ?></span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo escape_html($insumo['nombre']); ?></strong>
                                                        <?php if (!empty($insumo['descripcion'])): ?>
                                                            <br><small class="text-muted"><?php echo escape_html($insumo['descripcion']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($insumo['categoria']): ?>
                                                        <span class="badge bg-secondary">
                                                            <?php echo escape_html($insumo['categoria']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin categoría</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo escape_html($insumo['unidad_medida']); ?></td>
                                                <td>
                                                    <?php if ($insumo['activo']): ?>
                                                        <span class="badge bg-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-primary"
                                                            onclick="editarInsumo(<?php echo htmlspecialchars(json_encode($insumo)); ?>)"
                                                            title="Editar insumo">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Paginación de insumos">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&categoria=<?php echo $filter_categoria; ?>&activo=<?php echo $filter_activo; ?>">
                                                Anterior
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&categoria=<?php echo $filter_categoria; ?>&activo=<?php echo $filter_activo; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&categoria=<?php echo $filter_categoria; ?>&activo=<?php echo $filter_activo; ?>">
                                                Siguiente
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            
                            <div class="text-center text-muted">
                                Mostrando <?php echo count($insumos); ?> de <?php echo $total_insumos; ?> insumos
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal Nuevo Insumo -->
    <div class="modal fade" id="nuevoInsumoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Nuevo Insumo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="codigo" class="form-label">
                                    Código <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="codigo" name="codigo" maxlength="20" required>
                                <div class="form-text">Máximo 20 caracteres</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">
                                    Nombre <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required>
                            </div>
                            
                            <div class="col-12">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="categoria_id" class="form-label">Categoría</label>
                                <select class="form-select" id="categoria_id" name="categoria_id">
                                    <option value="">Sin categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['id']; ?>">
                                            <?php echo escape_html($categoria['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="unidad_medida" class="form-label">
                                    Unidad de Medida <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="unidad_medida" name="unidad_medida" placeholder="Ej: Unidad, Kg, Litro" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_insumo" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Crear Insumo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Insumo -->
    <div class="modal fade" id="editarInsumoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Insumo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="insumo_id" id="edit_insumo_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_codigo" class="form-label">
                                    Código <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="edit_codigo" name="codigo" maxlength="20" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_nombre" class="form-label">
                                    Nombre <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="edit_nombre" name="nombre" maxlength="100" required>
                            </div>
                            
                            <div class="col-12">
                                <label for="edit_descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="2"></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_categoria_id" class="form-label">Categoría</label>
                                <select class="form-select" id="edit_categoria_id" name="categoria_id">
                                    <option value="">Sin categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['id']; ?>">
                                            <?php echo escape_html($categoria['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_unidad_medida" class="form-label">
                                    Unidad de Medida <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="edit_unidad_medida" name="unidad_medida" required>
                            </div>
                            

                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_activo" name="activo" checked>
                                    <label class="form-check-label" for="edit_activo">
                                        Insumo activo
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_insumo" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Actualizar Insumo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Nueva Categoría -->
    <div class="modal fade" id="nuevaCategoriaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tags me-2"></i>Nueva Categoría
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label for="nombre_categoria" class="form-label">
                                Nombre <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" id="nombre_categoria" name="nombre_categoria" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion_categoria" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion_categoria" name="descripcion_categoria" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_categoria" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Crear Categoría
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editarInsumo(insumo) {
            $('#edit_insumo_id').val(insumo.id);
            $('#edit_codigo').val(insumo.codigo);
            $('#edit_nombre').val(insumo.nombre);
            $('#edit_descripcion').val(insumo.descripcion);
            $('#edit_categoria_id').val(insumo.categoria_id);
            $('#edit_unidad_medida').val(insumo.unidad_medida);
            // $('#edit_precio_unitario').val(insumo.precio_unitario);
            // $('#edit_stock_minimo').val(insumo.stock_minimo);

            $('#edit_activo').prop('checked', insumo.activo == 1);
            
            $('#editarInsumoModal').modal('show');
        }
        
        // Auto-submit filtros con delay
        let filterTimeout;
        $('#search').on('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(function() {
                $('form').first().submit();
            }, 500);
        });
        
        // Convertir código a mayúsculas automáticamente
        $('#codigo, #edit_codigo').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });
    </script>
</body>
</html>
