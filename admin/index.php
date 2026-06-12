<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';

require_role('admin');

// ── Stats ─────────────────────────────────────────────────────────
$totalStudents  = (int) fetch_one('SELECT COUNT(*) c FROM students')['c'];
$totalTeachers  = (int) fetch_one('SELECT COUNT(*) c FROM users WHERE role="teacher"')['c'];
$totalDevices   = (int) fetch_one('SELECT COUNT(*) c FROM devices')['c'];
$totalSubs      = (int) fetch_one('SELECT COUNT(*) c FROM submissions')['c'];
$pendingSubs    = (int) fetch_one('SELECT COUNT(*) c FROM submissions WHERE status="pending"')['c'];
$doneSubs       = (int) fetch_one('SELECT COUNT(*) c FROM submissions WHERE status="done"')['c'];
$errorSubs      = (int) fetch_one('SELECT COUNT(*) c FROM submissions WHERE status="error"')['c'];

// ── Recent submissions ─────────────────────────────────────────────
$recent = fetch_all(
    'SELECT s.*, d.name AS device_name FROM submissions s
     LEFT JOIN devices d ON d.device_id = s.device_id
     ORDER BY s.uploaded_at DESC LIMIT 10'
);

// ── Recent activity by hour (last 24 h) ───────────────────────────
$activity = fetch_all(
    "SELECT DATE_FORMAT(uploaded_at,'%H:00') hr, COUNT(*) cnt
     FROM submissions WHERE uploaded_at >= NOW() - INTERVAL 24 HOUR
     GROUP BY hr ORDER BY hr"
);
$actJson = json_encode(array_values($activity));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — ARCA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/arca.css">
<link rel="icon" href="<?= BASE_URL ?>/ARCA.svg" type="image/x-icon">
</head>
<body>
<div class="app-shell">
<?php layout_sidebar('admin', 'dashboard'); ?>
<?php layout_topbar('Dashboard', 'Overview'); ?>

<div class="animate-in">

<!-- Stat Grid -->
<div class="stat-grid">
  <div class="stat-card blue animate-in delay-1">
    <span class="stat-label">Total Students</span>
    <span class="stat-value"><?= $totalStudents ?></span>
    <span class="stat-sub">Registered in system</span>
  </div>
  <div class="stat-card green animate-in delay-1">
    <span class="stat-label">Teachers</span>
    <span class="stat-value"><?= $totalTeachers ?></span>
    <span class="stat-sub">Active accounts</span>
  </div>
  <div class="stat-card teal animate-in delay-1">
    <span class="stat-label">Devices</span>
    <span class="stat-value"><?= $totalDevices ?></span>
    <span class="stat-sub">Registered scanners</span>
  </div>
  <div class="stat-card violet animate-in delay-2">
    <span class="stat-label">Total Submissions</span>
    <span class="stat-value"><?= $totalSubs ?></span>
    <span class="stat-sub">All time</span>
  </div>
  <div class="stat-card amber animate-in delay-2">
    <span class="stat-label">Pending</span>
    <span class="stat-value"><?= $pendingSubs ?></span>
    <span class="stat-sub">Awaiting processing</span>
  </div>
  <div class="stat-card red animate-in delay-2">
    <span class="stat-label">Errors</span>
    <span class="stat-value"><?= $errorSubs ?></span>
    <span class="stat-sub">Failed submissions</span>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-bottom:24px">

  <!-- Recent Submissions -->
  <div class="card animate-in delay-3">
    <div class="card-header">
      <div class="card-title">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
        Recent Submissions
        <span class="live-dot"></span>
      </div>
      <a href="<?= BASE_URL ?>/admin/submissions.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Preview</th><th>Student</th><th>Section</th><th>Device</th><th>Status</th><th>Time</th></tr>
        </thead>
        <tbody id="recent-tbody">
        <?php foreach ($recent as $r): ?>
          <tr>
            <td class="mono"><?= $r['id'] ?></td>
            <td>
              <?php if ($r['image_path'] && file_exists(ROOT_PATH.'/'.$r['image_path'])): ?>
                <img src="<?= BASE_URL.'/'.$r['image_path'] ?>" class="thumb" alt="">
              <?php else: ?>
                <div class="thumb-placeholder">📄</div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:500"><?= esc($r['student_name'] ?? '—') ?></div>
              <div style="font-size:11px;color:var(--text-soft);font-family:var(--font-data)"><?= esc($r['student_id'] ?? '') ?></div>
            </td>
            <td><?= esc($r['section'] ?? '—') ?></td>
            <td class="mono" style="font-size:12px"><?= esc($r['device_name'] ?? $r['device_id'] ?? '—') ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="mono" style="font-size:11px;color:var(--text-soft)"><?= esc(substr($r['uploaded_at'],0,16)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
          <tr><td colspan="7"><div class="empty-state"><div class="icon">📭</div><p>No submissions yet.</p></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Quick Actions -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card animate-in delay-3">
      <div class="card-header"><div class="card-title">Quick Actions</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
        <a href="<?= BASE_URL ?>/admin/students.php?action=add" class="btn btn-primary" style="justify-content:center">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Student
        </a>
        <a href="<?= BASE_URL ?>/admin/teachers.php?action=add" class="btn btn-secondary" style="justify-content:center">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Teacher
        </a>
        <a href="<?= BASE_URL ?>/admin/devices.php?action=add" class="btn btn-secondary" style="justify-content:center">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Register Device
        </a>
      </div>
    </div>

    <div class="card animate-in delay-4">
      <div class="card-header"><div class="card-title">Processing Queue</div></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:center">
          <?php foreach (['pending'=>'amber','processing'=>'violet','done'=>'green','error'=>'red'] as $s => $c):
            $cnt = fetch_one("SELECT COUNT(*) c FROM submissions WHERE status='$s'")['c']; ?>
          <div style="padding:12px;background:var(--bg2);border-radius:8px;border:1px solid var(--line)">
            <div style="font-size:22px;font-family:var(--font-data);font-weight:500;color:var(--<?=$c?>)"><?= $cnt ?></div>
            <div style="font-size:10px;font-family:var(--font-data);text-transform:uppercase;letter-spacing:.08em;color:var(--text-soft);margin-top:2px"><?= $s ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</div><!-- /grid -->
</div><!-- /animate-in -->

<?php layout_close(); ?>

<script>
// Auto-refresh recent submissions table every 5 seconds
setInterval(() => {
  fetch('<?= BASE_URL ?>/admin/api/recent_submissions.php')
    .then(r => r.json())
    .then(data => {
      if (!data.rows) return;
      // Simple re-render approach
      location.reload();
    })
    .catch(() => {});
}, 5000);
</script>
</body>
</html>