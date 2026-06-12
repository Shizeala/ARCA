<?php
// teacher/index.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';

require_role('teacher');

$user = current_user();
$uid  = (int)$user['id'];

// Teacher's assigned devices
$devices = fetch_all('SELECT * FROM devices WHERE assigned_teacher=?', [$uid]);
$deviceIds = array_column($devices, 'device_id');

// Stats for their devices
$inClause = $deviceIds ? implode(',', array_fill(0, count($deviceIds), '?')) : "'_none_'";
$stats = [];
foreach (['pending', 'processing', 'done', 'error'] as $s) {
    $stats[$s] = $deviceIds
        ? (int)fetch_one("SELECT COUNT(*) c FROM submissions WHERE status='$s' AND device_id IN ($inClause)", $deviceIds)['c']
        : 0;
}
$total = array_sum($stats);

// Recent submissions
$recent = $deviceIds
    ? fetch_all("SELECT s.*, d.name AS device_name FROM submissions s
                 LEFT JOIN devices d ON d.device_id=s.device_id
                 WHERE s.device_id IN ($inClause)
                 ORDER BY s.uploaded_at DESC LIMIT 10", $deviceIds)
    : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Teacher Dashboard — ARCA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/arca.css">
    <link rel="icon" href="<?= BASE_URL ?>/ARCA.svg" type="image/x-icon">
</head>

<body>
    <div class="app-shell">
        <?php layout_sidebar('teacher', 'dashboard'); ?>
        <?php layout_topbar('Dashboard', esc($user['full_name'] ?? $user['username'])); ?>

        <div class="animate-in">

            <?php if (empty($devices)): ?>
                <div class="alert alert-warning">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    No devices are assigned to your account yet. Contact the administrator.
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stat-grid">
                <div class="stat-card blue animate-in delay-1">
                    <span class="stat-label">Total Submissions</span>
                    <span class="stat-value"><?= $total ?></span>
                    <span class="stat-sub">From your device(s)</span>
                </div>
                <div class="stat-card amber animate-in delay-1">
                    <span class="stat-label">Pending</span>
                    <span class="stat-value"><?= $stats['pending'] ?></span>
                </div>
                <div class="stat-card green animate-in delay-1">
                    <span class="stat-label">Done</span>
                    <span class="stat-value"><?= $stats['done'] ?></span>
                </div>
                <div class="stat-card red animate-in delay-1">
                    <span class="stat-label">Errors</span>
                    <span class="stat-value"><?= $stats['error'] ?></span>
                </div>
            </div>

            <!-- Devices Info -->
            <?php if (!empty($devices)): ?>
                <div class="card animate-in delay-2" style="margin-bottom:20px">
                    <div class="card-header">
                        <div class="card-title">Your Assigned Devices</div>
                    </div>
                    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap">
                        <?php foreach ($devices as $d): ?>
                            <div style="background:var(--bg2);border:1px solid var(--line);border-radius:8px;padding:12px 18px;min-width:180px">
                                <div style="font-weight:600;font-size:13px"><?= esc($d['name']) ?></div>
                                <div style="font-family:var(--font-data);font-size:11px;color:var(--text-soft);margin-top:3px"><?= esc($d['device_id']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Submissions -->
            <div class="card animate-in delay-3">
                <div class="card-header">
                    <div class="card-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                            <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                        </svg>
                        Recent Submissions <span class="live-dot"></span>
                    </div>
                    <a href="<?= BASE_URL ?>/teacher/submissions.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Preview</th>
                                <th>Student</th>
                                <th>Section</th>
                                <th>Device</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td class="mono"><?= $r['id'] ?></td>
                                    <td>
                                        <?php if ($r['image_path'] && file_exists(ROOT_PATH . '/' . $r['image_path'])): ?>
                                            <a href="<?= BASE_URL . '/' . $r['image_path'] ?>" target="_blank"><img src="<?= BASE_URL . '/' . $r['image_path'] ?>" class="thumb" alt=""></a>
                                        <?php else: ?><div class="thumb-placeholder">📄</div><?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:500"><?= esc($r['student_name'] ?? '—') ?></div>
                                        <div style="font-size:11px;color:var(--text-soft);font-family:var(--font-data)"><?= esc($r['student_id'] ?? '') ?></div>
                                    </td>
                                    <td><?= esc($r['section'] ?? '—') ?></td>
                                    <td class="mono" style="font-size:12px"><?= esc($r['device_name'] ?? $r['device_id'] ?? '—') ?></td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td class="mono" style="font-size:11px;color:var(--text-soft)"><?= esc(substr($r['uploaded_at'], 0, 16)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent)): ?><tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="icon">📭</div>
                                            <p>No submissions from your devices yet.</p>
                                        </div>
                                    </td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /animate-in -->

        <?php layout_close(); ?>
</body>

</html>