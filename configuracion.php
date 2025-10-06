<?php
/**
 * Configuración del Sistema
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Auth.php';

// Requerir sesión y rol administrador
require_auth();
$auth = new Auth();
$current_user = $auth->getCurrentUser();
if (!$current_user || !has_role('Administrador')) {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$database = new Database();
$conn = $database->getConnection();

$error_message = '';
$success_message = '';

// Acciones POST (crear/actualizar/eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error_message = 'Error de seguridad CSRF.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add') {
                $clave = trim(sanitize_input($_POST['clave'] ?? '')); 
                $valor = trim($_POST['valor'] ?? '');
                $descripcion = trim(sanitize_input($_POST['descripcion'] ?? ''));

                if ($clave === '' || $valor === '') {
                    throw new Exception('La clave y el valor son obligatorios.');
                }
                // Validar duplicado
                $q = $conn->prepare('SELECT id FROM configuracion_sistema WHERE clave = :clave');
                $q->bindParam(':clave', $clave);
                $q->execute();
                if ($q->fetch()) {
                    throw new Exception('Ya existe una configuración con esa clave.');
                }
                $ins = $conn->prepare('INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES (:clave, :valor, :descripcion)');
                $ins->bindParam(':clave', $clave);
                $ins->bindParam(':valor', $valor);
                $ins->bindParam(':descripcion', $descripcion);
                if (!$ins->execute()) {
                    throw new Exception('Error creando la configuración.');
                }
                log_user_activity($current_user['id'], 'config_created', "Clave: {$clave}");
                $success_message = 'Configuración creada correctamente.';

            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $clave = trim(sanitize_input($_POST['clave'] ?? '')); 
                $valor = trim($_POST['valor'] ?? '');
                $descripcion = trim(sanitize_input($_POST['descripcion'] ?? ''));
                if ($id <= 0 || $clave === '' || $valor === '') {
                    throw new Exception('Datos inválidos.');
                }
                $q = $conn->prepare('SELECT id FROM configuracion_sistema WHERE clave = :clave AND id != :id');
                $q->bindParam(':clave', $clave);
                $q->bindParam(':id', $id, PDO::PARAM_INT);
                $q->execute();
                if ($q->fetch()) {
                    throw new Exception('Ya existe otra configuración con esa clave.');
                }
                $upd = $conn->prepare('UPDATE configuracion_sistema SET clave = :clave, valor = :valor, descripcion = :descripcion WHERE id = :id');
                $upd->bindParam(':clave', $clave);
                $upd->bindParam(':valor', $valor);
                $upd->bindParam(':descripcion', $descripcion);
                $upd->bindParam(':id', $id, PDO::PARAM_INT);
                if (!$upd->execute()) {
                    throw new Exception('Error actualizando la configuración.');
                }
                log_user_activity($current_user['id'], 'config_updated', "ID: {$id}, Clave: {$clave}");
                $success_message = 'Configuración actualizada correctamente.';

            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('ID inválido.');
                }
                $del = $conn->prepare('DELETE FROM configuracion_sistema WHERE id = :id');
                $del->bindParam(':id', $id, PDO::PARAM_INT);
                if (!$del->execute()) {
                    throw new Exception('Error eliminando la configuración.');
                }
                log_user_activity($current_user['id'], 'config_deleted', "ID: {$id}");
                $success_message = 'Configuración eliminada correctamente.';
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Listado de configuraciones
try {
    $stmt = $conn->prepare('SELECT id, clave, valor, descripcion, fecha_actualizacion FROM configuracion_sistema ORDER BY clave');
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $configs = [];
    $error_message = 'Error cargando configuraciones.';
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuración - <?php echo APP_NAME; ?></title>
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
              <li class="breadcrumb-item active" aria-current="page">Configuración</li>
            </ol>
          </nav>

          <div class="card main-card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Configuración del Sistema</h5>
              <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#configModal" id="btnNuevaConfig">
                <i class="fas fa-plus me-2"></i>Nueva clave
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
                      <th>Clave</th>
                      <th>Valor</th>
                      <th>Descripción</th>
                      <th>Actualizado</th>
                      <th style="width: 220px;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($configs)): ?>
                      <?php foreach ($configs as $c): ?>
                        <tr>
                          <td><code><?php echo escape_html($c['clave']); ?></code></td>
                          <td><?php echo nl2br(escape_html($c['valor'])); ?></td>
                          <td><?php echo escape_html($c['descripcion']); ?></td>
                          <td><?php echo escape_html(format_datetime($c['fecha_actualizacion'])); ?></td>
                          <td>
                            <div class="btn-group">
                              <button class="btn btn-sm btn-warning btn-editar-config"
                                      data-bs-toggle="modal" data-bs-target="#configModal"
                                      data-id="<?php echo $c['id']; ?>"
                                      data-clave="<?php echo escape_html($c['clave']); ?>"
                                      data-valor="<?php echo htmlspecialchars($c['valor'], ENT_QUOTES, 'UTF-8'); ?>"
                                      data-desc="<?php echo escape_html($c['descripcion']); ?>">
                                <i class="fas fa-edit"></i> Editar
                              </button>
                              <form method="POST" action="" class="ms-2" onsubmit="return confirm('¿Eliminar esta clave?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                  <i class="fas fa-trash"></i> Eliminar
                                </button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="5" class="text-center text-muted">No hay configuraciones</td>
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

  <!-- Modal Crear/Editar Config -->
  <div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="configModalLabel">Nueva clave</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="">
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" id="config_action" value="add">
            <input type="hidden" name="id" id="config_id">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Clave <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="clave" id="config_clave" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Descripción</label>
                <input type="text" class="form-control" name="descripcion" id="config_desc">
              </div>
              <div class="col-12">
                <label class="form-label">Valor <span class="text-danger">*</span></label>
                <textarea class="form-control" name="valor" id="config_valor" rows="4" required></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="btn_guardar_config">
              <i class="fas fa-save me-2"></i>Guardar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Preparar modal
    document.getElementById('btnNuevaConfig')?.addEventListener('click', function() {
      document.getElementById('configModalLabel').textContent = 'Nueva clave';
      document.getElementById('config_action').value = 'add';
      document.getElementById('config_id').value = '';
      document.getElementById('config_clave').value = '';
      document.getElementById('config_desc').value = '';
      document.getElementById('config_valor').value = '';
      document.getElementById('config_clave').readOnly = false;
    });

    document.querySelectorAll('.btn-editar-config').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const clave = this.getAttribute('data-clave');
        const valor = this.getAttribute('data-valor');
        const desc = this.getAttribute('data-desc');
        document.getElementById('configModalLabel').textContent = 'Editar clave';
        document.getElementById('config_action').value = 'edit';
        document.getElementById('config_id').value = id;
        document.getElementById('config_clave').value = clave;
        document.getElementById('config_desc').value = desc || '';
        document.getElementById('config_valor').value = valor || '';
        // Opcional: clave no editable para evitar conflictos de única
        document.getElementById('config_clave').readOnly = false;
      });
    });
  </script>
</body>
</html>

