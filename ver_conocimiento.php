<?php
/**
 * Ver Conocimiento (detalle)
 * Sistema de Conocimiento de Entrega de Insumos
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
$conocimiento = null;
$insumos = [];

// Validar parámetro
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = 'ID de conocimiento inválido';
} else {
    $conocimiento_id = (int)$_GET['id'];

    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Obtener datos del conocimiento (receptor desde tabla receptores)
        $sql = "SELECT c.id,
                       c.numero_conocimiento,
                       c.estado,
                       c.fecha_entrega,
                       DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') AS fecha_formateada,
                       c.lugar_entrega,
                       c.observaciones_generales AS observaciones,
                       c.creado_por,
                       DATE_FORMAT(c.fecha_creacion, '%d/%m/%Y %H:%i') AS fecha_creacion,
                       DATE_FORMAT(c.fecha_actualizacion, '%d/%m/%Y %H:%i') AS fecha_actualizacion,
                       u_ent.id AS entregante_id,
                       u_ent.nombre_completo AS entregante_nombre,
                       COALESCE(p_ent.nombre, u_ent.puesto) AS entregante_puesto,
                       d_ent.nombre AS entregante_distrito,
                       r.id AS receptor_id,
                       r.nombre_completo AS receptor_nombre,
                       COALESCE(p_rec.nombre, r.puesto) AS receptor_puesto,
                       d_rec.nombre AS receptor_distrito,
                       r.telefono AS receptor_telefono,
                       r.email AS receptor_email,
                       creador.nombre_completo AS creado_por_nombre
                FROM conocimientos c
                INNER JOIN usuarios u_ent ON c.entregante_id = u_ent.id
                LEFT JOIN puestos p_ent ON u_ent.puesto_id = p_ent.id
                LEFT JOIN distritos d_ent ON u_ent.distrito_id = d_ent.id
                INNER JOIN receptores r ON c.receptor_id = r.id
                LEFT JOIN puestos p_rec ON r.puesto_id = p_rec.id
                LEFT JOIN distritos d_rec ON r.distrito_id = d_rec.id
                LEFT JOIN usuarios creador ON c.creado_por = creador.id
                WHERE c.id = :id";

        // Seguridad: si no es Administrador, limitar a creador o entregante
        if (($current_user['rol'] ?? '') !== 'Administrador') {
            $sql .= " AND (c.creado_por = :uid OR c.entregante_id = :uid)";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $conocimiento_id, PDO::PARAM_INT);
        if (($current_user['rol'] ?? '') !== 'Administrador') {
            $stmt->bindParam(':uid', $current_user['id'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $conocimiento = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conocimiento) {
            // Obtener detalle de insumos
            $q = "SELECT di.insumo_id, di.cantidad, di.observaciones,
                         i.codigo, i.nombre AS insumo_nombre, i.unidad_medida,
                         ccat.nombre AS categoria
                  FROM detalle_conocimientos di
                  INNER JOIN insumos i ON di.insumo_id = i.id
                  LEFT JOIN categorias_insumos ccat ON i.categoria_id = ccat.id
                  WHERE di.conocimiento_id = :id
                  ORDER BY i.nombre";
            $d = $conn->prepare($q);
            $d->bindParam(':id', $conocimiento_id, PDO::PARAM_INT);
            $d->execute();
            $insumos = $d->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error_message = 'Conocimiento no encontrado o sin permisos para verlo';
        }
    } catch (Exception $e) {
        error_log('Error cargando ver_conocimiento: ' . $e->getMessage());
        $error_message = 'Error interno cargando el conocimiento';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detalle del Conocimiento - <?php echo APP_NAME; ?></title>
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
              <li class="breadcrumb-item"><a href="conocimientos.php">Conocimientos</a></li>
              <li class="breadcrumb-item active" aria-current="page">Detalle</li>
            </ol>
          </nav>

          <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <?php echo escape_html($error_message); ?>
            </div>
          <?php else: ?>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">
              <i class="fas fa-file-alt me-2"></i>
              Conocimiento #<?php echo escape_html($conocimiento['numero_conocimiento']); ?>
            </h3>
            <div class="btn-group">
              <a class="btn btn-outline-secondary" href="conocimientos.php">
                <i class="fas fa-arrow-left me-1"></i>Volver
              </a>
              <a class="btn btn-primary" target="_blank" href="generar_pdf.php?id=<?php echo (int)$conocimiento['id']; ?>">
                <i class="fas fa-file-pdf me-1"></i>PDF
              </a>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-4 col-md-6 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <strong><i class="fas fa-info-circle me-2"></i>Informacion General</strong>
                </div>
                <div class="card-body">
                  <table class="table table-sm table-borderless mb-0">
                    <tr>
                      <td class="fw-bold">Numero</td>
                      <td><span class="badge bg-primary fs-6"><?php echo escape_html($conocimiento['numero_conocimiento']); ?></span></td>
                    </tr>
                    <tr>
                      <td class="fw-bold">Fecha de entrega</td>
                      <td><?php echo escape_html($conocimiento['fecha_formateada']); ?></td>
                    </tr>
                    <tr>
                      <td class="fw-bold">Lugar</td>
                      <td><?php echo escape_html($conocimiento['lugar_entrega']); ?></td>
                    </tr>
                    <tr>
                      <td class="fw-bold">Estado</td>
                      <td><?php echo escape_html(ucfirst($conocimiento['estado'])); ?></td>
                    </tr>
                    <tr>
                      <td class="fw-bold">Fecha de creacion</td>
                      <td><?php echo escape_html($conocimiento['fecha_creacion']); ?></td>
                    </tr>
                    <?php if (!empty($conocimiento['fecha_actualizacion'])): ?>
                    <tr>
                      <td class="fw-bold">Ultima actualizacion</td>
                      <td><?php echo escape_html($conocimiento['fecha_actualizacion']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($conocimiento['creado_por_nombre'])): ?>
                    <tr>
                      <td class="fw-bold">Creado por</td>
                      <td><?php echo escape_html($conocimiento['creado_por_nombre']); ?></td>
                    </tr>
                    <?php endif; ?>
                  </table>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <strong><i class="fas fa-user-check me-2"></i>Datos del Entregante</strong>
                </div>
                <div class="card-body">
                  <table class="table table-sm table-borderless mb-0">
                    <tr>
                      <td class="fw-bold">Nombre</td>
                      <td><?php echo escape_html($conocimiento['entregante_nombre']); ?></td>
                    </tr>
                    <?php if (!empty($conocimiento['entregante_puesto'])): ?>
                    <tr>
                      <td class="fw-bold">Puesto</td>
                      <td><?php echo escape_html($conocimiento['entregante_puesto']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($conocimiento['entregante_distrito'])): ?>
                    <tr>
                      <td class="fw-bold">Distrito</td>
                      <td><?php echo escape_html($conocimiento['entregante_distrito']); ?></td>
                    </tr>
                    <?php endif; ?>
                  </table>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <strong><i class="fas fa-user-plus me-2"></i>Datos del Receptor</strong>
                </div>
                <div class="card-body">
                  <table class="table table-sm table-borderless mb-0">
                    <tr>
                      <td class="fw-bold">Nombre</td>
                      <td><?php echo escape_html($conocimiento['receptor_nombre']); ?></td>
                    </tr>
                    <?php if (!empty($conocimiento['receptor_puesto'])): ?>
                    <tr>
                      <td class="fw-bold">Puesto</td>
                      <td><?php echo escape_html($conocimiento['receptor_puesto']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($conocimiento['receptor_distrito'])): ?>
                    <tr>
                      <td class="fw-bold">Distrito</td>
                      <td><?php echo escape_html($conocimiento['receptor_distrito']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($conocimiento['receptor_telefono'])): ?>
                    <tr>
                      <td class="fw-bold">Telefono</td>
                      <td><?php echo escape_html($conocimiento['receptor_telefono']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($conocimiento['receptor_email'])): ?>
                    <tr>
                      <td class="fw-bold">Email</td>
                      <td><?php echo escape_html($conocimiento['receptor_email']); ?></td>
                    </tr>
                    <?php endif; ?>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-header">
              <strong><i class="fas fa-boxes me-2"></i>Detalle de Insumos (<?php echo count($insumos); ?>)</strong>
            </div>
            <div class="card-body">
              <?php if (empty($insumos)): ?>
                <div class="text-muted">No hay insumos registrados.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm table-hover">
                    <thead class="table-light">
                      <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Insumo</th>
                        <th>Cantidad</th>
                        <th>Unidad</th>
                        <th>Observaciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($insumos as $index => $i): ?>
                        <tr>
                          <td><?php echo $index + 1; ?></td>
                          <td><code><?php echo escape_html($i['codigo']); ?></code></td>
                          <td>
                            <strong><?php echo escape_html($i['insumo_nombre']); ?></strong>
                            <?php if (!empty($i['categoria'])): ?>
                              <br><small class="text-muted"><?php echo escape_html($i['categoria']); ?></small>
                            <?php endif; ?>
                          </td>
                          <td><?php echo escape_html($i['cantidad']); ?></td>
                          <td><?php echo escape_html($i['unidad_medida']); ?></td>
                          <td><?php echo escape_html($i['observaciones']); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($conocimiento['observaciones'])): ?>
          <div class="card">
            <div class="card-header">
              <strong><i class="fas fa-comment-dots me-2"></i>Observaciones</strong>
            </div>
            <div class="card-body">
              <p class="mb-0"><?php echo nl2br(escape_html($conocimiento['observaciones'])); ?></p>
            </div>
          </div>
          <?php endif; ?>

          <?php endif; // no error ?>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






