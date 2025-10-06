<?php
// includes/navbar.php
if (!isset($current_user)) {
    $current_user = [
        'nombre_completo' => 'Invitado',
        'rol' => 'Invitado',
        'id' => 0
    ];
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">
      <i class="fas fa-clipboard-list me-2"></i>
      Sistema de Conocimientos
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="dashboard.php">
            <i class="fas fa-home me-2"></i>Inicio
          </a>
        </li>

        <?php if (($current_user['rol'] ?? '') === 'Técnico' || ($current_user['rol'] ?? '') === 'Administrador'): ?>
          <li class="nav-item">
            <a class="nav-link" href="nuevo_conocimiento.php">
              <i class="fas fa-file-alt me-2"></i>Nuevo Conocimiento
            </a>
          </li>
        <?php endif; ?>

        <li class="nav-item">
          <a class="nav-link" href="reportes.php">
            <i class="fas fa-chart-line me-2"></i>Reportes
          </a>
        </li>

        <?php if (($current_user['rol'] ?? '') === 'Administrador'): ?>
          <li class="nav-item">
            <a class="nav-link" href="receptores.php">
              <i class="fas fa-user-friends me-2"></i>Receptores
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button"
               data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-cog me-2"></i>Administración
            </a>
            <ul class="dropdown-menu" aria-labelledby="adminDropdown">
              <li><a class="dropdown-item" href="usuarios.php"><i class="fas fa-users me-2"></i>Usuarios</a></li>
              <li><a class="dropdown-item" href="distritos.php"><i class="fas fa-map-marked-alt me-2"></i>Distritos</a></li>
              <li><a class="dropdown-item" href="puestos.php"><i class="fas fa-briefcase me-2"></i>Puestos</a></li>
              <li><a class="dropdown-item" href="insumos.php"><i class="fas fa-boxes me-2"></i>Insumos</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="configuracion.php"><i class="fas fa-cogs me-2"></i>Configuración</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
             data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle me-2"></i>
            <?php echo escape_html($current_user['nombre_completo'] ?? ''); ?>
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
            <?php if (($current_user['rol'] ?? 'Invitado') !== 'Invitado'): ?>
              <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
              <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>Cambiar Contraseña</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
            <?php else: ?>
              <li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión</a></li>
            <?php endif; ?>
          </ul>
        </li>
      </ul>
    </div>
  </div>
  </nav>

