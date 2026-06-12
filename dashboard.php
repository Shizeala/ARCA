<?php
// =============================================================
//  dashboard.php — Submissions dashboard
// =============================================================
require_once __DIR__ . '/config.php';

// ── Filters ──────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

// ── Query ─────────────────────────────────────────────────────
$where  = [];
$params = [];
if (in_array($filterStatus, ['pending','processing','done','error'], true)) {
    $where[]  = 'status = :status';
    $params[':status'] = $filterStatus;
}
if ($search !== '') {
    $where[]  = '(student_name LIKE :q OR student_id LIKE :q OR section LIKE :q OR device_id LIKE :q)';
    $params[':q'] = "%$search%";
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$pdo     = db();
$total   = (int) $pdo->prepare("SELECT COUNT(*) FROM submissions $whereSQL")->execute($params)
    ? $pdo->prepare("SELECT COUNT(*) FROM submissions $whereSQL")->execute($params) && 1 : 0;

// Re-run cleanly
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM submissions $whereSQL");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$dataStmt = $pdo->prepare(
    "SELECT * FROM submissions $whereSQL ORDER BY uploaded_at DESC LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) { $dataStmt->bindValue($k, $v); }
$dataStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll();

// ── Stats ─────────────────────────────────────────────────────
$stats = $pdo->query(
    "SELECT status, COUNT(*) as cnt FROM submissions GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

function statusBadge(string $s): string {
    $map = [
        'pending'    => ['label' => 'Pending',    'class' => 'badge-pending'],
        'processing' => ['label' => 'Processing', 'class' => 'badge-processing'],
        'done'       => ['label' => 'Done',        'class' => 'badge-done'],
        'error'      => ['label' => 'Error',       'class' => 'badge-error'],
    ];
    $b = $map[$s] ?? ['label' => ucfirst($s), 'class' => ''];
    return "<span class=\"badge {$b['class']}\">{$b['label']}</span>";
}

function esc(mixed $v): string {
    return htmlspecialchars((string)($v ?? '—'), ENT_QUOTES, 'UTF-8');
}

$q = fn(string $k, mixed $default = '') => esc($_GET[$k] ?? $default);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DocScan — Submission Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  /* ── Reset & tokens ─────────────────────────────────────── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #0d0f12;
    --surface:   #141720;
    --surface2:  #1c2030;
    --border:    #252a38;
    --text:      #dde1ec;
    --muted:     #616b85;
    --accent:    #4f8ef7;
    --accent2:   #38d9a9;

    --pending:   #f59e0b;
    --processing:#818cf8;
    --done:      #34d399;
    --error:     #f87171;

    --font-sans: 'IBM Plex Sans', sans-serif;
    --font-mono: 'IBM Plex Mono', monospace;
    --radius:    6px;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-sans);
    font-size: 14px;
    line-height: 1.6;
    min-height: 100vh;
  }

  /* ── Layout ─────────────────────────────────────────────── */
  .shell { max-width: 1320px; margin: 0 auto; padding: 0 24px; }

  /* ── Header ─────────────────────────────────────────────── */
  header {
    border-bottom: 1px solid var(--border);
    padding: 18px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }
  .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: var(--font-mono);
    font-size: 15px;
    font-weight: 600;
    letter-spacing: .04em;
    color: var(--accent);
  }
  .logo svg { flex-shrink: 0; }
  .header-meta {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--muted);
  }

  /* ── Stat cards ─────────────────────────────────────────── */
  .stat-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin: 24px 0;
  }
  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .stat-card .label {
    font-size: 11px;
    font-family: var(--font-mono);
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
  }
  .stat-card .value {
    font-size: 28px;
    font-family: var(--font-mono);
    font-weight: 600;
  }
  .stat-card.total   .value { color: var(--text); }
  .stat-card.pending .value { color: var(--pending); }
  .stat-card.process .value { color: var(--processing); }
  .stat-card.done    .value { color: var(--done); }
  .stat-card.error   .value { color: var(--error); }

  /* ── Toolbar ─────────────────────────────────────────────── */
  .toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }
  .search-wrap { position: relative; flex: 1; min-width: 220px; max-width: 360px; }
  .search-wrap input {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-family: var(--font-sans);
    font-size: 13px;
    padding: 8px 12px 8px 34px;
    outline: none;
    transition: border-color .15s;
  }
  .search-wrap input:focus { border-color: var(--accent); }
  .search-wrap .ico {
    position: absolute;
    left: 10px; top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    pointer-events: none;
  }
  .filter-tabs { display: flex; gap: 6px; }
  .filter-tabs a {
    font-family: var(--font-mono);
    font-size: 11px;
    padding: 6px 12px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    color: var(--muted);
    text-decoration: none;
    letter-spacing: .04em;
    transition: all .15s;
  }
  .filter-tabs a:hover    { border-color: var(--accent); color: var(--accent); }
  .filter-tabs a.active   { background: var(--accent); border-color: var(--accent); color: #fff; }
  .btn-refresh {
    margin-left: auto;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    border-radius: var(--radius);
    padding: 7px 14px;
    font-family: var(--font-mono);
    font-size: 11px;
    cursor: pointer;
    text-decoration: none;
    letter-spacing: .04em;
    transition: all .15s;
  }
  .btn-refresh:hover { border-color: var(--accent2); color: var(--accent2); }

  /* ── Table ───────────────────────────────────────────────── */
  .table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    overflow-x: auto;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    min-width: 860px;
  }
  thead th {
    background: var(--surface2);
    padding: 11px 16px;
    text-align: left;
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--muted);
    white-space: nowrap;
    border-bottom: 1px solid var(--border);
  }
  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .1s;
  }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--surface2); }
  td {
    padding: 11px 16px;
    vertical-align: middle;
    font-size: 13px;
  }
  td.mono { font-family: var(--font-mono); font-size: 12px; }
  td.dim  { color: var(--muted); }

  /* Thumbnail */
  .thumb-wrap { width: 48px; height: 48px; border-radius: 4px; overflow: hidden;
                border: 1px solid var(--border); flex-shrink: 0; }
  .thumb-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .thumb-placeholder {
    width: 48px; height: 48px; border-radius: 4px;
    background: var(--surface2); border: 1px dashed var(--border);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); font-size: 18px;
  }

  /* Badges */
  .badge {
    display: inline-block;
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 3px;
    white-space: nowrap;
  }
  .badge-pending    { background: rgba(245,158,11,.12); color: var(--pending);    border: 1px solid rgba(245,158,11,.3); }
  .badge-processing { background: rgba(129,140,248,.12); color: var(--processing); border: 1px solid rgba(129,140,248,.3); }
  .badge-done       { background: rgba(52,211,153,.12); color: var(--done);       border: 1px solid rgba(52,211,153,.3); }
  .badge-error      { background: rgba(248,113,113,.12); color: var(--error);     border: 1px solid rgba(248,113,113,.3); }

  /* Confidence bar */
  .conf-wrap { display: flex; align-items: center; gap: 8px; }
  .conf-bar  { flex: 1; height: 4px; background: var(--border); border-radius: 2px; min-width: 60px; }
  .conf-fill { height: 100%; border-radius: 2px; background: var(--accent2); }
  .conf-val  { font-family: var(--font-mono); font-size: 11px; color: var(--muted); white-space: nowrap; }

  /* Empty state */
  .empty {
    text-align: center;
    padding: 60px 24px;
    color: var(--muted);
    font-family: var(--font-mono);
    font-size: 13px;
  }
  .empty .icon { font-size: 32px; margin-bottom: 12px; }

  /* ── Pagination ──────────────────────────────────────────── */
  .pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 16px;
    color: var(--muted);
    font-family: var(--font-mono);
    font-size: 11px;
    flex-wrap: wrap;
    gap: 8px;
  }
  .pagination-links { display: flex; gap: 6px; }
  .pagination-links a, .pagination-links span {
    padding: 5px 10px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    text-decoration: none;
    color: var(--muted);
    transition: all .15s;
  }
  .pagination-links a:hover   { border-color: var(--accent); color: var(--accent); }
  .pagination-links .current  { background: var(--accent); border-color: var(--accent); color: #fff; }

  footer {
    border-top: 1px solid var(--border);
    margin-top: 40px;
    padding: 18px 0;
    text-align: center;
    color: var(--muted);
    font-family: var(--font-mono);
    font-size: 10px;
    letter-spacing: .06em;
  }

  /* Auto-refresh indicator */
  #countdown { color: var(--accent2); }
</style>
</head>
<body>

<div class="shell">

  <!-- Header -->
  <header>
    <div class="logo">
      <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
        <rect x="1" y="1" width="20" height="20" rx="3" stroke="#4f8ef7" stroke-width="1.5"/>
        <path d="M5 7h12M5 11h8M5 15h10" stroke="#4f8ef7" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      DOCSCAN / DASHBOARD
    </div>
    <div class="header-meta">
      Auto-refresh in <span id="countdown">30</span>s &nbsp;·&nbsp;
      <?= date('D, d M Y H:i:s') ?>
    </div>
  </header>

  <!-- Stat Cards -->
  <div class="stat-row">
    <div class="stat-card total">
      <span class="label">Total</span>
      <span class="value"><?= $total ?></span>
    </div>
    <div class="stat-card pending">
      <span class="label">Pending</span>
      <span class="value"><?= $stats['pending'] ?? 0 ?></span>
    </div>
    <div class="stat-card process">
      <span class="label">Processing</span>
      <span class="value"><?= $stats['processing'] ?? 0 ?></span>
    </div>
    <div class="stat-card done">
      <span class="label">Done</span>
      <span class="value"><?= $stats['done'] ?? 0 ?></span>
    </div>
    <div class="stat-card error">
      <span class="label">Error</span>
      <span class="value"><?= $stats['error'] ?? 0 ?></span>
    </div>
  </div>

  <!-- Toolbar -->
  <?php
    $baseQ = http_build_query(array_filter(['q' => $search, 'status' => $filterStatus]));
    function filterUrl(string $status, string $search): string {
        return '?' . http_build_query(array_filter(['status' => $status, 'q' => $search]));
    }
  ?>
  <div class="toolbar">
    <form method="get" class="search-wrap" style="margin:0">
      <?php if ($filterStatus): ?>
        <input type="hidden" name="status" value="<?= esc($filterStatus) ?>">
      <?php endif; ?>
      <svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
      <input type="text" name="q" value="<?= esc($search) ?>" placeholder="Search name, ID, section…">
    </form>

    <div class="filter-tabs">
      <a href="<?= filterUrl('', $search) ?>"            class="<?= $filterStatus===''          ?'active':'' ?>">ALL</a>
      <a href="<?= filterUrl('pending', $search) ?>"     class="<?= $filterStatus==='pending'    ?'active':'' ?>">PENDING</a>
      <a href="<?= filterUrl('processing', $search) ?>"  class="<?= $filterStatus==='processing' ?'active':'' ?>">PROCESSING</a>
      <a href="<?= filterUrl('done', $search) ?>"        class="<?= $filterStatus==='done'       ?'active':'' ?>">DONE</a>
      <a href="<?= filterUrl('error', $search) ?>"       class="<?= $filterStatus==='error'      ?'active':'' ?>">ERROR</a>
    </div>

    <a href="?" class="btn-refresh">↺ Refresh</a>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Preview</th>
          <th>Student Name</th>
          <th>Student ID</th>
          <th>Section</th>
          <th>Status</th>
          <th>Confidence</th>
          <th>Device</th>
          <th>Uploaded</th>
          <th>Processed</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="10">
          <div class="empty">
            <div class="icon">📭</div>
            No submissions found.
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono dim"><?= $r['id'] ?></td>
          <td>
            <?php if ($r['image_path'] && file_exists(__DIR__ . '/' . $r['image_path'])): ?>
              <div class="thumb-wrap">
                <img src="<?= esc($r['image_path']) ?>" alt="submission">
              </div>
            <?php else: ?>
              <div class="thumb-placeholder">📄</div>
            <?php endif; ?>
          </td>
          <td><?= esc($r['student_name']) ?></td>
          <td class="mono"><?= esc($r['student_id']) ?></td>
          <td><?= esc($r['section']) ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td>
            <?php if ($r['ai_confidence'] !== null): ?>
              <div class="conf-wrap">
                <div class="conf-bar">
                  <div class="conf-fill" style="width:<?= min(100, (float)$r['ai_confidence']) ?>%"></div>
                </div>
                <span class="conf-val"><?= number_format($r['ai_confidence'], 1) ?>%</span>
              </div>
            <?php else: ?>
              <span class="dim">—</span>
            <?php endif; ?>
          </td>
          <td class="mono dim"><?= esc($r['device_id']) ?></td>
          <td class="mono dim" style="font-size:11px"><?= esc($r['uploaded_at']) ?></td>
          <td class="mono dim" style="font-size:11px"><?= esc($r['processed_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="pagination">
    <span>Showing <?= count($rows) ?> of <?= $total ?> results</span>
    <div class="pagination-links">
      <?php
      $pBase = array_filter(['q' => $search, 'status' => $filterStatus]);
      for ($p = 1; $p <= $pages; $p++):
        $url = '?' . http_build_query($pBase + ['page' => $p]);
        if ($p === $page): ?>
          <span class="current"><?= $p ?></span>
        <?php else: ?>
          <a href="<?= esc($url) ?>"><?= $p ?></a>
        <?php endif;
      endfor; ?>
    </div>
  </div>

  <footer>DOCSCAN PIPELINE — PLAIN PHP + MYSQL &nbsp;·&nbsp; <?= date('Y') ?></footer>

</div><!-- /shell -->

<script>
  // Auto-refresh countdown
  let secs = 30;
  const el = document.getElementById('countdown');
  setInterval(() => {
    secs--;
    if (el) el.textContent = secs;
    if (secs <= 0) location.reload();
  }, 1000);
</script>
</body>
</html>