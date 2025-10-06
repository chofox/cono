<?php
/**
 * Editar Conocimiento de Entrega
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'includes/functions.php';

// Verificar autenticación y permisos
require_auth();
if (!has_role('Técnico') && !has_role('Administrador')) {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$conocimiento_id = null;
$conocimiento = null;
$insumos_conocimiento = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cambios_conocimiento'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Error de seguridad. Intento de CSRF detectado.";
        header('Location: conocimientos.php');
        exit();
    }

    $conocimiento_id = sanitize_input($_POST['conocimiento_id']);
    $fecha_entrega = sanitize_input($_POST['fecha_entrega']);
    $lugar_entrega = sanitize_input($_POST['lugar_entrega']);
    $entregante_id = sanitize_input($_POST['entregante_id']);
    $receptor_id = sanitize_input($_POST['receptor_id']);
    $observaciones_generales = sanitize_input($_POST['observaciones_generales']);
    $estado = sanitize_input($_POST['estado']);
     if ($estado === 'Completado') {
         $estado = 'finalizado';
     }
    $insumos = $_POST['insumos'] ?? [];
        debug_log("Insumos recibidos en POST: " . print_r($insumos, true));

        // Validaciones básicas
    if (empty($fecha_entrega) || empty($lugar_entrega) || empty($entregante_id) || empty($receptor_id) || empty($estado)) {
        $_SESSION['error_message'] = "Todos los campos obligatorios deben ser completados.";
        header('Location: editar_conocimiento.php?id=' . $conocimiento_id);
        exit();
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();

        $conn->beginTransaction();

        // Actualizar conocimiento
        $query_conocimiento = "UPDATE conocimientos SET
                                fecha_entrega = :fecha_entrega,
                                lugar_entrega = :lugar_entrega,
                                entregante_id = :entregante_id,
                                receptor_id = :receptor_id,
                                observaciones_generales = :observaciones_generales,
                                estado = :estado,
                                fecha_actualizacion = NOW()
                                WHERE id = :id";
        $stmt_conocimiento = $conn->prepare($query_conocimiento);
        $stmt_conocimiento->bindParam(':fecha_entrega', $fecha_entrega);
        $stmt_conocimiento->bindParam(':lugar_entrega', $lugar_entrega);
        $stmt_conocimiento->bindParam(':entregante_id', $entregante_id);
        $stmt_conocimiento->bindParam(':receptor_id', $receptor_id);
        $stmt_conocimiento->bindParam(':observaciones_generales', $observaciones_generales);
        $stmt_conocimiento->bindParam(':estado', $estado);
        $stmt_conocimiento->bindParam(':id', $conocimiento_id);
        $stmt_conocimiento->execute();

        // Eliminar insumos existentes
        $delete_insumos_query = "DELETE FROM detalle_conocimientos WHERE conocimiento_id = :conocimiento_id";
        $stmt_delete_insumos = $conn->prepare($delete_insumos_query);
        $stmt_delete_insumos->bindParam(':conocimiento_id', $conocimiento_id);
        $stmt_delete_insumos->execute();

        foreach ($insumos as $insumo) {
            $insumo_id = sanitize_input($insumo['id']);
            $cantidad = sanitize_input($insumo['cantidad']);
            $observaciones_insumo = sanitize_input($insumo['observaciones'] ?? '');

            if (empty($insumo_id) || !is_numeric($cantidad) || $cantidad <= 0) {
                throw new Exception("Datos de insumo inválidos.");
            }

            $query_insert_insumo = "INSERT INTO detalle_conocimientos (conocimiento_id, insumo_id, cantidad, observaciones)
                                    VALUES (:conocimiento_id, :insumo_id, :cantidad, :observaciones)";
            $stmt_insert_insumo = $conn->prepare($query_insert_insumo);
            $stmt_insert_insumo->bindParam(':conocimiento_id', $conocimiento_id);
            $stmt_insert_insumo->bindParam(':insumo_id', $insumo_id);
            $stmt_insert_insumo->bindParam(':cantidad', $cantidad);
            $stmt_insert_insumo->bindParam(':observaciones', $observaciones_insumo);
            $stmt_insert_insumo->execute();
        }

        $conn->commit();
         $_SESSION['success_message'] = "Conocimiento actualizado exitosamente.";
         header('Location: conocimientos.php');
         exit();

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error al actualizar conocimiento: " . $e->getMessage());
        $_SESSION['error_message'] = "Error al actualizar el conocimiento: " . $e->getMessage();
        header('Location: editar_conocimiento.php?id=' . $conocimiento_id);
        exit();
    }
}

if (isset($_GET['id'])) {
    $conocimiento_id = sanitize_input($_GET['id']);

    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Obtener datos del conocimiento
        $stmt = $conn->prepare("SELECT * FROM conocimientos WHERE id = :id");
        $stmt->bindParam(':id', $conocimiento_id);
        $stmt->execute();
        $conocimiento = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log("Conocimiento: " . print_r($conocimiento, true));

        if (!$conocimiento) {
            $_SESSION['error_message'] = "Conocimiento no encontrado.";
            header('Location: conocimientos.php');
            exit();
        }

        // Obtener insumos asociados al conocimiento
        $stmt_insumos = $conn->prepare("SELECT di.insumo_id, di.cantidad, di.observaciones, i.nombre as insumo_nombre, i.unidad_medida FROM detalle_conocimientos di JOIN insumos i ON di.insumo_id = i.id WHERE di.conocimiento_id = :conocimiento_id");
        $stmt_insumos->bindParam(':conocimiento_id', $conocimiento_id);
        $stmt_insumos->execute();
        $insumos_conocimiento = $stmt_insumos->fetchAll(PDO::FETCH_ASSOC);
        debug_log("Insumos Conocimiento: " . print_r($insumos_conocimiento, true));

    } catch (PDOException $e) {
        error_log("Error al cargar conocimiento para edición: " . $e->getMessage());
        $_SESSION['error_message'] = "Error al cargar el conocimiento.";
        header('Location: conocimientos.php');
        exit();
    }
}

// Cargar datos para los select (entregantes, receptores, insumos, lugares de entrega)
$entregantes = get_users_by_role('Entregante');
debug_log("Entregantes: " . print_r($entregantes, true));
$receptores = get_all_receptores();
debug_log("Receptores: " . print_r($receptores, true));
$insumos_disponibles = get_all_insumos();
debug_log("Insumos Disponibles: " . print_r($insumos_disponibles, true));
// $lugares_entrega = get_all_lugares_entrega(); // Eliminado ya que la tabla no es necesaria
// debug_log("Lugares de Entrega: " . print_r($lugares_entrega, true));

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = "Editar Conocimiento";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Conocimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include_once 'includes/navbar.php'; ?>

<div class="container-fluid mt-4">
  <div class="row">
    <div class="col-md-3 col-lg-2 px-0">
      <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9 col-lg-10">
      <div class="container mt-4">

<div class="container mt-5">
    <h1 class="mb-4">Editar Conocimiento de Entrega #<?php echo htmlspecialchars($conocimiento_id); ?></h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>

      </div>
    </div>
  </div>
</div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <form id="conocimientoForm" action="editar_conocimiento.php?id=<?php echo htmlspecialchars($conocimiento_id); ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="conocimiento_id" value="<?php echo htmlspecialchars($conocimiento_id); ?>">

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="fecha_entrega" class="form-label">Fecha de Entrega</label>
                <input type="date" class="form-control" id="fecha_entrega" name="fecha_entrega" value="<?php echo htmlspecialchars($conocimiento['fecha_entrega'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="lugar_entrega" class="form-label">Lugar de Entrega</label>
                <select class="form-select" id="lugar_entrega" name="lugar_entrega" required>
                    <option value="">Seleccione un lugar</option>
                    <option value="COBAN" <?php echo (isset($conocimiento['lugar_entrega']) && $conocimiento['lugar_entrega'] == 'COBAN') ? 'selected' : ''; ?>>COBAN</option>
                    <option value="GUATEMALA" <?php echo (isset($conocimiento['lugar_entrega']) && $conocimiento['lugar_entrega'] == 'GUATEMALA') ? 'selected' : ''; ?>>GUATEMALA</option>
                    <option value="SALAMA" <?php echo (isset($conocimiento['lugar_entrega']) && $conocimiento['lugar_entrega'] == 'SALAMA') ? 'selected' : ''; ?>>SALAMA</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="entregante_id" class="form-label">Entregante</label>
                <select class="form-select" id="entregante_id" name="entregante_id" required>
                    <option value="">Seleccione un entregante</option>
                    <?php foreach ($entregantes as $entregante): ?>
                        <option value="<?php echo htmlspecialchars($entregante['id']); ?>" <?php echo (isset($conocimiento['entregante_id']) && $conocimiento['entregante_id'] == $entregante['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($entregante['nombre_completo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="receptor_id" class="form-label">Receptor</label>
                <select class="form-select" id="receptor_id" name="receptor_id" required>
                    <option value="">Seleccione un receptor</option>
                    <?php foreach ($receptores as $receptor): ?>
                        <option value="<?php echo htmlspecialchars($receptor['id']); ?>" <?php echo (isset($conocimiento['receptor_id']) && $conocimiento['receptor_id'] == $receptor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($receptor['nombre_completo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label for="observaciones_generales" class="form-label">Observaciones Generales</label>
            <textarea class="form-control" id="observaciones_generales" name="observaciones_generales" rows="3"><?php echo htmlspecialchars($conocimiento['observaciones_generales'] ?? ''); ?></textarea>
        </div>

        <div class="mb-3">
            <label for="estado" class="form-label">Estado</label>
            <select class="form-select" id="estado" name="estado" required>
                <option value="Pendiente" <?php echo (isset($conocimiento['estado']) && $conocimiento['estado'] == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                <option value="finalizado" <?php echo (isset($conocimiento['estado']) && $conocimiento['estado'] == 'finalizado') ? 'selected' : ''; ?>>Completado</option>
                <option value="Cancelado" <?php echo (isset($conocimiento['estado']) && $conocimiento['estado'] == 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
            </select>
        </div>

        <hr>
        <h2 class="mb-3">Insumos</h2>
        <div id="insumos-container">
            <?php if (!empty($insumos_conocimiento)): ?>
                <?php foreach ($insumos_conocimiento as $index => $detalle): ?>
                    <div class="row insumo-item mb-3" data-index="<?php echo $index; ?>">
                        <div class="col-md-5">
                            <label for="insumo_id_<?php echo $index; ?>" class="form-label">Insumo</label>
                            <select class="form-select insumo-select" id="insumo_id_<?php echo $index; ?>" name="insumos[<?php echo $index; ?>][id]" required>
                                <option value="">Seleccione un insumo</option>
                                <?php foreach ($insumos_disponibles as $insumo): ?>
                                    <option value="<?php echo htmlspecialchars($insumo['id']); ?>" data-unidad="<?php echo htmlspecialchars($insumo['unidad_medida']); ?>" <?php echo ($detalle['insumo_id'] == $insumo['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($insumo['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="cantidad_<?php echo $index; ?>" class="form-label">Cantidad</label>
                            <input type="number" class="form-control cantidad-input" id="cantidad_<?php echo $index; ?>" name="insumos[<?php echo $index; ?>][cantidad]" value="<?php echo htmlspecialchars($detalle['cantidad']); ?>" min="1" required>
                        </div>
                        <div class="col-md-2">
                            <label for="unidad_<?php echo $index; ?>" class="form-label">Unidad</label>
                            <input type="text" class="form-control unidad-input" id="unidad_<?php echo $index; ?>" value="<?php echo htmlspecialchars($detalle['unidad_medida']); ?>" readonly>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-insumo">Eliminar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-success mb-3" id="add-insumo">Agregar Insumo</button>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg" name="guardar_cambios_conocimiento">Guardar Cambios</button>
            <a href="conocimientos.php" class="btn btn-secondary btn-lg">Cancelar</a>
        </div>
    </form>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let insumoIndex = <?php echo count($insumos_conocimiento); ?>;

        function updateUnits() {
            document.querySelectorAll('.insumo-item').forEach(function(item) {
                let select = item.querySelector('.insumo-select');
                let unidadInput = item.querySelector('.unidad-input');
                let selectedOption = select.options[select.selectedIndex];
                if (selectedOption && selectedOption.dataset.unidad) {
                    unidadInput.value = selectedOption.dataset.unidad;
                } else {
                    unidadInput.value = '';
                }
            });
        }

        document.getElementById('add-insumo').addEventListener('click', function() {
            const container = document.getElementById('insumos-container');
            const newInsumoHtml = `
                <div class="row insumo-item mb-3" data-index="${insumoIndex}">
                    <div class="col-md-5">
                        <label for="insumo_id_${insumoIndex}" class="form-label">Insumo</label>
                        <select class="form-select insumo-select" id="insumo_id_${insumoIndex}" name="insumos[${insumoIndex}][id]" required>
                            <option value="">Seleccione un insumo</option>
                            <?php foreach ($insumos_disponibles as $insumo): ?>
                                <option value="<?php echo htmlspecialchars($insumo['id']); ?>" data-unidad="<?php echo htmlspecialchars($insumo['unidad_medida']); ?>"><?php echo htmlspecialchars($insumo['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="cantidad_${insumoIndex}" class="form-label">Cantidad</label>
                        <input type="number" class="form-control cantidad-input" id="cantidad_${insumoIndex}" name="insumos[${insumoIndex}][cantidad]" value="1" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <label for="unidad_${insumoIndex}" class="form-label">Unidad</label>
                        <input type="text" class="form-control unidad-input" id="unidad_${insumoIndex}" readonly>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger remove-insumo">Eliminar</button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', newInsumoHtml);
            insumoIndex++;
            updateUnits();
        });

        document.getElementById('insumos-container').addEventListener('change', function(e) {
            if (e.target.classList.contains('insumo-select')) {
                updateUnits();
            }
        });

        document.getElementById('insumos-container').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-insumo')) {
                e.target.closest('.insumo-item').remove();
            }
        });

        // Initial unit update for existing items
        updateUnits();

        // Form submission handler
        document.getElementById('conocimientoForm').addEventListener('submit', function(e) {
            let isValid = true;
            const insumoItems = document.querySelectorAll('.insumo-item');

            if (insumoItems.length === 0) {
                alert('Debe agregar al menos un insumo.');
                isValid = false;
            }

            insumoItems.forEach(function(item) {
                const cantidadInput = item.querySelector('.cantidad-input');
                if (parseInt(cantidadInput.value) <= 0) {
                    alert('La cantidad de cada insumo debe ser mayor que cero.');
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
            } else {
                // Re-enable submit button just before submission to ensure its name is sent
                $('button[type="submit"]').prop('disabled', false);
            }
        });
    });
</script>
</body>
</html>
