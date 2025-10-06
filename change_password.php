<?php
/**
 * Cambiar contraseña (usuario autenticado)
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

// Requiere sesión
require_auth();

$auth = new Auth();
$current_user = $auth->getCurrentUser();
if (!$current_user) {
    header('Location: login.php');
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Error de seguridad. Token inválido.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        try {
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('Debe completar todos los campos.');
            }
            if ($new_password !== $confirm_password) {
                throw new Exception('Las contraseñas no coinciden.');
            }
            if (strlen($new_password) < 6) {
                throw new Exception('La contraseña debe tener al menos 6 caracteres.');
            }

            $database = new Database();
            $conn = $database->getConnection();
            
            // Cargar hash actual
            $q = $conn->prepare('SELECT password_hash FROM usuarios WHERE id = :id');
            $q->bindParam(':id', $current_user['id'], PDO::PARAM_INT);
            $q->execute();
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception('Usuario no encontrado.');
            }
            if (!password_verify($current_password, $row['password_hash'])) {
                throw new Exception('La contraseña actual no es correcta.');
            }

            // Actualizar
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $u = $conn->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
            $u->bindParam(':hash', $new_hash);
            $u->bindParam(':id', $current_user['id'], PDO::PARAM_INT);
            $u->execute();

            $success_message = 'Contraseña actualizada correctamente.';
            log_user_activity($current_user['id'], 'password_changed_self', 'El usuario cambió su propia contraseña');
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// CSRF token
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <li class="breadcrumb-item active">Cambiar Contraseña</li>
              </ol>
            </nav>

            <div class="card">
              <div class="card-header"><strong>Cambiar Contraseña</strong></div>
              <div class="card-body">
                <?php if (!empty($error_message)): ?>
                  <div class="alert alert-danger"><?php echo escape_html($error_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                  <div class="alert alert-success"><?php echo escape_html($success_message); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <div class="mb-3">
                    <label class="form-label">Contraseña Actual</label>
                    <input type="password" class="form-control" name="current_password" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Nueva Contraseña</label>
                    <input type="password" class="form-control" name="new_password" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Confirmar Nueva Contraseña</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                  </div>
                  <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
  </html>

