<?php
// includes/sidebar.php
?>
<aside class="menu app-sidebar">
    <p class="menu-label">Navegación</p>
    <ul class="menu-list">
        <li>
            <a href="<?php echo APP_URL; ?>/dashboard.php" class="is-flex is-align-items-center">
                <span class="icon"><i class="fas fa-tachometer-alt"></i></span>
                <span>Dashboard</span>
            </a>
        </li>
    </ul>

    <p class="menu-label">Conocimientos</p>
    <ul class="menu-list">
        <?php if (function_exists('has_role') ? (has_role('Técnico') || has_role('Administrador')) : true): ?>
            <li>
                <a href="<?php echo APP_URL; ?>/modules/conocimientos/pages/crear.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-plus-circle"></i></span>
                    <span>Nuevo conocimiento</span>
                </a>
            </li>
        <?php endif; ?>
        <li>
            <a href="<?php echo APP_URL; ?>/modules/conocimientos/index.php" class="is-flex is-align-items-center">
                <span class="icon"><i class="fas fa-list"></i></span>
                <span>Listado</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/modules/conocimientos/pages/reportes.php" class="is-flex is-align-items-center">
                <span class="icon"><i class="fas fa-chart-bar"></i></span>
                <span>Reportes</span>
            </a>
        </li>
    </ul>

    <?php if (function_exists('has_any_role') ? has_any_role(['Técnico', 'Supervisor', 'Administrador']) : true): ?>
        <p class="menu-label">Repuestos</p>
        <ul class="menu-list">
            <li>
                <a href="<?php echo APP_URL; ?>/modules/repuestos/index.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-toolbox"></i></span>
                    <span>Listado de repuestos</span>
                </a>
            </li>
        </ul>
    <?php endif; ?>

    <?php if (function_exists('has_any_role') ? has_any_role(['Recepcionista', 'Supervisor', 'Técnico', 'Administrador']) : true): ?>
        <p class="menu-label">Mantenimiento</p>
        <ul class="menu-list">
            <li>
                <a href="<?php echo APP_URL; ?>/modules/mantenimiento/index.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-tools"></i></span>
                    <span>Panel de mantenimientos</span>
                </a>
            </li>
            <?php if (function_exists('has_any_role') ? has_any_role(['Recepcionista', 'Administrador']) : true): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/recepcion.php" class="is-flex is-align-items-center">
                        <span class="icon"><i class="fas fa-inbox"></i></span>
                        <span>Recepción de equipo</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (function_exists('has_any_role') ? has_any_role(['Supervisor', 'Administrador']) : true): ?>
                <li>
                    <a href="<?php echo APP_URL; ?>/modules/mantenimiento/pages/reportes.php" class="is-flex is-align-items-center">
                        <span class="icon"><i class="fas fa-file-alt"></i></span>
                        <span>Reportes de mantenimiento</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>

    <?php if (function_exists('has_role') ? has_role('Administrador') : true): ?>
        <p class="menu-label">Administración</p>
        <ul class="menu-list">
            <li>
                <a href="<?php echo APP_URL; ?>/usuarios.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-users"></i></span>
                    <span>Usuarios</span>
                </a>
            </li>
            <li>
                <a href="<?php echo APP_URL; ?>/receptores.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-user-friends"></i></span>
                    <span>Receptores</span>
                </a>
            </li>
            <li>
                <a href="<?php echo APP_URL; ?>/distritos.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-map-marked-alt"></i></span>
                    <span>Distritos</span>
                </a>
            </li>
            <li>
                <a href="<?php echo APP_URL; ?>/puestos.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-briefcase"></i></span>
                    <span>Puestos</span>
                </a>
            </li>
            <li>
                <a href="<?php echo APP_URL; ?>/insumos.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-boxes"></i></span>
                    <span>Catálogo de insumos</span>
                </a>
            </li>
            <li>
                <a href="<?php echo APP_URL; ?>/configuracion.php" class="is-flex is-align-items-center">
                    <span class="icon"><i class="fas fa-cog"></i></span>
                    <span>Configuración</span>
                </a>
            </li>
        </ul>
    <?php endif; ?>
</aside>
