<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

try {
    db();
    echo "✅ Database connected successfully!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}