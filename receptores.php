<?php
error_log("DEBUG: receptores.php - Script started.");
/**
 * Gestión de Receptores
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'includes/functions.php';

// Verificar autenticación y rol
require_auth();
$auth = new Auth();
$current_user = $auth->getCurrentUser();

// Solo administradores pueden gestionar receptores
if ($current_user['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

$database = new Database();
$conn = $database->getConnection();

// Obtener distritos para el formulario
$query_distritos = "SELECT id, nombre FROM distritos WHERE activo = 1 ORDER BY nombre";
$stmt_distritos = $conn->prepare($query_distritos);
$stmt_distritos->execute();
$distritos = $stmt_distritos->fetchAll(PDO::FETCH_ASSOC);

// Obtener puestos para el formulario
$query_puestos = "SELECT id, nombre FROM puestos WHERE activo = 1 ORDER BY nombre";
$stmt_puestos = $conn->prepare($query_puestos);
$stmt_puestos->execute();
$puestos = $stmt_puestos->fetchAll(PDO::FETCH_ASSOC);

// Lógica para agregar/editar receptor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Error de seguridad: Token CSRF inválido.';
    } else {
        $nombre_completo = sanitize_input($_POST['nombre_completo']);
        $puesto_id = (int)$_POST['puesto_id'];
        $distrito_id = (int)$_POST['distrito_id'];
        $telefono = sanitize_input($_POST['telefono']);
        $email = sanitize_input($_POST['email']);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre_completo) || empty($distrito_id) || empty($puesto_id)) {
            $error_message = 'El nombre completo, el puesto y el distrito son campos obligatorios.';
        } else {
            try {
                if ($action === 'add') {
                    $query = "INSERT INTO receptores (nombre_completo, puesto_id, distrito_id, telefono, email, activo) VALUES (:nombre_completo, :puesto_id, :distrito_id, :telefono, :email, :activo)";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':nombre_completo', $nombre_completo);
                    $stmt->bindParam(':puesto_id', $puesto_id);
                    $stmt->bindParam(':distrito_id', $distrito_id);
                    $stmt->bindParam(':telefono', $telefono);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':activo', $activo);
                    $stmt->execute();
                    log_user_activity($current_user['id'], 'receptor_added', "Receptor '{$nombre_completo}' agregado.");
                    $success_message = 'Receptor agregado exitosamente.';
                } elseif ($action === 'edit') {
                    $id = (int)$_POST['id'];
                    $query = "UPDATE receptores SET nombre_completo = :nombre_completo, puesto_id = :puesto_id, distrito_id = :distrito_id, telefono = :telefono, email = :email, activo = :activo WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':nombre_completo', $nombre_completo);
                    $stmt->bindParam(':puesto_id', $puesto_id);
                    $stmt->bindParam(':distrito_id', $distrito_id);
                    $stmt->bindParam(':telefono', $telefono);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':activo', $activo);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    log_user_activity($current_user['id'], 'receptor_updated', "Receptor '{$nombre_completo}' (ID: {$id}) actualizado.");
                    $success_message = 'Receptor actualizado exitosamente.';
                }
            } catch (PDOException $e) {
                error_log("Error al guardar receptor: " . $e->getMessage());
                $error_message = 'Error al guardar el receptor: ' . $e->getMessage();
                // Log additional debugging info
                error_log("POST data: " . print_r($_POST, true));
                error_log("SQL query: $query");
            }
        }
    }
}

// Lógica para eliminar receptor
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $csrf_token = $_GET['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Error de seguridad: Token CSRF inválido.';
    } else {
        try {
            $query = "DELETE FROM receptores WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            log_user_activity($current_user['id'], 'receptor_deleted', "Receptor (ID: {$id}) eliminado.");
            $success_message = 'Receptor eliminado exitosamente.';
        } catch (PDOException $e) {
            error_log("Error al eliminar receptor: " . $e->getMessage());
            $error_message = 'Error al eliminar el receptor: ' . $e->getMessage();
        }
    }
}

// Obtener lista de receptores
$receptores = [];
try {
    $query = "SELECT r.id,
                     r.nombre_completo,
                     COALESCE(p.nombre, r.puesto) AS puesto_nombre,
                     r.puesto_id,
                     r.distrito_id,
                     d.nombre AS distrito,
                     r.telefono,
                     r.email,
                     r.activo
              FROM receptores r
              LEFT JOIN distritos d ON r.distrito_id = d.id
              LEFT JOIN puestos p ON r.puesto_id = p.id
              ORDER BY r.nombre_completo";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $receptores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar receptores: " . $e->getMessage());
    $error_message = 'Error al cargar la lista de receptores.';
}

$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Receptores - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <!-- CSS Globales -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
          <div class="container mt-4">
    
    <div class="container mt-5">
        <h1 class="mb-4">Gestión de Receptores</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#receptorModal" data-action="add">
                    <i class="fas fa-plus me-2"></i>Agregar Nuevo Receptor
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Puesto</th>
                                <th>Distrito</th>
                                <th>Teléfono</th>
                                <th>Email</th>
                                <th>Activo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($receptores) > 0): ?>
                                <?php foreach ($receptores as $receptor): ?>
                                    <tr>
                                        <td><?php echo escape_html($receptor['nombre_completo']); ?></td>
                                        <td><?php echo escape_html($receptor['puesto_nombre']); ?></td>
                                        <td><?php echo escape_html($receptor['distrito']); ?></td>
                                        <td><?php echo escape_html($receptor['telefono']); ?></td>
                                        <td><?php echo escape_html($receptor['email']); ?></td>
                                        <td>
                                            <?php if ($receptor['activo']): ?>
                                                <span class="badge bg-success">Sí</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-receptor-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#receptorModal" 
                                                    data-action="edit"
                                                    data-id="<?php echo $receptor['id']; ?>"
                                                    data-nombre_completo="<?php echo escape_html($receptor['nombre_completo']); ?>"
                                                    data-puesto_id="<?php echo isset($receptor['puesto_id']) ? $receptor['puesto_id'] : ''; ?>"
                                                    data-distrito_id="<?php echo isset($receptor['distrito_id']) ? $receptor['distrito_id'] : ''; ?>"
                                                    data-telefono="<?php echo escape_html($receptor['telefono']); ?>"
                                                    data-email="<?php echo escape_html($receptor['email']); ?>"
                                                    data-activo="<?php echo $receptor['activo']; ?>">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                            <a href="receptores.php?action=delete&id=<?php echo $receptor['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('¿Está seguro de que desea eliminar este receptor?');">
                                                <i class="fas fa-trash-alt"></i> Eliminar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay receptores registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal para Agregar/Editar Receptor -->
    <div class="modal fade" id="receptorModal" tabindex="-1" aria-labelledby="receptorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receptorModalLabel">Agregar/Editar Receptor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="receptorForm" method="POST" action="receptores.php">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="id" id="receptorId">
                        
                        <div class="mb-3">
                            <label for="nombre_completo" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                        </div>
                        <div class="mb-3">
                            <label for="puesto_id" class="form-label">Puesto</label>
                            <select class="form-select select2" id="puesto_id" name="puesto_id" required>
                                <option value="">Seleccione un puesto</option>
                                <?php foreach ($puestos as $puesto): ?>
                                    <option value="<?php echo $puesto['id']; ?>"><?php echo escape_html($puesto['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="distrito_id" class="form-label">Distrito</label>
                            <select class="form-select select2" id="distrito_id" name="distrito_id" required>
                                <option value="">Seleccione un distrito</option>
                                <?php foreach ($distritos as $distrito): ?>
                                    <option value="<?php echo $distrito['id']; ?>"><?php echo escape_html($distrito['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" checked>
                            <label class="form-check-label" for="activo">Activo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Guardar Receptor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (necesario para Select2) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                dropdownParent: $('#receptorModal')
            });

            $('#receptorModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget); // Botón que activó el modal
                var action = button.data('action'); // Extraer información de los atributos data-*
                var modal = $(this);

                modal.find('.modal-title').text(action === 'add' ? 'Agregar Nuevo Receptor' : 'Editar Receptor');
                modal.find('#formAction').val(action);
                
                if (action === 'edit') {
                    var id = button.data('id');
                    var nombre_completo = button.data('nombre_completo');
                    var puesto_id = button.data('puesto_id');
                    var distrito_id = button.data('distrito_id');
                    var telefono = button.data('telefono');
                    var email = button.data('email');
                    var activo = button.data('activo');

                    modal.find('#receptorId').val(id);
                    modal.find('#nombre_completo').val(nombre_completo);
                    modal.find('#puesto_id').val(puesto_id).trigger('change');
                    modal.find('#distrito_id').val(distrito_id).trigger('change');
                    modal.find('#telefono').val(telefono);
                    modal.find('#email').val(email);
                    modal.find('#activo').prop('checked', activo == 1);
                } else {
                    // Limpiar el formulario para 'add'
                    modal.find('#receptorId').val('');
                    modal.find('#nombre_completo').val('');
                    modal.find('#puesto_id').val('').trigger('change');
                    modal.find('#distrito_id').val('').trigger('change');
                    modal.find('#telefono').val('');
                    modal.find('#email').val('');
                    modal.find('#activo').prop('checked', true); // Por defecto activo
                }
            });
        });
    </script>
</body>
</html>
