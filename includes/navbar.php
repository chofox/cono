<?php
// includes/navbar.php
if (!isset($current_user)) {
    $current_user = [
        'nombre_completo' => 'Invitado',
        'rol' => 'Invitado',
        'id' => 0,
    ];
}
?>

<nav class="navbar is-spaced is-primary" role="navigation" aria-label="main navigation">
    <div class="container">
        <div class="navbar-brand">
            <a class="navbar-item is-uppercase has-text-weight-semibold" href="<?php echo APP_URL; ?>/dashboard.php">
                <span class="icon-text">
                    <span class="icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Sistema de Conocimiento</span>
                </span>
            </a>

            <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="app-navbar">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </a>
        </div>

        <div id="app-navbar" class="navbar-menu">
            <div class="navbar-start">
                <a class="navbar-item" href="<?php echo APP_URL; ?>/dashboard.php">
                    <span class="icon-text">
                        <span class="icon"><i class="fas fa-home"></i></span>
                        <span>Inicio</span>
                    </span>
                </a>

                <?php if (($current_user['rol'] ?? '') === 'Técnico' || ($current_user['rol'] ?? '') === 'Administrador'): ?>
                    <a class="navbar-item" href="<?php echo APP_URL; ?>/modules/conocimientos/pages/crear.php">
                        <span class="icon-text">
                            <span class="icon"><i class="fas fa-file-alt"></i></span>
                            <span>Nuevo conocimiento</span>
                        </span>
                    </a>
                <?php endif; ?>

                <a class="navbar-item" href="<?php echo APP_URL; ?>/modules/conocimientos/pages/reportes.php">
                    <span class="icon-text">
                        <span class="icon"><i class="fas fa-chart-line"></i></span>
                        <span>Reportes</span>
                    </span>
                </a>

                <?php if (($current_user['rol'] ?? '') === 'Administrador'): ?>
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link">
                            <span class="icon-text">
                                <span class="icon"><i class="fas fa-cog"></i></span>
                                <span>Administración</span>
                            </span>
                        </a>
                        <div class="navbar-dropdown">
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/usuarios.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-users"></i></span>
                                    <span>Usuarios</span>
                                </span>
                            </a>
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/distritos.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-map-marked-alt"></i></span>
                                    <span>Distritos</span>
                                </span>
                            </a>
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/puestos.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-briefcase"></i></span>
                                    <span>Puestos</span>
                                </span>
                            </a>
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/insumos.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-boxes"></i></span>
                                    <span>Insumos</span>
                                </span>
                            </a>
                            <hr class="navbar-divider">
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/configuracion.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-cogs"></i></span>
                                    <span>Configuración</span>
                                </span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="navbar-end">
                <div class="navbar-item has-dropdown is-hoverable">
                    <a class="navbar-link">
                        <span class="icon-text">
                            <span class="icon"><i class="fas fa-user-circle"></i></span>
                            <span><?php echo escape_html($current_user['nombre_completo'] ?? ''); ?></span>
                        </span>
                    </a>
                    <div class="navbar-dropdown is-right">
                        <?php if (($current_user['rol'] ?? 'Invitado') !== 'Invitado'): ?>
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/profile.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-user"></i></span>
                                    <span>Mi perfil</span>
                                </span>
                            </a>
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/change_password.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-key"></i></span>
                                    <span>Cambiar contraseña</span>
                                </span>
                            </a>
                            <hr class="navbar-divider">
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/logout.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
                                    <span>Cerrar sesión</span>
                                </span>
                            </a>
                        <?php else: ?>
                            <a class="navbar-item" href="<?php echo APP_URL; ?>/login.php">
                                <span class="icon-text">
                                    <span class="icon"><i class="fas fa-sign-in-alt"></i></span>
                                    <span>Iniciar sesión</span>
                                </span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const burger = document.querySelector('.navbar-burger');
        const target = burger ? burger.getAttribute('data-target') : null;
        const menu = target ? document.getElementById(target) : null;
        if (burger && menu) {
            burger.addEventListener('click', () => {
                burger.classList.toggle('is-active');
                menu.classList.toggle('is-active');
            });
        }
    });
</script>
