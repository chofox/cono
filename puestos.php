<?php
session_start(); // Iniciar la sesión al principio del script

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Puesto.php';

// Inicializar conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

$auth = new Auth();
$puesto = new Puesto($conn); // Instanciar la clase Puesto con la conexión

// Verificar sesión y obtener usuario actual
if (!$auth->verifySession()) {
    header("Location: login.php");
    exit();
}
$current_user = $auth->getCurrentUser();

if (!$current_user) {
    // Si por alguna razón el usuario actual no se encuentra después de la verificación de la sesión
    header("Location: login.php");
    exit();
}

// Requerir rol Administrador para acceder
if (!$auth->hasPermission('Administrador')) {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$puestos = [];
$error_message = '';
$success_message = '';

try {
    // Obtener puestos usando la clase Puesto
    $stmt = $puesto->getAll();
    $puestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error cargando puestos: " . $e->getMessage());
    $error_message = "Error cargando los puestos";
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        try {
            $conn->beginTransaction();

            $accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

            if ($accion === 'crear' || isset($_POST['crear_puesto'])) {
                $puesto->nombre = sanitize_input($_POST['nombre']);

                if (empty($puesto->nombre)) {
                    throw new Exception("El nombre del puesto no puede estar vacío.");
                }

                if (!$puesto->create()) {
                    throw new Exception("Error al crear el puesto.");
                }
                $success_message = "Puesto creado exitosamente.";
                log_user_activity($current_user['id'], 'puesto_created', "Puesto creado: {$puesto->nombre}");

            } elseif ($accion === 'editar' || isset($_POST['editar_puesto'])) {
                $puesto->id = (int)$_POST['puesto_id'];
                $puesto->nombre = sanitize_input($_POST['nombre']);
                $puesto->activo = isset($_POST['activo']) ? 1 : 0;

                if (empty($puesto->nombre)) {
                    throw new Exception("El nombre del puesto no puede estar vacío.");
                }

                if (!$puesto->update()) {
                    throw new Exception("Error al actualizar el puesto.");
                }
                $success_message = "Puesto actualizado exitosamente.";
                log_user_activity($current_user['id'], 'puesto_updated', "Puesto actualizado: {$puesto->nombre} (ID: {$puesto->id})");

            } elseif ($accion === 'eliminar' || isset($_POST['eliminar_puesto'])) {
                $puesto->id = (int)$_POST['puesto_id'];

                // Verificar si hay usuarios o receptores asociados a este puesto
                if ($puesto->hasAssociatedUsers()) {
                    throw new Exception("No se puede eliminar el puesto porque hay usuarios asociados.");
                }

                if ($puesto->hasAssociatedReceptores()) {
                    throw new Exception("No se puede eliminar el puesto porque hay receptores asociados.");
                }

                if (!$puesto->delete()) {
                    throw new Exception("Error al eliminar el puesto.");
                }
                $success_message = "Puesto eliminado exitosamente.";
                log_user_activity($current_user['id'], 'puesto_deleted', "Puesto eliminado (ID: {$puesto->id})");
            }

            $conn->commit();
            // Recargar puestos después de la operación
            header("Location: puestos.php");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = $e->getMessage();
            error_log("Error en gestión de puestos: " . $e->getMessage());
        }
    } else {
        $error_message = "Error de seguridad CSRF.";
    }
}

$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Puestos</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
        <h1 class="mb-4">Gestión de Puestos</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo escape_html($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo escape_html($success_message); ?></div>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#puestoModal" id="addPuestoBtn">
            <i class="fas fa-plus"></i> Nuevo Puesto
        </button>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Activo</th>
                        <th>Fecha Creación</th>
                        <th>Última Actualización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($puestos) > 0): ?>
                        <?php foreach ($puestos as $puesto): ?>
                            <tr>
                                <td><?php echo escape_html($puesto['id']); ?></td>
                                <td><?php echo escape_html($puesto['nombre']); ?></td>
                                <td>
                                    <?php if ($puesto['activo']): ?>
                                        <span class="badge bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo escape_html(format_datetime($puesto['fecha_creacion'])); ?></td>
                                <td><?php echo escape_html(format_datetime($puesto['fecha_actualizacion'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-puesto-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#puestoModal"
                                            data-id="<?php echo $puesto['id']; ?>"
                                            data-nombre="<?php echo escape_html($puesto['nombre']); ?>"
                                            data-activo="<?php echo $puesto['activo']; ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-puesto-btn"
                                            data-id="<?php echo $puesto['id']; ?>"
                                            data-nombre="<?php echo escape_html($puesto['nombre']); ?>">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay puestos registrados.</td>
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

    <!-- Modal para Crear/Editar Puesto -->
    <div class="modal fade" id="puestoModal" tabindex="-1" aria-labelledby="puestoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="puestoModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="puestoForm" method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="puesto_id" id="puesto_id">
                        <input type="hidden" name="accion" id="accion_puesto" value="crear">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Puesto <span class="required">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="mb-3" id="activo-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                                <label class="form-check-label" for="activo">
                                    Activo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary" id="submitPuestoBtn"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var puestoModal = document.getElementById('puestoModal');
            var puestoForm = document.getElementById('puestoForm');
            var submitBtnGlobal = document.getElementById('submitPuestoBtn');

            puestoModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var form = puestoModal.querySelector('#puestoForm');
                var modalTitle = puestoModal.querySelector('.modal-title');
                var submitBtn = puestoModal.querySelector('#submitPuestoBtn');
                var activoGroup = puestoModal.querySelector('#activo-group');

                form.reset(); // Limpiar el formulario

                if (button.id === 'addPuestoBtn') {
                    modalTitle.textContent = 'Crear Nuevo Puesto';
                    submitBtn.textContent = 'Crear Puesto';
                    submitBtn.name = 'crear_puesto';
                    document.getElementById('accion_puesto').value = 'crear';
                    activoGroup.style.display = 'none'; // Ocultar activo al crear
                    puestoModal.querySelector('#activo').checked = true; // Por defecto activo al crear
                } else {
                    modalTitle.textContent = 'Editar Puesto';
                    submitBtn.textContent = 'Guardar Cambios';
                    submitBtn.name = 'editar_puesto';
                    document.getElementById('accion_puesto').value = 'editar';
                    activoGroup.style.display = 'block'; // Mostrar activo al editar

                    var id = button.getAttribute('data-id');
                    var nombre = button.getAttribute('data-nombre');
                    var activo = button.getAttribute('data-activo');

                    puestoModal.querySelector('#puesto_id').value = id;
                    puestoModal.querySelector('#nombre').value = nombre;
                    puestoModal.querySelector('#activo').checked = (activo == 1);
                }
            });

            // Al enviar el formulario, deshabilitar botón y cerrar modal (UX)
            if (puestoForm) {
                puestoForm.addEventListener('submit', function () {
                    if (submitBtnGlobal) {
                        submitBtnGlobal.disabled = true;
                        submitBtnGlobal.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
                    }
                    try {
                        var modalInst = bootstrap.Modal.getInstance(puestoModal);
                        if (modalInst) modalInst.hide();
                    } catch (e) { /* ignore */ }
                });
            }

            // Manejar eliminación de puesto
            document.querySelectorAll('.delete-puesto-btn').forEach(button => {
                button.addEventListener('click', function() {
                    var puestoId = this.getAttribute('data-id');
                    var puestoNombre = this.getAttribute('data-nombre');

                    if (confirm('¿Está seguro de que desea eliminar el puesto "' + puestoNombre + '"? Esta acción no se puede deshacer.')) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';

                        var csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = 'csrf_token';
                        csrfInput.value = '<?php echo $csrf_token; ?>';
                        form.appendChild(csrfInput);

                        var idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'puesto_id';
                        idInput.value = puestoId;
                        form.appendChild(idInput);

                        var deleteInput = document.createElement('input');
                        deleteInput.type = 'hidden';
                        deleteInput.name = 'eliminar_puesto';
                        deleteInput.value = '1';
                        form.appendChild(deleteInput);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>
