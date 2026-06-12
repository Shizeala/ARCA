<?php
// teacher/submissions.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';

require_role('teacher');

$user      = current_user();
$uid       = (int)$user['id'];
$devices   = fetch_all('SELECT device_id FROM devices WHERE assigned_teacher=?', [$uid]);
$deviceIds = array_column($devices, 'device_id');
$inClause  = $deviceIds ? implode(',', array_fill(0, count($deviceIds), '?')) : "'_none_'";

$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;
$offset = ($page - 1) * $per;

$where  = $deviceIds ? ["s.device_id IN ($inClause)"] : ["1=0"];
$params = $deviceIds ? array_values($deviceIds) : [];

if (in_array($status, ['pending', 'processing', 'done', 'error'], true)) {
    $where[] = "s.status=?";
    $params[] = $status;
}
if ($q) {
    $where[] = "(s.student_name LIKE ? OR s.student_id LIKE ? OR s.section LIKE ?)";
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}
$wSQL  = 'WHERE ' . implode(' AND ', $where);

$total = (int)fetch_one("SELECT COUNT(*) c FROM submissions s $wSQL", $params)['c'];
$pages = max(1, (int)ceil($total / $per));
$rows  = fetch_all("SELECT s.*,d.name AS device_name FROM submissions s LEFT JOIN devices d ON d.device_id=s.device_id $wSQL ORDER BY s.uploaded_at DESC LIMIT $per OFFSET $offset", $params);

function furl($s, $q, $p = 1)
{
    return '?' . http_build_query(array_filter(['status' => $s, 'q' => $q, 'page' => $p]));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Submissions — ARCA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/arca.css">
    <link rel="icon" href="<?= BASE_URL ?>/ARCA.svg" type="image/x-icon">
</head>

<body>
    <div class="app-shell">
        <?php layout_sidebar('teacher', 'subs'); ?>
        <?php layout_topbar('Submissions', 'From My Devices'); ?>

        <!-- Toolbar -->
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
            <form method="get" style="position:relative;flex:1;max-width:300px">
                <?php if ($status): ?><input type="hidden" name="status" value="<?= esc($status) ?>"><?php endif; ?>
                <input class="form-control" style="padding-left:32px" type="text" name="q" value="<?= esc($q) ?>" placeholder="Search…">
            </form>
            <div style="display:flex;gap:6px">
                <?php foreach (['' => 'All', 'pending' => 'Pending', 'done' => 'Done', 'error' => 'Error'] as $v => $l): ?>
                    <a href="<?= furl($v, $q) ?>" class="btn btn-sm <?= $status === $v ? 'btn-primary' : 'btn-secondary' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>
            <span style="margin-left:auto;font-size:12px;color:var(--text-soft);font-family:var(--font-data)"><?= $total ?> result(s)</span>
        </div>

        <div class="card animate-in">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Student</th>
                            <th>ID</th>
                            <th>Section</th>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Confidence</th>
                            <th>Uploaded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="mono"><?= $r['id'] ?></td>
                                <td>
                                    <?php if ($r['image_path'] && file_exists(ROOT_PATH . '/' . $r['image_path'])): ?>
                                        <a href="<?= BASE_URL . '/' . $r['image_path'] ?>" target="_blank"><img src="<?= BASE_URL . '/' . $r['image_path'] ?>" class="thumb" alt=""></a>
                                    <?php else: ?><div class="thumb-placeholder">📄</div><?php endif; ?>
                                </td>
                                <td style="font-weight:500"><?= esc($r['student_name'] ?? '—') ?></td>
                                <td class="mono"><?= esc($r['student_id'] ?? '—') ?></td>
                                <td><?= esc($r['section'] ?? '—') ?></td>
                                <td class="mono" style="font-size:12px"><?= esc($r['device_name'] ?? $r['device_id'] ?? '—') ?></td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td>
                                    <?php if ($r['ai_confidence'] !== null): ?>
                                        <div class="conf-wrap">
                                            <div class="conf-bar">
                                                <div class="conf-fill" style="width:<?= min(100, (float)$r['ai_confidence']) ?>%"></div>
                                            </div><span class="conf-val"><?= number_format($r['ai_confidence'], 1) ?>%</span>
                                        </div>
                                        <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="mono" style="font-size:11px;color:var(--text-soft)"><?= esc(substr($r['uploaded_at'], 0, 16)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rows)): ?><tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <div class="icon">📭</div>
                                        <p>No submissions.</p>
                                    </div>
                                </td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($pages > 1): ?>
            <div style="display:flex;justify-content:flex-end;gap:6px;margin-top:14px">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <a href="<?= furl($status, $q, $p) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <?php layout_close(); ?>
</body>

</html>