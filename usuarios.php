<?php
/**
 * Gestión de Usuarios
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

// Inicializar conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

$auth = new Auth();

// Verificar autenticación y permisos de administrador
if (!$auth->verifySession()) {
    header('Location: login.php');
    exit();
}

$current_user = $auth->getCurrentUser();

// Asegurarse de que $current_user no sea null antes de intentar acceder a sus propiedades
if (!$current_user) {
    header('Location: login.php');
    exit();
}

// Verificar si el usuario actual tiene el rol de Administrador
if (!$auth->hasPermission('Administrador')) {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener distritos para el formulario
    $query = "SELECT id, nombre FROM distritos WHERE activo = 1 ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $distritos = $stmt->fetchAll();
    
    // Obtener roles
    $query = "SELECT id, nombre FROM roles ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $roles = $stmt->fetchAll();
    
    // Obtener puestos para el formulario
    $query_puestos = "SELECT id, nombre FROM puestos WHERE activo = 1 ORDER BY nombre";
    $stmt_puestos = $conn->prepare($query_puestos);
    $stmt_puestos->execute();
    $puestos = $stmt_puestos->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error cargando datos: " . $e->getMessage());
    $error_message = "Error cargando los datos";
    $distritos = [];
    $roles = [];
    $puestos = [];
}

// Procesar acciones
if ($_POST) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        try {
            $conn->beginTransaction();
            $accion_usuario = isset($_POST['accion']) ? trim($_POST['accion']) : '';
            
            if ($accion_usuario === 'crear' || isset($_POST['crear_usuario'])) {
                // Crear nuevo usuario (alineado al esquema actual)
                $nombre_completo = sanitize_input($_POST['nombre_completo']);
                $usuario = sanitize_input($_POST['usuario']);
                $puesto_id = (int)$_POST['puesto_id'];
                $distrito_id = (int)$_POST['distrito_id'];
                $rol_id = (int)$_POST['rol_id'];
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Validaciones
                if (empty($nombre_completo) || empty($usuario) || !$puesto_id || !$distrito_id || !$rol_id || empty($password)) {
                    throw new Exception("Todos los campos obligatorios deben ser completados");
                }
                
                if ($password !== $confirm_password) {
                    throw new Exception("Las contraseñas no coinciden");
                }
                
                if (strlen($password) < 6) {
                    throw new Exception("La contraseña debe tener al menos 6 caracteres");
                }
                
                // Verificar que el usuario no exista
                $query = "SELECT id FROM usuarios WHERE usuario = :usuario";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':usuario', $usuario);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    throw new Exception("Ya existe un usuario con ese nombre de usuario");
                }
                
                // Insertar usuario
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Obtener nombre de puesto (texto) a partir del id seleccionado
                $qp = $conn->prepare("SELECT nombre FROM puestos WHERE id = :id");
                $qp->bindParam(':id', $puesto_id, PDO::PARAM_INT);
                $qp->execute();
                $rowP = $qp->fetch(PDO::FETCH_ASSOC);
                if (!$rowP) { throw new Exception("Puesto seleccionado inválido"); }
                $puesto_nombre = $rowP['nombre'];
                
                $query = "INSERT INTO usuarios 
                          (nombre_completo, usuario, password_hash, puesto, puesto_id, distrito_id, rol_id, activo) 
                          VALUES (:nombre_completo, :usuario, :password_hash, :puesto, :puesto_id, :distrito_id, :rol_id, 1)";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':nombre_completo', $nombre_completo);
                $stmt->bindParam(':usuario', $usuario);
                $stmt->bindParam(':password_hash', $password_hash);
                $stmt->bindParam(':puesto', $puesto_nombre);
                $stmt->bindParam(':puesto_id', $puesto_id, PDO::PARAM_INT);
                $stmt->bindParam(':distrito_id', $distrito_id, PDO::PARAM_INT);
                $stmt->bindParam(':rol_id', $rol_id, PDO::PARAM_INT);

                
                if (!$stmt->execute()) {
                    throw new Exception("Error creando el usuario");
                }
                
                // Obtener ID del nuevo usuario para guardar la firma si se envió
                $new_user_id = (int)$conn->lastInsertId();
                
                // Manejar carga de firma (opcional)
                if (isset($_FILES['firma']) && is_uploaded_file($_FILES['firma']['tmp_name'])) {
                    try {
                        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
                        $mime = mime_content_type($_FILES['firma']['tmp_name']);
                        $size = (int)$_FILES['firma']['size'];
                        if (!isset($allowed[$mime])) { throw new Exception('Formato de firma no permitido'); }
                        if ($size > 2 * 1024 * 1024) { throw new Exception('La firma supera los 2 MB'); }
                        $destDir = __DIR__ . '/assets/firmas';
                        if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                        // Siempre almacenar en PNG opaco (sin alfa) para preferencia PNG y evitar problemas en TCPDF
                        $dest = $destDir . "/user_{$new_user_id}.png";
                        foreach (['png','jpg','jpeg','webp'] as $e) { $p = $destDir . "/user_{$new_user_id}.{$e}"; if (is_file($p)) @unlink($p); }
                        
                        $tmp = $_FILES['firma']['tmp_name'];
                        if (function_exists('imagecreatefromstring')) {
                            $raw = @file_get_contents($tmp);
                            if ($raw === false) { throw new Exception('No se pudo leer la imagen de firma'); }
                            $src = @imagecreatefromstring($raw);
                            if (!$src) { throw new Exception('No se pudo procesar la imagen de firma'); }
                            $srcW = imagesx($src); $srcH = imagesy($src);
                            $maxW = 800; $maxH = 300; // dimensiones recomendadas
                            $scale = min($maxW / max(1,$srcW), $maxH / max(1,$srcH));
                            $tW = max(1, (int)floor($srcW * $scale));
                            $tH = max(1, (int)floor($srcH * $scale));
                            $dst = imagecreatetruecolor($maxW, $maxH);
                            $white = imagecolorallocate($dst, 255, 255, 255);
                            imagefilledrectangle($dst, 0, 0, $maxW, $maxH, $white);
                            $dx = (int)floor(($maxW - $tW)/2); $dy = (int)floor(($maxH - $tH)/2);
                            imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $tW, $tH, $srcW, $srcH);
                            // Guardar como PNG opaco con compresión razonable
                            if (!imagepng($dst, $dest, 6)) { throw new Exception('No se pudo guardar la firma procesada'); }
                            imagedestroy($src); imagedestroy($dst);
                            // Actualizar ruta de firma en BD
                            $firma_rel = 'assets/firmas/' . basename($dest);
                            $qf = $conn->prepare("UPDATE usuarios SET firma_path = :fp WHERE id = :id");
                            $qf->bindParam(':fp', $firma_rel);
                            $qf->bindParam(':id', $new_user_id, PDO::PARAM_INT);
                            $qf->execute();
                        } else {
                            // Sin GD: aceptar PNG solo si NO tiene canal alfa; de lo contrario, solicitar JPG o PNG sin transparencia
                            $rawh = @fopen($tmp, 'rb');
                            if ($rawh) {
                                $hdr = @fread($rawh, 32);
                                @fclose($rawh);
                            } else { $hdr = ''; }
                            $hasAlpha = false;
                            if (strlen($hdr) >= 26 && substr($hdr,0,8) === "\x89PNG\x0D\x0A\x1A\x0A") {
                                $colorType = ord($hdr[25]);
                                if ($colorType === 4 || $colorType === 6) { $hasAlpha = true; }
                            }
                            if ($mime === 'image/png' && !$hasAlpha) {
                                if (!move_uploaded_file($tmp, $dest)) { throw new Exception('No se pudo guardar la firma'); }
                                $firma_rel = 'assets/firmas/' . basename($dest);
                                $qf = $conn->prepare("UPDATE usuarios SET firma_path = :fp WHERE id = :id");
                                $qf->bindParam(':fp', $firma_rel);
                                $qf->bindParam(':id', $new_user_id, PDO::PARAM_INT);
                                $qf->execute();
                            } else {
                                throw new Exception('Servidor sin GD/Imagick. Suba la firma en PNG sin transparencia o en JPG.');
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Carga de firma fallida (crear usuario): ' . $e->getMessage());
                    }
                }
                
                $conn->commit();
                $success_message = "Usuario creado exitosamente";
                
                // Log de actividad
                log_user_activity($current_user['id'], 'user_created', "Usuario creado: {$nombre_completo} (usuario: {$usuario})");
                
            } elseif ($accion_usuario === 'editar' || isset($_POST['editar_usuario'])) {
                // Editar usuario existente
                $user_id = (int)$_POST['user_id'];
                $nombre_completo = sanitize_input($_POST['nombre_completo']);
                $usuario = sanitize_input($_POST['usuario']);
                $puesto_id = (int)$_POST['puesto_id'];
                $distrito_id = (int)$_POST['distrito_id'];
                $rol_id = (int)$_POST['rol_id'];
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                // Validaciones
                if (empty($nombre_completo) || empty($usuario) || !$puesto_id || !$distrito_id || !$rol_id) {
                    throw new Exception("Todos los campos obligatorios deben ser completados");
                }
                
                // Verificar que el usuario no exista en otro registro
                $query = "SELECT id FROM usuarios WHERE usuario = :usuario AND id != :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':usuario', $usuario);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    throw new Exception("Ya existe otro usuario con ese nombre de usuario");
                }
                
                // No permitir desactivar el propio usuario
                if ($user_id == $current_user['id'] && !$activo) {
                    throw new Exception("No puede desactivar su propio usuario");
                }
                
                // Actualizar usuario
                $query = "UPDATE usuarios SET 
                          nombre_completo = :nombre_completo,
                          usuario = :usuario,
                          puesto = :puesto,
                          puesto_id = :puesto_id,
                          distrito_id = :distrito_id,
                          rol_id = :rol_id,
                          activo = :activo
                          WHERE id = :user_id";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':nombre_completo', $nombre_completo);
                $stmt->bindParam(':usuario', $usuario);
                
                // Obtener nombre de puesto (texto)
                $qp = $conn->prepare("SELECT nombre FROM puestos WHERE id = :id");
                $qp->bindParam(':id', $puesto_id, PDO::PARAM_INT);
                $qp->execute();
                $rowP = $qp->fetch(PDO::FETCH_ASSOC);
                if (!$rowP) { throw new Exception("Puesto seleccionado inválido"); }
                $puesto_nombre = $rowP['nombre'];
                $stmt->bindParam(':puesto', $puesto_nombre);
                $stmt->bindParam(':puesto_id', $puesto_id, PDO::PARAM_INT);
                $stmt->bindParam(':distrito_id', $distrito_id);
                $stmt->bindParam(':rol_id', $rol_id);
                $stmt->bindParam(':activo', $activo);
                $stmt->bindParam(':user_id', $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error actualizando el usuario");
                }
                
                // Manejar carga de firma (opcional) en edición
                if (isset($_FILES['firma']) && is_uploaded_file($_FILES['firma']['tmp_name'])) {
                    try {
                        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
                        $mime = mime_content_type($_FILES['firma']['tmp_name']);
                        $size = (int)$_FILES['firma']['size'];
                        if (!isset($allowed[$mime])) { throw new Exception('Formato de firma no permitido'); }
                        if ($size > 2 * 1024 * 1024) { throw new Exception('La firma supera los 2 MB'); }
                        $destDir = __DIR__ . '/assets/firmas';
                        if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                        $dest = $destDir . "/user_{$user_id}.png";
                        foreach (['png','jpg','jpeg','webp'] as $e) { $p = $destDir . "/user_{$user_id}.{$e}"; if (is_file($p)) @unlink($p); }
                        $tmp = $_FILES['firma']['tmp_name'];
                        if (function_exists('imagecreatefromstring')) {
                            $raw = @file_get_contents($tmp);
                            if ($raw === false) { throw new Exception('No se pudo leer la imagen de firma'); }
                            $src = @imagecreatefromstring($raw);
                            if (!$src) { throw new Exception('No se pudo procesar la imagen de firma'); }
                            $srcW = imagesx($src); $srcH = imagesy($src);
                            $maxW = 800; $maxH = 300;
                            $scale = min($maxW / max(1,$srcW), $maxH / max(1,$srcH));
                            $tW = max(1, (int)floor($srcW * $scale));
                            $tH = max(1, (int)floor($srcH * $scale));
                            $dst = imagecreatetruecolor($maxW, $maxH);
                            $white = imagecolorallocate($dst, 255, 255, 255);
                            imagefilledrectangle($dst, 0, 0, $maxW, $maxH, $white);
                            $dx = (int)floor(($maxW - $tW)/2); $dy = (int)floor(($maxH - $tH)/2);
                            imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $tW, $tH, $srcW, $srcH);
                            if (!imagepng($dst, $dest, 6)) { throw new Exception('No se pudo guardar la firma procesada'); }
                            imagedestroy($src); imagedestroy($dst);
                            $firma_rel = 'assets/firmas/' . basename($dest);
                            $qf = $conn->prepare("UPDATE usuarios SET firma_path = :fp WHERE id = :id");
                            $qf->bindParam(':fp', $firma_rel);
                            $qf->bindParam(':id', $user_id, PDO::PARAM_INT);
                            $qf->execute();
                        } else {
                            $rawh = @fopen($tmp, 'rb');
                            if ($rawh) { $hdr = @fread($rawh, 32); @fclose($rawh);} else { $hdr = ''; }
                            $hasAlpha = false;
                            if (strlen($hdr) >= 26 && substr($hdr,0,8) === "\x89PNG\x0D\x0A\x1A\x0A") {
                                $colorType = ord($hdr[25]);
                                if ($colorType === 4 || $colorType === 6) { $hasAlpha = true; }
                            }
                            if ($mime === 'image/png' && !$hasAlpha) {
                                if (!move_uploaded_file($tmp, $dest)) { throw new Exception('No se pudo guardar la firma'); }
                                $firma_rel = 'assets/firmas/' . basename($dest);
                                $qf = $conn->prepare("UPDATE usuarios SET firma_path = :fp WHERE id = :id");
                                $qf->bindParam(':fp', $firma_rel);
                                $qf->bindParam(':id', $user_id, PDO::PARAM_INT);
                                $qf->execute();
                            } else {
                                throw new Exception('Servidor sin GD/Imagick. Suba la firma en PNG sin transparencia o en JPG.');
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Carga de firma fallida (editar usuario): ' . $e->getMessage());
                    }
                }
                
                $conn->commit();
                $success_message = "Usuario actualizado exitosamente";
                
                // Log de actividad
                log_user_activity($current_user['id'], 'user_updated', "Usuario actualizado: {$nombre_completo} (ID: {$user_id})");
                
            } elseif (isset($_POST['cambiar_password'])) {
                // Cambiar contraseña
                $user_id = (int)$_POST['user_id'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($new_password) || empty($confirm_password)) {
                    throw new Exception("Debe completar ambos campos de contraseña");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("Las contraseñas no coinciden");
                }
                
                if (strlen($new_password) < 6) {
                    throw new Exception("La contraseña debe tener al menos 6 caracteres");
                }
                
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $query = "UPDATE usuarios SET password_hash = :password_hash WHERE id = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':password_hash', $password_hash);
                $stmt->bindParam(':user_id', $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error cambiando la contraseña");
                }
                
                $conn->commit();
                $success_message = "Contraseña cambiada exitosamente";
                
                // Log de actividad
                log_user_activity($current_user['id'], 'password_changed', "Contraseña cambiada para usuario ID: {$user_id}");
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = "Token de seguridad inválido";
    }
}

// Obtener lista de usuarios con paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_distrito = isset($_GET['distrito']) ? (int)$_GET['distrito'] : 0;
$filter_rol = isset($_GET['rol']) ? (int)$_GET['rol'] : 0;
$filter_activo = isset($_GET['activo']) ? (int)$_GET['activo'] : -1;

// Construir query con filtros
$where_conditions = [];
$params = [];

if (!empty($search)) {
    // Evitar depender de columnas opcionales como u.email
    $where_conditions[] = "(u.nombre_completo LIKE :search OR COALESCE(p.nombre, u.puesto) LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($filter_distrito > 0) {
    $where_conditions[] = "u.distrito_id = :distrito_id";
    $params[':distrito_id'] = $filter_distrito;
}

if ($filter_rol > 0) {
    $where_conditions[] = "u.rol_id = :rol_id";
    $params[':rol_id'] = $filter_rol;
}

if ($filter_activo >= 0) {
    $where_conditions[] = "u.activo = :activo";
    $params[':activo'] = $filter_activo;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Contar total de usuarios
    $count_query = "SELECT COUNT(*) as total 
                    FROM usuarios u 
                    INNER JOIN distritos d ON u.distrito_id = d.id 
                    INNER JOIN roles r ON u.rol_id = r.id 
                    LEFT JOIN puestos p ON u.puesto_id = p.id 
                    {$where_clause}";
    
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_users = $stmt->fetch()['total'];
    $total_pages = ceil($total_users / $per_page);
    
    // Obtener usuarios
    $query = "SELECT u.*, d.nombre as distrito, r.nombre as rol, COALESCE(p.nombre, u.puesto) as puesto_nombre
              FROM usuarios u 
              INNER JOIN distritos d ON u.distrito_id = d.id 
              INNER JOIN roles r ON u.rol_id = r.id 
              LEFT JOIN puestos p ON u.puesto_id = p.id 
              {$where_clause}
              ORDER BY u.nombre_completo 
              LIMIT :offset, :per_page";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error cargando usuarios: " . $e->getMessage());
    $error_message = "Error cargando la lista de usuarios";
    $usuarios = [];
    $total_users = 0;
    $total_pages = 0;
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">

    <!-- CSS Global -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Preferimos estilos globales desde assets/css/style.css -->
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
          <li class="breadcrumb-item active" aria-current="page">Gestión de Usuarios</li>
        </ol>
      </nav>

      <div class="card main-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-users me-2"></i>Gestión de Usuarios</h5>
          <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#usuarioModal" id="nuevoUsuarioBtn">
            <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
          </button>
        </div>
        <div class="card-body">
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

          <div class="filters-card">
            <form method="GET" action="">
              <div class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label for="search" class="form-label">Buscar</label>
                  <input type="text" class="form-control" id="search" name="search" value="<?php echo escape_html($search); ?>" placeholder="Nombre, email o puesto">
                </div>
                <div class="col-md-3">
                  <label for="filtro_distrito" class="form-label">Distrito</label>
                  <select class="form-select" id="filtro_distrito" name="distrito">
                    <option value="0">Todos</option>
                    <?php foreach ($distritos as $d): ?>
                      <option value="<?php echo $d['id']; ?>" <?php echo ($filter_distrito == $d['id']) ? 'selected' : ''; ?>><?php echo escape_html($d['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="filtro_rol" class="form-label">Rol</label>
                  <select class="form-select" id="filtro_rol" name="rol">
                    <option value="0">Todos</option>
                    <?php foreach ($roles as $r): ?>
                      <option value="<?php echo $r['id']; ?>" <?php echo ($filter_rol == $r['id']) ? 'selected' : ''; ?>><?php echo escape_html($r['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label for="filtro_activo" class="form-label">Estado</label>
                  <select class="form-select" id="filtro_activo" name="activo">
                    <option value="-1" <?php echo ($filter_activo == -1) ? 'selected' : ''; ?>>Todos</option>
                    <option value="1" <?php echo ($filter_activo == 1) ? 'selected' : ''; ?>>Activos</option>
                    <option value="0" <?php echo ($filter_activo == 0) ? 'selected' : ''; ?>>Inactivos</option>
                  </select>
                </div>
                <div class="col-md-12 d-flex gap-2">
                  <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Filtrar</button>
                  <a href="usuarios.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Limpiar</a>
                </div>
              </div>
            </form>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Email</th>
                  <th>Teléfono</th>
                  <th>Puesto</th>
                  <th>Distrito</th>
                  <th>Rol</th>
                  <th>Estado</th>
                  <th style="width: 210px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($usuarios)): ?>
                  <?php foreach ($usuarios as $u): ?>
                    <tr>
                      <td><?php echo escape_html($u['nombre_completo'] ?? ''); ?></td>
                      <td><?php echo escape_html($u['usuario'] ?? ''); ?></td>
                      <td></td>
                      <td><?php echo escape_html($u['puesto_nombre'] ?? ''); ?></td>
                      <td><?php echo escape_html($u['distrito'] ?? ''); ?></td>
                      <td><?php echo escape_html($u['rol'] ?? ''); ?></td>
                      <td>
                        <?php if (!empty($u['activo'])): ?>
                          <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                          <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="btn-group" role="group">
                          <button class="btn btn-sm btn-warning me-1 btn-editar-usuario"
                                  data-bs-toggle="modal"
                                  data-bs-target="#usuarioModal"
                                  data-id="<?php echo $u['id']; ?>"
                                  data-nombre="<?php echo escape_html($u['nombre_completo'] ?? ''); ?>"
                                  data-usuario="<?php echo escape_html($u['usuario'] ?? ''); ?>"
                                  data-puesto_id="<?php echo $u['puesto_id'] ?? ''; ?>"
                                  data-distrito_id="<?php echo $u['distrito_id'] ?? ''; ?>"
                                  data-rol_id="<?php echo $u['rol_id'] ?? ''; ?>"
                                  data-activo="<?php echo $u['activo'] ?? 0; ?>">
                            <i class="fas fa-edit"></i> Editar
                          </button>
                          <button class="btn btn-sm btn-secondary btn-password"
                                  data-bs-toggle="modal"
                                  data-bs-target="#passwordModal"
                                  data-id="<?php echo $u['id']; ?>"
                                  data-nombre="<?php echo escape_html($u['nombre_completo'] ?? ''); ?>">
                            <i class="fas fa-key"></i> Contraseña
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center text-muted">No se encontraron usuarios.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <?php if (!empty($total_pages) && $total_pages > 1): ?>
            <nav>
              <ul class="pagination justify-content-end">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                  <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&distrito=<?php echo $filter_distrito; ?>&rol=<?php echo $filter_rol; ?>&activo=<?php echo $filter_activo; ?>"><?php echo $p; ?></a>
                  </li>
                <?php endfor; ?>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Crear/Editar Usuario -->
    <div class="modal fade" id="usuarioModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="usuarioModalLabel">Nuevo Usuario</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="" id="usuarioForm" enctype="multipart/form-data">
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="user_id" id="user_id">
              <input type="hidden" name="accion" id="accion_usuario" value="crear">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="nombre_completo" id="nombre_completo" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Usuario <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="usuario" id="usuario" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Firma (opcional)</label>
                  <input type="file" class="form-control" name="firma" id="firma" accept="image/png, image/jpeg, image/jpg, image/webp">
                  <small class="text-muted">Formatos: PNG/JPG/WEBP. Máx. 2 MB.</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Puesto <span class="text-danger">*</span></label>
                  <select class="form-select" name="puesto_id" id="puesto_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($puestos as $p): ?>
                      <option value="<?php echo $p['id']; ?>"><?php echo escape_html($p['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Distrito <span class="text-danger">*</span></label>
                  <select class="form-select" name="distrito_id" id="distrito_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($distritos as $d): ?>
                      <option value="<?php echo $d['id']; ?>"><?php echo escape_html($d['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Rol <span class="text-danger">*</span></label>
                  <select class="form-select" name="rol_id" id="rol_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($roles as $r): ?>
                      <option value="<?php echo $r['id']; ?>"><?php echo escape_html($r['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-6" id="password_group">
                  <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" name="password" id="password">
                </div>
                <div class="col-md-6" id="confirm_password_group">
                  <label class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                </div>

                <div class="col-12" id="activo_group" style="display: none;">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1">
                    <label class="form-check-label" for="activo">Usuario activo</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="crear_usuario" id="submit_crear" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Guardar
              </button>
              <button type="submit" name="editar_usuario" id="submit_editar" class="btn btn-primary" style="display: none;">
                <i class="fas fa-save me-2"></i>Guardar Cambios
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Cambiar Contraseña -->
    <div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-key me-2"></i>Cambiar Contraseña</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="" id="passwordForm">
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="user_id" id="pwd_user_id">

              <div class="mb-3">
                <label class="form-label">Nueva Contraseña</label>
                <input type="password" class="form-control" name="new_password" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Confirmar Contraseña</label>
                <input type="password" class="form-control" name="confirm_password" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="cambiar_password" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Cambiar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Cierre de modales al enviar y prevención de doble envío
      (function(){
        const userModalEl = document.getElementById('usuarioModal');
        const pwdModalEl = document.getElementById('passwordModal');
        const userForm = document.getElementById('usuarioForm');
        const pwdForm = document.getElementById('passwordForm');
        const submitCrear = document.getElementById('submit_crear');
        const submitEditar = document.getElementById('submit_editar');

        function closeModal(el){
          try{ const inst = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el); inst.hide(); }catch(e){}
        }
        function disableBtn(btn){ if(btn){ btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...'; } }

        if(userForm){
          userForm.addEventListener('submit', function(){
            // deshabilitar ambos por si acaso
            disableBtn(submitCrear); disableBtn(submitEditar);
            closeModal(userModalEl);
          });
        }
        if(pwdForm){
          pwdForm.addEventListener('submit', function(){
            closeModal(pwdModalEl);
          });
        }
      })();
      // Abrir modal en modo crear
      document.getElementById('nuevoUsuarioBtn').addEventListener('click', function() {
        document.getElementById('usuarioModalLabel').textContent = 'Nuevo Usuario';
        document.getElementById('user_id').value = '';
        document.getElementById('nombre_completo').value = '';
        document.getElementById('usuario').value = '';
        document.getElementById('puesto_id').value = '';
        document.getElementById('distrito_id').value = '';
        document.getElementById('rol_id').value = '';
        document.getElementById('activo').checked = true;
        document.getElementById('activo_group').style.display = 'none';
        document.getElementById('password_group').style.display = '';
        document.getElementById('confirm_password_group').style.display = '';
        document.getElementById('submit_crear').style.display = '';
        document.getElementById('submit_editar').style.display = 'none';
        // Reset estado de botones por si quedaron deshabilitados
        const sc = document.getElementById('submit_crear');
        const se = document.getElementById('submit_editar');
        if (sc){ sc.disabled = false; sc.innerHTML = '<i class="fas fa-save me-2"></i>Guardar'; }
        if (se){ se.disabled = false; se.innerHTML = '<i class="fas fa-save me-2"></i>Guardar Cambios'; }
        const accion = document.getElementById('accion_usuario');
        if (accion) accion.value = 'crear';
      });

      // Abrir modal en modo editar y rellenar datos
      document.querySelectorAll('.btn-editar-usuario').forEach(function(btn) {
        btn.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const nombre = this.getAttribute('data-nombre');
          const usuario = this.getAttribute('data-usuario');
          const puestoId = this.getAttribute('data-puesto_id');
          const distritoId = this.getAttribute('data-distrito_id');
          const rolId = this.getAttribute('data-rol_id');
          const activo = this.getAttribute('data-activo') == '1';

          document.getElementById('usuarioModalLabel').textContent = 'Editar Usuario';
          document.getElementById('user_id').value = id;
          document.getElementById('nombre_completo').value = nombre || '';
          document.getElementById('usuario').value = usuario || '';
          document.getElementById('puesto_id').value = puestoId || '';
          document.getElementById('distrito_id').value = distritoId || '';
          document.getElementById('rol_id').value = rolId || '';
          document.getElementById('activo').checked = activo;
          document.getElementById('activo_group').style.display = '';
          // En edición no se solicitan contraseñas
          document.getElementById('password_group').style.display = 'none';
          document.getElementById('confirm_password_group').style.display = 'none';
          document.getElementById('submit_crear').style.display = 'none';
          document.getElementById('submit_editar').style.display = '';
          // Asegurar que botones estén habilitados al abrir
          document.getElementById('submit_crear').disabled = false;
          document.getElementById('submit_editar').disabled = false;
          const accion = document.getElementById('accion_usuario');
          if (accion) accion.value = 'editar';
        });
      });

      // Modal cambiar contraseña: setear user_id
      document.querySelectorAll('.btn-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          document.getElementById('pwd_user_id').value = id;
        });
      });
    </script>
  </body>
  </html>
