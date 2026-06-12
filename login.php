<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Already logged in → redirect
if (is_logged_in()) redirect_by_role();

$error   = '';
$timeout = !empty($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        if (login($username, $password)) {
            redirect_by_role();
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — ARCA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/ARCA.svg" type="image/x-icon">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --navy: #0f1c2e;
            --navy-mid: #162540;
            --blue: #2563eb;
            --line: #e2e8f0;
            --text: #0f172a;
            --text-soft: #475569;
            --green: #16a34a;
            --red: #dc2626;
            --bg: #f8fafc;
            --font-ui: 'DM Sans', sans-serif;
            --font-data: 'DM Mono', monospace;
            --font-disp: 'DM Serif Display', serif;
            --radius: 8px;
        }

        body {
            font-family: var(--font-ui);
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            -webkit-font-smoothing: antialiased;
        }

        /* Left panel */
        .login-left {
            width: 420px;
            flex-shrink: 0;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 44px;
            position: relative;
            overflow: hidden;
        }

        /* Subtle grid overlay */
        .login-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, .03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .03) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        .brand {
            position: relative;
            z-index: 1;
        }

        .brand-mark {
            font-family: var(--font-disp);
            font-size: 48px;
            color: #fff;
            line-height: 1;
            letter-spacing: -.01em;
        }

        .brand-full {
            font-size: 10px;
            font-family: var(--font-data);
            text-transform: uppercase;
            letter-spacing: .18em;
            color: rgba(255, 255, 255, .35);
            margin-top: 8px;
            line-height: 1.5;
        }

        .brand-desc {
            margin-top: 28px;
            font-size: 14px;
            color: rgba(255, 255, 255, .5);
            line-height: 1.7;
            max-width: 300px;
        }

        .features {
            position: relative;
            z-index: 1;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-top: 1px solid rgba(255, 255, 255, .07);
        }

        .feature-item:last-child {
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .feature-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #3b82f6;
            margin-top: 6px;
            flex-shrink: 0;
        }

        .feature-item p {
            font-size: 12px;
            color: rgba(255, 255, 255, .45);
            line-height: 1.5;
        }

        .feature-item strong {
            display: block;
            font-size: 12px;
            color: rgba(255, 255, 255, .75);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .left-footer {
            position: relative;
            z-index: 1;
            font-size: 10px;
            font-family: var(--font-data);
            color: rgba(255, 255, 255, .2);
            letter-spacing: .08em;
        }

        /* Right panel */
        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .login-box {
            width: 100%;
            max-width: 380px;
        }

        .login-heading {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }

        .login-sub {
            font-size: 13px;
            color: var(--text-soft);
            margin-bottom: 36px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 10px 13px;
            background: #fff;
            border: 1.5px solid var(--line);
            border-radius: var(--radius);
            font-size: 14px;
            color: var(--text);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            font-family: var(--font-ui);
        }

        .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 11px;
            background: var(--blue);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-ui);
            transition: background .15s;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 11px 14px;
            border-radius: var(--radius);
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-danger {
            background: #fee2e2;
            color: #7f1d1d;
            border-color: #fca5a5;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0 20px;
            color: var(--text-soft);
            font-size: 11px;
            font-family: var(--font-data);
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        .role-hints {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            font-size: 11px;
            font-family: var(--font-data);
        }

        .role-hint {
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }

        .role-hint .r {
            font-weight: 500;
            color: var(--text);
        }

        .role-hint .u {
            color: var(--text-soft);
            margin-top: 2px;
        }

        .login-footer {
            margin-top: 40px;
            font-size: 11px;
            color: var(--text-soft);
            font-family: var(--font-data);
            text-align: center;
        }

        @media (max-width: 700px) {
            .login-left {
                display: none;
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-6px);
            }

            40%,
            80% {
                transform: translateX(6px);
            }
        }

        .shake {
            animation: shake .4s ease;
        }
    </style>
</head>

<body>

    <div class="login-left">
        <div class="brand">
            <div class="brand-mark">ARCA</div>
            <div class="brand-full">Automated Record &amp;<br>Classification Assistant</div>
            <p class="brand-desc">
                A barcode-based document submission and student identification system for modern schools.
            </p>
        </div>

        <div class="features">
            <div class="feature-item">
                <div class="feature-dot"></div>
                <div>
                    <strong>Barcode Recognition</strong>
                    <p>Automatically identifies students from document barcodes via ZXing API.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-dot" style="background:#34d399"></div>
                <div>
                    <strong>Real-Time Processing</strong>
                    <p>Submissions queue and process automatically. Dashboard updates every 5 seconds.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-dot" style="background:#f59e0b"></div>
                <div>
                    <strong>Email Notifications</strong>
                    <p>Students and teachers receive instant notifications after processing.</p>
                </div>
            </div>
        </div>

        <div class="left-footer">
            © <?= date('Y') ?> · ARCA SYSTEM · v1.0
        </div>
    </div>

    <div class="login-right">
        <div class="login-box">
            <h1 class="login-heading">Welcome back</h1>
            <p class="login-sub">Sign in to your ARCA account to continue.</p>

            <?php if ($timeout): ?>
                <div class="alert alert-warning">Your session expired. Please log in again.</div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" id="err-alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" id="login-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" type="text" id="username" name="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="username" autofocus required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password"
                        autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-submit">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Sign In
                </button>
            </form>

            <div class="login-footer">ARCA · SCHOOL DOCUMENT PROCESSING SYSTEM</div>
        </div>
    </div>

    <script>
        <?php if ($error): ?>
            document.getElementById('login-form').classList.add('shake');
        <?php endif; ?>
    </script>
</body>

</html>

