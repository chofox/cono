<?php
/**
 * Cerrar Sesión
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$result = $auth->logout();

// Redirigir al login con mensaje
if ($result['success']) {
    header('Location: login.php?message=' . urlencode('Sesión cerrada correctamente'));
} else {
    header('Location: login.php?error=' . urlencode('Error cerrando sesión'));
}
exit();
?>