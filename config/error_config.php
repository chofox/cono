<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$log_file = __DIR__ . '/../error.log';
ini_set('error_log', $log_file);
?>