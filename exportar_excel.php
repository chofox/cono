<?php
require_once __DIR__ . '/config/database.php';
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = APP_URL . '/modules/conocimientos/pages/exportar_excel.php' . ($query ? '?' . $query : '');
header('Location: ' . $target);
exit();
