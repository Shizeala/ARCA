<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';

require_role('admin');

$errors = [];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'add_device') {
        $did  = trim($_POST['device_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $tid  = $_POST['assigned_teacher'] ? (int)$_POST['assigned_teacher'] : null;
        if (!$did || !$name) { $errors[] = 'Device ID and Name are required.'; }
        else {
            try {
                execute('INSERT INTO devices (device_id,name,assigned_teacher) VALUES (?,?,?)', [$did,$name,$tid]);
                flash('success', "Device \"$name\" registered.");
                header('Location: devices.php'); exit;
            } catch (PDOException $e) { $errors[] = 'DB: ' . $e->getMessage(); }
        }
    } elseif ($act === 'assign') {
        $did = $_POST['device_id'];
        $tid = $_POST['teacher_id'] ? (int)$_POST['teacher_id'] : null;
        execute('UPDATE devices SET assigned_teacher=? WHERE device_id=?', [$tid,$did]);
        flash('success', 'Device assignment updated.');
        header('Location: devices.php'); exit;
    } elseif ($act === 'delete_device') {
        $did = $_POST['device_id'];
        execute('DELETE FROM devices WHERE device_id=?', [$did]);
        flash('success', 'Device removed.');
        header('Location: devices.php'); exit;
    }
}

$devices  = fetch_all(
    'SELECT d.*, u.full_name AS teacher_name FROM devices d
     LEFT JOIN users u ON u.id=d.assigned_teacher ORDER BY d.name'
);
$teachers = fetch_all('SELECT id, full_name FROM users WHERE role="teacher" ORDER BY full_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Devices — ARCA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/arca.css">
<link rel="icon" href="<?= BASE_URL ?>/ARCA.svg" type="image/x-icon">
</head>
<body>
<div class="app-shell">
<?php layout_sidebar('admin','devices'); ?>
<?php layout_topbar('Devices','Scanner Management'); ?>

<?php render_flash(); ?>
<?php foreach($errors as $e): ?><div class="alert alert-danger"><?= esc($e) ?></div><?php endforeach; ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px">
  <button class="btn btn-primary" onclick="openModal('add-device-modal')">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Register Device
  </button>
</div>

<div class="card animate-in">
  <div class="card-header"><div class="card-title">Registered Devices</div><span style="font-size:12px;color:var(--text-soft);font-family:var(--font-data)"><?= count($devices) ?> device(s)</span></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Device ID</th><th>Name</th><th>Assigned Teacher</th><th>Registered</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($devices as $d): ?>
        <tr>
          <td class="mono"><?= esc($d['device_id']) ?></td>
          <td style="font-weight:500"><?= esc($d['name']) ?></td>
          <td>
            <?php if ($d['teacher_name']): ?>
              <span class="badge badge-teacher"><?= esc($d['teacher_name']) ?></span>
            <?php else: ?>
              <span style="color:var(--slate-lt);font-size:12px">Unassigned</span>
            <?php endif; ?>
          </td>
          <td class="mono" style="font-size:11px;color:var(--text-soft)"><?= esc(substr($d['created_at'],0,10)) ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn btn-secondary btn-sm" onclick="openAssignModal('<?= esc($d['device_id']) ?>','<?= esc($d['name']) ?>',<?= $d['assigned_teacher'] ?? 'null' ?>)">Assign</button>
              <button class="btn btn-danger btn-sm" onclick="confirmDeleteDevice('<?= esc($d['device_id']) ?>','<?= esc($d['name']) ?>')">Remove</button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($devices)): ?><tr><td colspan="5"><div class="empty-state"><div class="icon">📷</div><p>No devices registered.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Device Modal -->
<div class="modal-overlay" id="add-device-modal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Register Device</div><button class="modal-close" onclick="closeModal('add-device-modal')">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_device">
      <div class="modal-body">
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Device ID *</label><input class="form-control" name="device_id" required placeholder="e.g. device-lab-01"></div>
          <div class="form-group"><label class="form-label">Display Name *</label><input class="form-control" name="name" required placeholder="e.g. Lab Scanner 1"></div>
        </div>
        <div class="form-group"><label class="form-label">Assign to Teacher</label>
          <select class="form-control" name="assigned_teacher">
            <option value="">— Unassigned —</option>
            <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['full_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-device-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Register</button>
      </div>
    </form>
  </div>
</div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assign-modal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Assign Device — <span id="assign-device-name"></span></div><button class="modal-close" onclick="closeModal('assign-modal')">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="device_id" id="assign-device-id">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Teacher</label>
          <select class="form-control" name="teacher_id" id="assign-teacher-sel">
            <option value="">— Unassigned —</option>
            <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['full_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('assign-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Assignment</button>
      </div>
    </form>
  </div>
</div>

<form method="post" id="del-device-form" style="display:none">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete_device"><input type="hidden" name="device_id" id="del-device-id">
</form>

<?php layout_close(); ?>
<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openAssignModal(did, dname, currentTeacher) {
  document.getElementById('assign-device-id').value = did;
  document.getElementById('assign-device-name').textContent = dname;
  const sel = document.getElementById('assign-teacher-sel');
  sel.value = currentTeacher || '';
  openModal('assign-modal');
}
function confirmDeleteDevice(did, name) {
  if (!confirm('Remove device "' + name + '"?')) return;
  document.getElementById('del-device-id').value = did;
  document.getElementById('del-device-form').submit();
}
<?php if($action==='add'): ?>openModal('add-device-modal');<?php endif; ?>
</script>
</body>
</html>