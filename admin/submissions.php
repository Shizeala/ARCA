<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';

require_role('admin');

// ── Filters ────────────────────────────────────────────────────────
$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;
$offset = ($page - 1) * $per;

$where  = [];
$params = [];
if (in_array($status, ['pending', 'processing', 'done', 'error'], true)) {
  $where[] = 's.status = ?';
  $params[] = $status;
}
if ($q !== '') {
  $where[] = '(s.student_name LIKE ? OR s.student_id LIKE ? OR s.section LIKE ? OR s.device_id LIKE ?)';
  $params  = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%"]);
}
$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) fetch_one("SELECT COUNT(*) c FROM submissions s LEFT JOIN devices d ON d.device_id=s.device_id $wSQL", $params)['c'];
$pages = max(1, (int)ceil($total / $per));

// PDO can't bind LIMIT with named params easily, so use direct concatenation after casting
$rows  = fetch_all(
  "SELECT s.*, d.name AS device_name FROM submissions s
     LEFT JOIN devices d ON d.device_id=s.device_id
     $wSQL ORDER BY s.uploaded_at DESC LIMIT $per OFFSET $offset",
  $params
);

function filter_url(string $status, string $q, int $page = 1): string
{
  return '?' . http_build_query(array_filter(['status' => $status, 'q' => $q, 'page' => $page]));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Submissions — ARCA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/arca.css">
  <link rel="icon" href="<?= BASE_URL ?>/ARCA.svg" type="image/x-icon">
</head>

<body>
  <div class="app-shell">
    <?php layout_sidebar('admin', 'subs'); ?>
    <?php layout_topbar('Submissions', 'All Records'); ?>

    <?php render_flash(); ?>

    <!-- Toolbar -->
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
      <form method="get" style="position:relative;flex:1;max-width:320px">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= esc($status) ?>"><?php endif; ?>
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--slate-lt);pointer-events:none" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.35-4.35" />
        </svg>
        <input class="form-control" style="padding-left:32px" type="text" name="q" value="<?= esc($q) ?>" placeholder="Search name, ID, section…">
      </form>

      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach (['' => 'All', 'pending' => 'Pending', 'processing' => 'Processing', 'done' => 'Done', 'error' => 'Error'] as $val => $label): ?>
          <a href="<?= filter_url($val, $q) ?>" class="btn btn-sm <?= $status === $val ? 'btn-primary' : 'btn-secondary' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>

      <div style="margin-left:auto;font-size:12px;color:var(--text-soft);font-family:var(--font-data)">
        <?= $total ?> result(s)
      </div>
    </div>

    <!-- Table -->
    <div class="card animate-in">
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
              <th>Confidence</th>
              <th>Email</th>
              <th>Uploaded</th>
              <th>Processed</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="mono"><?= $r['id'] ?></td>
                <td>
                  <?php $imgPath = ROOT_PATH . '/' . $r['image_path']; ?>
                  <?php if ($r['image_path'] && file_exists($imgPath)): ?>
                    <a href="<?= BASE_URL . '/' . $r['image_path'] ?>" target="_blank">
                      <img src="<?= BASE_URL . '/' . $r['image_path'] ?>" class="thumb" alt="">
                    </a>
                  <?php else: ?><div class="thumb-placeholder">📄</div><?php endif; ?>
                </td>
                <td>
                  <div style="font-weight:500"><?= esc($r['student_name'] ?? '—') ?></div>
                  <div style="font-size:11px;color:var(--text-soft);font-family:var(--font-data)"><?= esc($r['student_id'] ?? '') ?></div>
                </td>
                <td><?= esc($r['section'] ?? '—') ?></td>
                <td class="mono" style="font-size:12px"><?= esc($r['device_name'] ?? $r['device_id'] ?? '—') ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td>
                  <?php if ($r['ai_confidence'] !== null): ?>
                    <div class="conf-wrap">
                      <div class="conf-bar">
                        <div class="conf-fill" style="width:<?= min(100, (float)$r['ai_confidence']) ?>%"></div>
                      </div>
                      <span class="conf-val"><?= number_format($r['ai_confidence'], 1) ?>%</span>
                    </div>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <?php if ($r['email_sent']): ?>
                    <span class="badge badge-done">Sent</span>
                  <?php else: ?>
                    <span style="color:var(--slate-lt);font-size:12px">—</span>
                  <?php endif; ?>
                </td>
                <td class="mono" style="font-size:11px;color:var(--text-soft)"><?= esc(substr($r['uploaded_at'], 0, 16)) ?></td>
                <td class="mono" style="font-size:11px;color:var(--text-soft)"><?= $r['processed_at'] ? esc(substr($r['processed_at'], 0, 16)) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="10">
                  <div class="empty-state">
                    <div class="icon">📭</div>
                    <p>No submissions found.</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;font-family:var(--font-data);font-size:12px;color:var(--text-soft)">
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <div style="display:flex;gap:6px">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="<?= filter_url($status, $q, $p) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php layout_close(); ?>
    <script>
      // Poll for updates every 5 s
      setInterval(() => location.reload(), 5000);
    </script>
</body>

</html>