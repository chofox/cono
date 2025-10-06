<?php
// includes/sidebar.php
// Requiere helpers de roles si no están cargados desde la página
?>
<div class="sidebar">
  <div class="p-3">
    <h6 class="text-muted text-uppercase mb-3">Menú Principal</h6>
    <nav class="nav flex-column">
      <a class="nav-link" href="dashboard.php">
        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
      </a>

      <?php if (function_exists('has_role') ? (has_role('Técnico') || has_role('Administrador')) : true): ?>
        <a class="nav-link" href="nuevo_conocimiento.php">
          <i class="fas fa-plus-circle me-2"></i>Nuevo Conocimiento
        </a>
      <?php endif; ?>

      <a class="nav-link" href="conocimientos.php">
        <i class="fas fa-list me-2"></i>Conocimientos
      </a>

      <a class="nav-link" href="reportes.php">
        <i class="fas fa-chart-bar me-2"></i>Reportes
      </a>

      <?php if (function_exists('has_role') ? has_role('Administrador') : true): ?>
        <hr class="my-3">
        <h6 class="text-muted text-uppercase mb-3">Administración</h6>
        <a class="nav-link" href="usuarios.php">
          <i class="fas fa-users me-2"></i>Usuarios
        </a>
        <a class="nav-link" href="receptores.php">
          <i class="fas fa-user-friends me-2"></i>Receptores
        </a>
        <a class="nav-link" href="distritos.php">
          <i class="fas fa-map-marked-alt me-2"></i>Distritos
        </a>
        <a class="nav-link" href="puestos.php">
          <i class="fas fa-briefcase me-2"></i>Puestos
        </a>
        <a class="nav-link" href="insumos.php">
          <i class="fas fa-boxes me-2"></i>Catálogo de Insumos
        </a>
        <a class="nav-link" href="configuracion.php">
          <i class="fas fa-cog me-2"></i>Configuración
        </a>
      <?php endif; ?>
    </nav>
  </div>
</div>

