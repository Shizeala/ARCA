<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect_by_role();
} else {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}