<?php
/**
 * Página de Inicio - Redirección al Login
 * Sistema de Conocimiento de Entrega de Insumos
 */

session_start();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Si no está autenticado, redirigir al login
header('Location: login.php');
exit();
?>