<?php
/**
 * Nuevo Conocimiento de Entrega
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
// Asegurar UTF-8 en todo el flujo
header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }
if (function_exists('mb_http_output')) { mb_http_output('UTF-8'); }
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'includes/functions.php';

// Verificar autenticaciÃ³n y permisos
require_auth();
if (!has_role('TÃ©cnico') && !has_role('Administrador')) {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$auth = new Auth();
$current_user = $auth->getCurrentUser();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener usuarios activos para entregante y receptor
    // Obtener usuarios activos para entregante
    $query = "SELECT u.id, u.nombre_completo, u.puesto, d.nombre as distrito 
                FROM usuarios u 
                INNER JOIN distritos d ON u.distrito_id = d.id 
                WHERE u.activo = 1 
                ORDER BY u.nombre_completo";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
    
    // Obtener receptores activos
    $query = "SELECT r.id, r.nombre_completo, r.puesto, d.nombre as distrito 
                FROM receptores r 
                INNER JOIN distritos d ON r.distrito_id = d.id 
                WHERE r.activo = 1 
                ORDER BY r.nombre_completo";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $receptores = $stmt->fetchAll();
    
    // Obtener insumos activos
    $query = "SELECT i.id, i.codigo, i.nombre, i.descripcion, i.unidad_medida, c.nombre as categoria
              FROM insumos i 
              LEFT JOIN categorias_insumos c ON i.categoria_id = c.id
              WHERE i.activo = 1 
              ORDER BY c.nombre, i.nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $insumos = $stmt->fetchAll();
    
    // Obtener distritos
    $query = "SELECT id, nombre FROM distritos WHERE activo = 1 ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $distritos = $stmt->fetchAll();
    
} catch (Exception $e) {
        error_log("Error cargando datos: " . $e->getMessage());
        $error_message = "Error cargando los datos del formulario";
        $usuarios = [];
        $receptores = [];
        $insumos = [];
        $distritos = [];
}

// Procesar formulario
if ($_POST && isset($_POST['guardar_conocimiento'])) {
    if (isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token'])) {
        try {
            $conn->beginTransaction();
            
            // Validar datos bÃ¡sicos
            $fecha_entrega = sanitize_input($_POST['fecha_entrega']);
            $lugar_entrega = sanitize_input($_POST['lugar_entrega']);
            $entregante_id = (int)$_POST['entregante_id'];
            $receptor_id = (int)$_POST['receptor_id'];
            $observaciones_generales = sanitize_input($_POST['observaciones_generales']);
            $estado = sanitize_input($_POST['estado']);
            
            if (empty($fecha_entrega) || empty($lugar_entrega) || !$entregante_id || !$receptor_id) {
                throw new Exception("Todos los campos obligatorios deben ser completados");
            }
            
            // Nota: Entregante (usuarios) y receptor (receptores) provienen de tablas distintas,
            // por lo que no comparamos igualdad directa de IDs entre tablas diferentes.
            
            if (!validate_date($fecha_entrega)) {
                throw new Exception("Fecha de entrega invÃ¡lida");
            }
            
            // Validar insumos
            if (!isset($_POST['insumos']) || empty($_POST['insumos'])) {
                throw new Exception("Debe agregar al menos un insumo");
            }
            
            $insumos_data = [];
            foreach ($_POST['insumos'] as $index => $insumo_id) {
                $cantidad = (float)$_POST['cantidades'][$index];
                $observaciones = sanitize_input($_POST['observaciones_insumos'][$index]);
                
                if ($cantidad <= 0) {
                    throw new Exception("La cantidad debe ser mayor a 0");
                }
                
                $insumos_data[] = [
                    'insumo_id' => (int)$insumo_id,
                    'cantidad' => $cantidad,
                    'observaciones' => $observaciones
                ];
            }
            
            // Insertar conocimiento (el trigger generarÃ¡ el nÃºmero automÃ¡ticamente)
            $query = "INSERT INTO conocimientos 
                      (fecha_entrega, lugar_entrega, entregante_id, receptor_id, 
                       observaciones_generales, estado, creado_por) 
                      VALUES (:fecha_entrega, :lugar_entrega, :entregante_id, :receptor_id, 
                              :observaciones_generales, :estado, :creado_por)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':fecha_entrega', $fecha_entrega);
            $stmt->bindParam(':lugar_entrega', $lugar_entrega);
            $stmt->bindParam(':entregante_id', $entregante_id);
            $stmt->bindParam(':receptor_id', $receptor_id);
            $stmt->bindParam(':observaciones_generales', $observaciones_generales);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':creado_por', $current_user['id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Error guardando el conocimiento");
            }
            
            $conocimiento_id = $conn->lastInsertId();
            
            // Insertar detalle de insumos
            $query = "INSERT INTO detalle_conocimientos 
                      (conocimiento_id, insumo_id, cantidad, observaciones) 
                      VALUES (:conocimiento_id, :insumo_id, :cantidad, :observaciones)";
            
            $stmt = $conn->prepare($query);
            
            foreach ($insumos_data as $item) {
                $stmt->bindValue(':conocimiento_id', $conocimiento_id, PDO::PARAM_INT);
                $stmt->bindValue(':insumo_id', $item['insumo_id'], PDO::PARAM_INT);
                $stmt->bindValue(':cantidad', $item['cantidad']);
                $stmt->bindValue(':observaciones', $item['observaciones']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error guardando los insumos");
                }
            }
            
            $conn->commit();
            
            // Obtener el nÃºmero de conocimiento generado
            $query = "SELECT numero_conocimiento FROM conocimientos WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $conocimiento_id);
            $stmt->execute();
            $row = $stmt->fetch();
            $numero_conocimiento = $row ? $row['numero_conocimiento'] : null;
            
            $success_message = "Conocimiento #{$numero_conocimiento} creado exitosamente";
            
            // Abrir PDF en nueva pesta\\u00F1a y redirigir a lista (con espera breve)
$pdf_url = 'generar_pdf.php?id=' . (int)$conocimiento_id;
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Procesando...</title>
</head>
<body>
<script>
try {
    // Intentar abrir en nueva pesta\\u00F1a
    window.open(' . json_encode($pdf_url) . ', "_blank");
} catch(e) {
    console.error("No se pudo abrir la pesta\\u00F1a: ", e);
}
// Redirigir despu&eacute;s de 1 segundo
setTimeout(function() {
    window.location.href = "conocimientos.php";
}, 1000);
</script>

<noscript>
    Documento creado. 
    Vea el <a href="' . htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8') . '" target="_blank">PDF</a>. 
    Luego regrese a <a href="conocimientos.php">Conocimientos</a>.
</noscript>
</body>
</html>';
exit();
            
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
                debug_log("TransacciÃ³n revertida debido a un error: " . $e->getMessage(), "ERROR");
            }
            error_log("Error al guardar conocimiento: " . $e->getMessage());
            error_log("POST data: " . print_r($_POST, true));
            debug_log("Error al guardar conocimiento: " . $e->getMessage(), "ERROR");
            $error_message = $e->getMessage();
        }
    } else {
        debug_log("Error de validaciÃ³n CSRF en el bloque POST.", "WARNING");
        $error_message = "Token de seguridad invÃ¡lido";
    }
}

$csrf_token = generate_csrf_token();

// Prefill de formulario tras error
$form_fecha_entrega = $_POST['fecha_entrega'] ?? date('Y-m-d');
$form_lugar_entrega = $_POST['lugar_entrega'] ?? '';
$form_entregante_id = isset($_POST['entregante_id']) ? (int)$_POST['entregante_id'] : (($current_user['id'] ?? null));
$form_receptor_id = isset($_POST['receptor_id']) ? (int)$_POST['receptor_id'] : 0;
$form_observaciones_generales = $_POST['observaciones_generales'] ?? '';
$form_estado = $_POST['estado'] ?? 'borrador';

// Prefill insumos
$prefill_insumos = [];
if (!empty($_POST['insumos']) && is_array($_POST['insumos'])) {
    foreach ($_POST['insumos'] as $idx => $insumo_sel) {
        $item = [
            'insumo_id' => (int)$insumo_sel,
            'cantidad' => isset($_POST['cantidades'][$idx]) ? (string)$_POST['cantidades'][$idx] : '',
            'observaciones' => isset($_POST['observaciones_insumos'][$idx]) ? (string)$_POST['observaciones_insumos'][$idx] : ''
        ];
        $prefill_insumos[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Conocimiento de Entrega - <?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">

    <!-- CSS Global -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
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
          <div class="container mt-2">
    <div class="container-fluid py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Nuevo Conocimiento</li>
            </ol>
        </nav>
        
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="main-card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            Nuevo Conocimiento de Entrega de Insumos
                        </h4>
                        <p class="mb-0 mt-2 opacity-75">
                            Complete el formulario para registrar una nueva entrega de insumos
                        </p>
                    </div>
                    
                    <div class="card-body p-4">
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
                        
                        <form method="POST" action="" id="conocimientoForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <!-- Informaci&oacute;n General -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Informaci&oacute;n General
                                    </h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_entrega" class="form-label">
                                        Fecha de Entrega <span class="required">*</span>
                                    </label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="fecha_entrega" 
                                           name="fecha_entrega" 
                                           value="<?php echo date('Y-m-d'); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="lugar_entrega" class="form-label">
                                        Lugar de Entrega <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="lugar_entrega" 
                                           name="lugar_entrega" 
                                           placeholder="Ej: CobÃ¡n A.V"
                                           value="<?php echo escape_html($form_lugar_entrega); ?>"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Personas Involucradas -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-users me-2"></i>Personas Involucradas
                                    </h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="entregante_id" class="form-label">
                                        Entregante <span class="required">*</span>
                                    </label>
                                    <select class="form-select select2" 
                                            id="entregante_id" 
                                            name="entregante_id" 
                                            required>
                                        <option value="">Seleccione el entregante</option>
                                         <?php foreach ($usuarios as $usuario): ?>
                                             <option value="<?php echo $usuario['id']; ?>" <?php echo ($form_entregante_id == $usuario['id']) ? 'selected' : ''; ?>>
                                                 <?php echo escape_html($usuario['nombre_completo']); ?> - 
                                                 <?php echo escape_html($usuario['puesto']); ?> 
                                                 (<?php echo escape_html($usuario['distrito']); ?>)
                                             </option>
                                         <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                     <label for="receptor_id" class="form-label">
                                         Receptor <span class="required">*</span>
                                     </label>
                                     <select class="form-select select2" 
                                             id="receptor_id" 
                                             name="receptor_id" 
                                             required>
                                         <option value="">Seleccione el receptor</option>
                                          <?php foreach ($receptores as $receptor): ?>
                                              <option value="<?php echo $receptor['id']; ?>" <?php echo ($form_receptor_id == $receptor['id']) ? 'selected' : ''; ?>>
                                                  <?php echo escape_html($receptor['nombre_completo']); ?> - 
                                                  <?php echo escape_html($receptor['puesto']); ?> 
                                                  (<?php echo escape_html($receptor['distrito']); ?>)
                                              </option>
                                          <?php endforeach; ?>
                                     </select>
                                 </div>
                            </div>
                            
                            <!-- Insumos -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-boxes me-2"></i>Insumos a Entregar
                                    </h5>
                                </div>
                                
                                <div class="col-12">
                                    <div id="insumos-container">
                                        <!-- Los insumos se agregarÃ¡n dinÃ¡micamente aquÃ­ -->
                                    </div>
                                    
                                    <button type="button" class="btn btn-add-insumo" id="add-insumo">
                                        <i class="fas fa-plus me-2"></i>Agregar Insumo
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Observaciones -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <label for="observaciones_generales" class="form-label">
                                        Observaciones Generales
                                    </label>
                                    <textarea class="form-control" 
                                              id="observaciones_generales" 
                                              name="observaciones_generales" 
                                              rows="3"
                                              placeholder="Observaciones adicionales sobre la entrega..."></textarea>
                                </div>
                            </div>
                            
                            <!-- Estado -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="estado" class="form-label">
                                        Estado del Conocimiento <span class="required">*</span>
                                    </label>
                                    <select class="form-select" id="estado" name="estado" required>
                                        <option value="borrador">Borrador (se puede editar despu&eacute;s)</option>
                                        <option value="finalizado">Finalizado (generar PDF)</option>
                                    </select>
                                    <div class="form-text">
                                        Si selecciona "Finalizado", se generar&aacute; autom&aacute;ticamente el PDF
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Botones -->
                            <div class="row">
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <a href="dashboard.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Cancelar
                                        </a>
                                        
                                        <button type="submit" name="guardar_conocimiento" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Guardar Conocimiento
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Template para insumo -->
    <template id="insumo-template">
        <div class="insumo-row">
            <div class="row align-items-end">
                <div class="col-md-5 mb-2">
                    <label class="form-label">Insumo <span class="required">*</span></label>
                    <select class="form-select insumo-select" name="insumos[]" required>
                        <option value="">Seleccione un insumo</option>
                        <?php foreach ($insumos as $insumo): ?>
                            <option value="<?php echo $insumo['id']; ?>" 
                                    data-unidad="<?php echo escape_html($insumo['unidad_medida']); ?>">
                                <?php echo escape_html($insumo['codigo']); ?> - 
                                <?php echo escape_html($insumo['nombre']); ?>
                                <?php if ($insumo['categoria']): ?>
                                    (<?php echo escape_html($insumo['categoria']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2 mb-2">
                    <label class="form-label">Cantidad <span class="required">*</span></label>
                    <input type="number" 
                           class="form-control" 
                           name="cantidades[]" 
                           min="0.01" 
                           step="0.01" 
                           required>
                </div>
                
                <div class="col-md-1 mb-2">
                    <label class="form-label">Unidad</label>
                    <input type="text" 
                           class="form-control unidad-display" 
                           readonly 
                           placeholder="Unidad">
                </div>
                
                <div class="col-md-3 mb-2">
                    <label class="form-label">Observaciones</label>
                    <input type="text" 
                           class="form-control" 
                           name="observaciones_insumos[]" 
                           placeholder="Observaciones del insumo">
                </div>
                
                <div class="col-md-1 mb-2">
                    <button type="button" class="btn btn-remove-insumo" title="Eliminar insumo">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Prefill selects principales y estado
            $('#entregante_id').val('<?php echo (int)$form_entregante_id; ?>');
            $('#receptor_id').val('<?php echo (int)$form_receptor_id; ?>');
            $('#estado').val('<?php echo $form_estado; ?>');
            try { $('#entregante_id').trigger('change'); $('#receptor_id').trigger('change'); } catch(e){}
            
            // Prefill insumos si existieran
            var prefillInsumos = <?php echo json_encode($prefill_insumos, JSON_UNESCAPED_UNICODE); ?>;
            // Inicializar Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                placeholder: 'Seleccione una opci&oacute;n'
            });
            
            // Agregar primer insumo automÃ¡ticamente
            addInsumo();
            
            // Agregar insumo
            $('#add-insumo').click(function() {
                addInsumo();
            });
            
            // Eliminar insumo
            $(document).on('click', '.btn-remove-insumo', function() {
                $(this).closest('.insumo-row').remove();
                updateInsumoNumbers();
            });
            
            // Actualizar unidad cuando se selecciona un insumo
            $(document).on('change', '.insumo-select', function() {
                const unidad = $(this).find('option:selected').data('unidad');
                $(this).closest('.insumo-row').find('.unidad-display').val(unidad || '');
            });
            
            // Validar que entregante y receptor sean diferentes
            // Validar que entregante y receptor no pueden ser la misma persona.
            // Removido: El entregante y el receptor ahora provienen de tablas diferentes, por lo que no pueden ser la misma persona.
            // $('#entregante_id, #receptor_id').change(function() {
            //     const entregante = $('#entregante_id').val();
            //     const receptor = $('#receptor_id').val();
            //     
            //     if (entregante && receptor && entregante === receptor) {
            //         alert('El entregante y receptor no pueden ser la misma persona');
            //         $(this).val('').trigger('change');
            //     }
            // });
            
            // Validar formulario antes de enviar
            $('#conocimientoForm').submit(function(e) {
                const insumos = $('.insumo-select').length;
                if (insumos === 0) {
                    e.preventDefault();
                    alert('Debe agregar al menos un insumo');
                    return false;
                }
                
                // Validar que todos los insumos tengan cantidad
                let valid = true;
                $('input[name="cantidades[]"]').each(function() {
                    if (!$(this).val() || parseFloat($(this).val()) <= 0) {
                        valid = false;
                        $(this).focus();
                        return false;
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Todas las cantidades deben ser mayores a 0');
                    return false;
                }
                
                // Deshabilitar botÃ³n de envÃ­o
                // $('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');
            });
        });
        
        function addInsumo() {
            const template = document.getElementById('insumo-template');
            const clone = template.content.cloneNode(true);
            document.getElementById('insumos-container').appendChild(clone);
            updateInsumoNumbers();
        }
        
        function updateInsumoNumbers() {
            $('.insumo-row').each(function(index) {
                $(this).find('label:first').html(`Insumo ${index + 1} <span class="required">*</span>`);
            });
        }
    </script>
</body>
</html>
