<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once './classes/Auth.php';
$auth = new Auth();

$csrf_token = generate_csrf_token();

if ($auth->verifySession()) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    error_log('Intento de login para usuario: ' . $username);
    $login_result = $auth->login($username, $password);
    error_log('Resultado de login: ' . json_encode($login_result));

    if ($login_result['success']) {
        error_log('Login exitoso. Verificando sesión...');
        if ($auth->verifySession()) {
            error_log('Sesión verificada después del login. Redirigiendo a dashboard.php');
            header('Location: dashboard.php');
            exit();
        }

        error_log('ERROR: Sesión NO verificada después del login.');
        $error_message = 'Error al establecer la sesión después del login.';
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
    <title>Iniciar sesión - <?php echo APP_NAME; ?></title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <main class="login-page">
        <section class="login-card">
            <header class="login-card__header">
                <span class="login-card__icon"><i class="fas fa-clipboard-list"></i></span>
                <h1 class="title is-4 mb-1"><?php echo escape_html(APP_NAME); ?></h1>
                <p class="subtitle is-6 has-text-grey">Entrega y seguimiento institucional</p>
            </header>

            <div class="login-card__institution">
                <strong>Dirección Departamental de Redes Integradas<br>de Servicios de Salud de Alta Verapaz</strong><br>
                Unidad de Informática
            </div>

            <?php if ($error_message): ?>
                <div class="notification is-danger is-light">
                    <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span><?php echo escape_html($error_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="notification is-success is-light">
                    <span class="icon"><i class="fas fa-check-circle"></i></span>
                    <span><?php echo escape_html($success_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="field">
                    <label for="username" class="label">Usuario</label>
                    <div class="control has-icons-left">
                        <input type="text"
                               class="input"
                               id="username"
                               name="username"
                               placeholder="Ingrese su usuario"
                               value="<?php echo isset($_POST['username']) ? escape_html($_POST['username']) : ''; ?>"
                               required
                               autocomplete="username">
                        <span class="icon is-small is-left"><i class="fas fa-user"></i></span>
                    </div>
                </div>

                <div class="field">
                    <label for="password" class="label">Contraseña</label>
                    <div class="control has-icons-left has-icons-right">
                        <input type="password"
                               class="input"
                               id="password"
                               name="password"
                               placeholder="Ingrese su contraseña"
                               required
                               autocomplete="current-password">
                        <span class="icon is-small is-left"><i class="fas fa-lock"></i></span>
                        <button type="button" class="button is-ghost is-small password-toggle" data-toggle-password>
                            <span class="icon"><i class="fas fa-eye"></i></span>
                        </button>
                    </div>
                </div>

                <div class="field">
                    <button type="submit" name="login" class="button is-primary is-fullwidth">
                        <span class="icon"><i class="fas fa-sign-in-alt"></i></span>
                        <span>Iniciar sesión</span>
                    </button>
                </div>
            </form>

            <footer class="login-card__footer">
                <p class="has-text-grey is-size-7">
                    <i class="fas fa-shield-alt me-1"></i>
                    Acceso seguro para personal autorizado.
                </p>
                <p class="has-text-grey is-size-7">
                    ¿Problemas para acceder? Contacte al administrador del sistema.
                </p>
            </footer>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.querySelector('[data-toggle-password]');
            const passwordInput = document.getElementById('password');
            if (!toggle || !passwordInput) {
                return;
            }

            toggle.addEventListener('click', () => {
                const isHidden = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isHidden ? 'text' : 'password');
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            });
        });
    </script>
</body>
</html>
