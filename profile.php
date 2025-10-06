<?php
/**
 * Perfil del usuario autenticado
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
$success_message = '';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Cargar datos actuales del usuario
    $q = $conn->prepare('SELECT id, nombre_completo, usuario, puesto, puesto_id, distrito_id, firma_path FROM usuarios WHERE id = :id');
    $q->bindParam(':id', $current_user['id'], PDO::PARAM_INT);
    $q->execute();
    $user = $q->fetch(PDO::FETCH_ASSOC);
    if (!$user) { throw new Exception('Usuario no encontrado'); }

    // Distritos para mostrar nombre (solo lectura en este formulario)
    $distrito_nombre = '';
    if (!empty($user['distrito_id'])) {
        $qd = $conn->prepare('SELECT nombre FROM distritos WHERE id = :id');
        $qd->bindParam(':id', $user['distrito_id'], PDO::PARAM_INT);
        $qd->execute();
        $distrito_nombre = ($qd->fetch(PDO::FETCH_COLUMN)) ?: '';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Error de seguridad. Token inválido.');
        }

        $nombre_completo = sanitize_input($_POST['nombre_completo'] ?? '');
        if (empty($nombre_completo)) { throw new Exception('El nombre completo es obligatorio.'); }

        // Actualizar nombre completo
        $u = $conn->prepare('UPDATE usuarios SET nombre_completo = :n WHERE id = :id');
        $u->bindParam(':n', $nombre_completo);
        $u->bindParam(':id', $current_user['id'], PDO::PARAM_INT);
        $u->execute();

        // Manejar firma (opcional), sin GD/Imagick compatible
        if (isset($_FILES['firma']) && is_uploaded_file($_FILES['firma']['tmp_name'])) {
            $mime = mime_content_type($_FILES['firma']['tmp_name']);
            $size = (int)$_FILES['firma']['size'];
            if (!in_array($mime, ['image/png','image/jpeg','image/jpg','image/webp'])) {
                throw new Exception('Formato de firma no permitido.');
            }
            if ($size > 2 * 1024 * 1024) { throw new Exception('La firma supera los 2 MB'); }

            $destDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'firmas';
            if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
            if (!is_dir($destDir)) { throw new Exception('No se pudo crear el directorio de firmas.'); }
            if (!is_writable($destDir)) { throw new Exception('No hay permisos de escritura en el directorio de firmas.'); }

            $dest = $destDir . "/user_{$current_user['id']}.png";
            // Sin GD: aceptar PNG solo si no tiene alfa; o JPG.
            $tmp = $_FILES['firma']['tmp_name'];
            $ok = false;
            if (function_exists('imagecreatefromstring')) {
                $raw = @file_get_contents($tmp);
                if ($raw === false) { throw new Exception('No se pudo leer la imagen de firma'); }
                $src = @imagecreatefromstring($raw);
                if ($src) {
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
                    $ok = imagepng($dst, $dest, 6);
                    imagedestroy($src); imagedestroy($dst);
                }
            }
            if (!$ok) {
                // Sin GD, aceptar PNG sin alfa únicamente
                $rawh = @fopen($tmp, 'rb');
                $hdr = $rawh ? @fread($rawh, 32) : '';
                if ($rawh) @fclose($rawh);
                $hasAlpha = false;
                if (strlen($hdr) >= 26 && substr($hdr,0,8) === "\x89PNG\x0D\x0A\x1A\x0A") {
                    $colorType = ord($hdr[25]);
                    if ($colorType === 4 || $colorType === 6) { $hasAlpha = true; }
                }
                if ($mime === 'image/png' && !$hasAlpha) {
                    $ok = move_uploaded_file($tmp, $dest);
                } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                    // copiar JPG como PNG sin transformación si no hay GD: igual funciona en PDF
                    $ok = move_uploaded_file($tmp, $dest);
                } else {
                    throw new Exception('Suba la firma en PNG sin transparencia o en JPG.');
                }
            }
            if (!$ok) { throw new Exception('No se pudo guardar la firma.'); }

            // Actualizar ruta
            $firma_rel = 'assets/firmas/' . basename($dest);
            $uf = $conn->prepare('UPDATE usuarios SET firma_path = :fp WHERE id = :id');
            $uf->bindParam(':fp', $firma_rel);
            $uf->bindParam(':id', $current_user['id'], PDO::PARAM_INT);
            $uf->execute();
            $user['firma_path'] = $firma_rel;
        }

        $success_message = 'Perfil actualizado correctamente.';
        log_user_activity($current_user['id'], 'profile_updated', 'Actualizó su perfil');

        // Refrescar nombre
        $user['nombre_completo'] = $nombre_completo;
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// CSRF token
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <li class="breadcrumb-item active">Mi Perfil</li>
              </ol>
            </nav>

            <div class="card">
              <div class="card-header"><strong>Mi Perfil</strong></div>
              <div class="card-body">
                <?php if (!empty($error_message)): ?>
                  <div class="alert alert-danger"><?php echo escape_html($error_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                  <div class="alert alert-success"><?php echo escape_html($success_message); ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Nombre Completo</label>
                      <input type="text" class="form-control" name="nombre_completo" value="<?php echo escape_html($user['nombre_completo'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Usuario</label>
                      <input type="text" class="form-control" value="<?php echo escape_html($user['usuario'] ?? ''); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Puesto</label>
                      <input type="text" class="form-control" value="<?php echo escape_html($user['puesto'] ?? ''); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Distrito</label>
                      <input type="text" class="form-control" value="<?php echo escape_html($distrito_nombre); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Firma (PNG sin transparencia o JPG, máx. 2MB)</label>
                      <input type="file" class="form-control" name="firma" accept="image/png,image/jpeg,image/jpg">
                      <?php if (!empty($user['firma_path'])): ?>
                        <div class="mt-2">
                          <small class="text-muted">Firma actual:</small><br>
                          <img src="<?php echo escape_html($user['firma_path']); ?>" alt="Firma" style="max-height:80px; max-width:220px; border:1px solid #ddd; padding:4px; background:#fff;">
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar</button>
                    <a href="change_password.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-key me-2"></i>Cambiar Contraseña</a>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
  </html>

