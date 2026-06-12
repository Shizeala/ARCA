<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
logout();
header('Location: ' . BASE_URL . '/login.php');
exit;