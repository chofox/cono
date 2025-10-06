<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Auth.php';

$database = new Database();
$conn = $database->getConnection();

$auth = new Auth();
if (!$auth->verifySession()) {
    header('Location: login.php');
    exit();
}
$current_user = $auth->getCurrentUser();
if (!$current_user) {
    header('Location: login.php');
    exit();
}

// Solo administradores
if (!has_role('Administrador')) {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$error_message = '';
$success_message = '';

try {
    // Cargar distritos
    $stmt = $conn->prepare("SELECT id, nombre, activo, fecha_creacion FROM distritos ORDER BY nombre");
    $stmt->execute();
    $distritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error cargando distritos: ' . $e->getMessage());
    $error_message = 'Error cargando los distritos';
    $distritos = [];
}

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        try {
            $conn->beginTransaction();

            if (isset($_POST['crear_distrito'])) {
                $nombre = sanitize_input($_POST['nombre'] ?? '');
                if (empty($nombre)) {
                    throw new Exception('El nombre del distrito es obligatorio');
                }
                // Verificar duplicado
                $q = $conn->prepare('SELECT id FROM distritos WHERE nombre = :nombre');
                $q->bindParam(':nombre', $nombre);
                $q->execute();
                if ($q->fetch()) {
                    throw new Exception('Ya existe un distrito con ese nombre');
                }
                $ins = $conn->prepare('INSERT INTO distritos (nombre, activo) VALUES (:nombre, 1)');
                $ins->bindParam(':nombre', $nombre);
                if (!$ins->execute()) {
                    throw new Exception('Error creando el distrito');
                }
                $success_message = 'Distrito creado exitosamente';
                log_user_activity($current_user['id'], 'district_created', "Distrito creado: {$nombre}");

            } elseif (isset($_POST['editar_distrito'])) {
                $id = (int)($_POST['distrito_id'] ?? 0);
                $nombre = sanitize_input($_POST['nombre'] ?? '');
                $activo = isset($_POST['activo']) ? 1 : 0;
                if ($id <= 0 || empty($nombre)) {
                    throw new Exception('Datos inválidos');
                }
                $q = $conn->prepare('SELECT id FROM distritos WHERE nombre = :nombre AND id != :id');
                $q->bindParam(':nombre', $nombre);
                $q->bindParam(':id', $id, PDO::PARAM_INT);
                $q->execute();
                if ($q->fetch()) {
                    throw new Exception('Ya existe otro distrito con ese nombre');
                }
                $upd = $conn->prepare('UPDATE distritos SET nombre = :nombre, activo = :activo WHERE id = :id');
                $upd->bindParam(':nombre', $nombre);
                $upd->bindParam(':activo', $activo, PDO::PARAM_INT);
                $upd->bindParam(':id', $id, PDO::PARAM_INT);
                if (!$upd->execute()) {
                    throw new Exception('Error actualizando el distrito');
                }
                $success_message = 'Distrito actualizado exitosamente';
                log_user_activity($current_user['id'], 'district_updated', "Distrito actualizado: {$nombre} (ID: {$id})");

            } elseif (isset($_POST['eliminar_distrito'])) {
                $id = (int)($_POST['distrito_id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('ID inválido');
                }
                // Verificar asociaciones
                $cntU = $conn->prepare('SELECT COUNT(*) AS c FROM usuarios WHERE distrito_id = :id');
                $cntU->bindParam(':id', $id, PDO::PARAM_INT);
                $cntU->execute();
                $hasUsers = (int)$cntU->fetch()['c'] > 0;

                $cntR = $conn->prepare('SELECT COUNT(*) AS c FROM receptores WHERE distrito_id = :id');
                $cntR->bindParam(':id', $id, PDO::PARAM_INT);
                $cntR->execute();
                $hasReceptores = (int)$cntR->fetch()['c'] > 0;

                if ($hasUsers || $hasReceptores) {
                    throw new Exception('No se puede eliminar: hay usuarios o receptores asociados');
                }
                $del = $conn->prepare('DELETE FROM distritos WHERE id = :id');
                $del->bindParam(':id', $id, PDO::PARAM_INT);
                if (!$del->execute()) {
                    throw new Exception('Error eliminando el distrito');
                }
                $success_message = 'Distrito eliminado correctamente';
                log_user_activity($current_user['id'], 'district_deleted', "Distrito eliminado (ID: {$id})");
            }

            $conn->commit();
            header('Location: distritos.php');
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = 'Error de seguridad CSRF';
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Distritos - <?php echo APP_NAME; ?></title>
  <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
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
        <div class="container mt-4">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
              <li class="breadcrumb-item active" aria-current="page">Gestión de Distritos</li>
            </ol>
          </nav>

          <div class="card main-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i>Distritos</h5>
              <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#distritoModal" id="nuevoDistritoBtn">
                <i class="fas fa-plus me-2"></i>Nuevo Distrito
              </button>
            </div>
            <div class="card-body">
              <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                  <i class="fas fa-exclamation-triangle me-2"></i>
                  <?php echo escape_html($error_message); ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                  <i class="fas fa-check-circle me-2"></i>
                  <?php echo escape_html($success_message); ?>
                </div>
              <?php endif; ?>

              <div class="table-responsive">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Nombre</th>
                      <th>Activo</th>
                      <th>Fecha creación</th>
                      <th style="width: 220px;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($distritos)): ?>
                      <?php foreach ($distritos as $d): ?>
                        <tr>
                          <td><?php echo escape_html($d['nombre']); ?></td>
                          <td>
                            <?php if (!empty($d['activo'])): ?>
                              <span class="badge bg-success">Sí</span>
                            <?php else: ?>
                              <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                          </td>
                          <td><?php echo escape_html(format_datetime($d['fecha_creacion'])); ?></td>
                          <td>
                            <div class="btn-group">
                              <button class="btn btn-sm btn-warning btn-editar-distrito"
                                      data-bs-toggle="modal" data-bs-target="#distritoModal"
                                      data-id="<?php echo $d['id']; ?>"
                                      data-nombre="<?php echo escape_html($d['nombre']); ?>"
                                      data-activo="<?php echo (int)$d['activo']; ?>">
                                <i class="fas fa-edit"></i> Editar
                              </button>
                              <button class="btn btn-sm btn-danger btn-eliminar-distrito"
                                      data-id="<?php echo $d['id']; ?>">
                                <i class="fas fa-trash"></i> Eliminar
                              </button>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted">No hay distritos</td>
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

  <!-- Modal Crear/Editar Distrito -->
  <div class="modal fade" id="distritoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="distritoModalLabel">Nuevo Distrito</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="">
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="distrito_id" id="distrito_id">
            <div class="mb-3">
              <label class="form-label">Nombre <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="nombre" id="distrito_nombre" required>
            </div>
            <div class="form-check" id="distrito_activo_group" style="display: none;">
              <input class="form-check-input" type="checkbox" id="distrito_activo" name="activo" value="1">
              <label class="form-check-label" for="distrito_activo">Activo</label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" name="crear_distrito" id="btn_crear_distrito">
              <i class="fas fa-save me-2"></i>Guardar
            </button>
            <button type="submit" class="btn btn-primary" name="editar_distrito" id="btn_editar_distrito" style="display: none;">
              <i class="fas fa-save me-2"></i>Guardar Cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Form eliminar -->
  <form id="formEliminarDistrito" method="POST" action="" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="distrito_id" id="eliminar_distrito_id">
    <input type="hidden" name="eliminar_distrito" value="1">
  </form>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Modo crear
    document.getElementById('nuevoDistritoBtn')?.addEventListener('click', function() {
      document.getElementById('distritoModalLabel').textContent = 'Nuevo Distrito';
      document.getElementById('distrito_id').value = '';
      document.getElementById('distrito_nombre').value = '';
      document.getElementById('distrito_activo_group').style.display = 'none';
      document.getElementById('btn_crear_distrito').style.display = '';
      document.getElementById('btn_editar_distrito').style.display = 'none';
    });

    // Modo editar
    document.querySelectorAll('.btn-editar-distrito').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const nombre = this.getAttribute('data-nombre');
        const activo = this.getAttribute('data-activo') === '1';
        document.getElementById('distritoModalLabel').textContent = 'Editar Distrito';
        document.getElementById('distrito_id').value = id;
        document.getElementById('distrito_nombre').value = nombre || '';
        document.getElementById('distrito_activo').checked = activo;
        document.getElementById('distrito_activo_group').style.display = '';
        document.getElementById('btn_crear_distrito').style.display = 'none';
        document.getElementById('btn_editar_distrito').style.display = '';
      });
    });

    // Eliminar
    document.querySelectorAll('.btn-eliminar-distrito').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        if (confirm('¿Está seguro de eliminar este distrito?')) {
          document.getElementById('eliminar_distrito_id').value = id;
          document.getElementById('formEliminarDistrito').submit();
        }
      });
    });
  </script>
</body>
</html>

