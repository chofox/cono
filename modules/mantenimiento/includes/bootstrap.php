<?php
// Bootstrap común para páginas del módulo de mantenimiento
session_start();

require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/classes/Auth.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';
require_once dirname(__DIR__) . '/services/MantenimientoRepository.php';

if (!isset($auth) || !($auth instanceof Auth)) {
    $auth = new Auth();
}

if (!isset($current_user) || empty($current_user)) {
    $current_user = $auth->getCurrentUser();
}

$repository = $repository ?? new MantenimientoRepository();
