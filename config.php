<?php
// ================================================================
//  config.php — ARCA Central Configuration
//  Include at the top of every PHP file.
// ================================================================

// ── Database ──────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'u259569521_submissions'); // ← exact name
define('DB_USER', 'u259569521_arca');            // ← your DB user
define('DB_PASS', 'Jironebb01!');       // ← VERY IMPORTANT
define('DB_CHARSET', 'utf8mb4');

// ── Paths ────────────────────────────────────────────────────
define('BASE_URL',    'http://hirosheii.com/arca');  // ← no trailing slash
define('ROOT_PATH',   __DIR__);
define('UPLOAD_DIR',  ROOT_PATH . '/uploads/');
define('UPLOAD_URL',  BASE_URL  . '/uploads/');

// ── Upload limits ─────────────────────────────────────────────────
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);   // 10 MB
define('ALLOWED_MIME', ['image/jpeg','image/png','image/gif','image/webp']);

// ── Session ───────────────────────────────────────────────────────
define('SESSION_NAME',    'arca_session');
define('SESSION_TIMEOUT', 3600);  // seconds

// ── Email (PHPMailer / SMTP) ──────────────────────────────────────
define('MAIL_HOST',       'smtp.hostinger.com');
define('MAIL_PORT',       587);
define('MAIL_SECURE',     'tls');          // 'ssl' or 'tls'
define('MAIL_USERNAME',   'hermes@hirosheii.com');
define('MAIL_PASSWORD',   'Jironebb01!');
define('MAIL_FROM_NAME',  'ARCA System');
define('MAIL_FROM_EMAIL', 'hermes@hirosheii.com');

// ── ZXing barcode API ─────────────────────────────────────────────
define('ZXING_API_URL', 'https://api.qrserver.com/v1/read-qr-code/');
// Alternative: https://zxing.org/w/decode  (file upload, scrape result)

// ── Misc ──────────────────────────────────────────────────────────
define('APP_NAME',    'ARCA');
define('APP_TAGLINE', 'Automated Record and Classification Assistant');
define('USE_MOCK_AI', true);  // set false when real barcode API is ready

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}