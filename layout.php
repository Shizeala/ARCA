<?php
// ================================================================
//  layout.php — Shared layout helpers
//  Usage: include from any page after require_once auth.php
// ================================================================

/**
 * Emit the full HTML <head> block.
 * $active = current nav item key
 */
function layout_head(string $title, string $extra_css = ''): void {
    $app = APP_NAME;
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title} — {$app}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{BASE_URL}/assets/arca.css">
{$extra_css}
</head>
<body>
<div class="app-shell">
HTML;
    // Replace constant in href
    echo str_replace('{BASE_URL}', BASE_URL, '');
}

function layout_sidebar(string $role, string $active = ''): void {
    $user     = current_user();
    $initials = strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1));
    $name     = htmlspecialchars($user['full_name'] ?? $user['username']);
    $roleLabel = ucfirst($role);
    $base      = BASE_URL;

    $navs = [
        'admin' => [
            'Dashboard'   => ['key'=>'dashboard', 'icon'=>'grid',      'href'=>'/admin/index.php'],
            'Students'    => ['key'=>'students',  'icon'=>'users',     'href'=>'/admin/students.php'],
            'Teachers'    => ['key'=>'teachers',  'icon'=>'briefcase', 'href'=>'/admin/teachers.php'],
            'Devices'     => ['key'=>'devices',   'icon'=>'cpu',       'href'=>'/admin/devices.php'],
            'Submissions' => ['key'=>'subs',      'icon'=>'inbox',     'href'=>'/admin/submissions.php'],
        ],
        'teacher' => [
            'Dashboard'   => ['key'=>'dashboard', 'icon'=>'grid',  'href'=>'/teacher/index.php'],
            'Submissions' => ['key'=>'subs',      'icon'=>'inbox', 'href'=>'/teacher/submissions.php'],
        ],
        'student' => [
            'Dashboard'   => ['key'=>'dashboard', 'icon'=>'grid',    'href'=>'/student/index.php'],
            'My History'  => ['key'=>'history',   'icon'=>'archive', 'href'=>'/student/history.php'],
        ],
    ];

    $icons = [
        'grid'      => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        'users'     => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'briefcase' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
        'cpu'       => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>',
        'inbox'     => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
        'archive'   => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
    ];

    echo "<aside class='sidebar'>";
    echo "<div class='sidebar-brand'>";
    echo "<div class='wordmark'>ARCA</div>";
    echo "<div class='tagline'>Automated Record &amp; Classification</div>";
    echo "<span class='sidebar-role-badge'>{$roleLabel}</span>";
    echo "</div>";
    echo "<nav class='sidebar-nav'>";

    foreach (($navs[$role] ?? []) as $label => $item) {
        $href    = $base . $item['href'];
        $cls     = ($active === $item['key']) ? 'nav-item active' : 'nav-item';
        $iconSvg = $icons[$item['icon']] ?? '';
        echo "<a href='{$href}' class='{$cls}'>{$iconSvg} " . htmlspecialchars($label) . "</a>";
    }

    echo "</nav>";
    echo "<div class='sidebar-footer'>";
    echo "<div class='sidebar-user'>";
    echo "<div class='avatar'>{$initials}</div>";
    echo "<div class='sidebar-user-info'><div class='name'>{$name}</div><div class='role'>{$roleLabel}</div></div>";
    echo "</div>";
    echo "<a href='{$base}/logout.php' class='btn-logout'>";
    echo '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    echo " Log Out</a>";
    echo "</div></aside>";
}

function layout_topbar(string $title, string $subtitle = ''): void {
    $time = date('D, d M Y · H:i');
    echo "<div class='main-content'>";
    echo "<div class='topbar'>";
    echo "<div class='topbar-title'>" . htmlspecialchars($title) . ($subtitle ? " <span style='font-weight:300;color:var(--text-soft)'>/ {$subtitle}</span>" : '') . "</div>";
    echo "<div class='topbar-right'><span class='live-dot'></span> &nbsp; {$time}</div>";
    echo "</div>";
    echo "<div class='page-body'>";
}

function layout_close(): void {
    echo "</div></div></div></body></html>";
}

// ── Flash messages ─────────────────────────────────────────────────
function flash(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}
function get_flash(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}
function render_flash(): void {
    foreach (['success','danger','warning','info'] as $type) {
        if ($msg = get_flash($type)) {
            echo "<div class='alert alert-{$type}'>" . htmlspecialchars($msg) . "</div>";
        }
    }
}

// ── HTML helpers ───────────────────────────────────────────────────
function status_badge(string $s): string {
    return "<span class='badge badge-{$s}'>{$s}</span>";
}
function esc(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}