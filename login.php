<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once './classes/Auth.php';
$auth = new Auth();

$csrf_token = generate_csrf_token(); // Inicializar $csrf_token

// Redirigir si ya está logueado
if ($auth->verifySession()) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = ''; // Inicializar $success_message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    error_log("Intento de login para usuario: " . $username); // Log antes de la llamada a login
    $login_result = $auth->login($username, $password);
    error_log("Resultado de login: " . json_encode($login_result)); // Log después de la llamada a login

    if ($login_result['success']) {
        error_log("Login exitoso. Verificando sesión...");
        if ($auth->verifySession()) {
            error_log("Sesión verificada después del login. Redirigiendo a dashboard.php");
            header('Location: dashboard.php');
            exit();
        } else {
            error_log("ERROR: Sesión NO verificada después del login.");
            $error_message = "Error al establecer la sesión después del login.";
        }
    } else {
        $error_message = $login_result['message'];
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-container">
                    <div class="login-header">
                        <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                        <h3 class="mb-0">Sistema de Conocimientos</h3>
                        <p class="mb-0">Entrega de Insumos</p>
                    </div>
                    
                    <div class="login-body">
                        <div class="institution-info">
                            <strong>Dirección Departamental de Redes Integradas<br>
                            de Servicios de Salud de Alta Verapaz</strong><br>
                            Unidad de Informática
                        </div>
                        
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
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Usuario
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           placeholder="Ingrese su usuario"
                                           value="<?php echo isset($_POST['username']) ? escape_html($_POST['username']) : ''; ?>"
                                           required 
                                           autocomplete="username">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Contraseña
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Ingrese su contraseña"
                                           required 
                                           autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="login" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Iniciar Sesión
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                Acceso seguro y protegido
                            </small>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                ¿Problemas para acceder? Contacte al administrador del sistema
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="version-info">
        <i class="fas fa-code me-1"></i>
        Versión <?php echo APP_VERSION; ?>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto-focus en el campo de usuario
        document.getElementById('username').focus();
        
        // Prevenir envío múltiple del formulario
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Iniciando sesión...';
        });
    </script>
</body>
</html>