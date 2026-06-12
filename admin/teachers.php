<?php
// admin/teachers.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';

require_role('admin');

$action = $_GET['action'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'add_teacher') {
        $uname = trim($_POST['username'] ?? '');
        $fname = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = trim($_POST['password'] ?? '');
        $sec   = trim($_POST['section'] ?? '');

        if (!$uname || !$fname || !$email || !$pass) $errors[] = 'All fields required.';
        if ($pass && strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

        if (empty($errors)) {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
                execute('INSERT INTO users (username,password_hash,role,section,email,full_name)
                         VALUES (?,?,?,?,?,?)', [$uname,$hash,'teacher',$sec,$email,$fname]);
                flash('success', "Teacher account created for $fname.");
                header('Location: teachers.php'); exit;
            } catch (PDOException $e) {
                $errors[] = 'DB error: ' . $e->getMessage();
            }
        }
    } elseif ($act === 'delete_teacher') {
        $id = (int)$_POST['user_id'];
        execute('UPDATE devices SET assigned_teacher=NULL WHERE assigned_teacher=?',[$id]);
        execute('DELETE FROM users WHERE id=? AND role="teacher"',[$id]);
        flash('success','Teacher deleted.');
        header('Location: teachers.php'); exit;
    } elseif ($act === 'reset_pass') {
        $id   = (int)$_POST['user_id'];
        $pass = trim($_POST['new_password'] ?? '');
        if (strlen($pass) < 6) { $errors[] = 'Password min 6 chars.'; }
        else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
            execute('UPDATE users SET password_hash=? WHERE id=?',[$hash,$id]);
            flash('success','Password updated.');
            header('Location: teachers.php'); exit;
        }
    }
}

$teachers = fetch_all(
    'SELECT u.*, COUNT(d.device_id) AS device_count
     FROM users u LEFT JOIN devices d ON d.assigned_teacher=u.id
     WHERE u.role="teacher" GROUP BY u.id ORDER BY u.full_name'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Teachers — ARCA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/arca.css">
<link rel="icon" href="<?= BASE_URL ?>/ARCA.svg" type="image/x-icon">
</head>
<body>
<div class="app-shell">
<?php layout_sidebar('admin','teachers'); ?>
<?php layout_topbar('Teachers','Manage Accounts'); ?>

<?php render_flash(); ?>
<?php foreach($errors as $e): ?><div class="alert alert-danger"><?= esc($e) ?></div><?php endforeach; ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px">
  <button class="btn btn-primary" onclick="openModal('add-teacher-modal')">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Teacher
  </button>
</div>

<div class="card animate-in">
  <div class="card-header"><div class="card-title">Teacher Accounts</div><span style="font-size:12px;color:var(--text-soft);font-family:var(--font-data)"><?= count($teachers) ?> teacher(s)</span></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Section</th><th>Devices</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($teachers as $t): ?>
        <tr>
          <td class="mono"><?= $t['id'] ?></td>
          <td style="font-weight:500"><?= esc($t['full_name']) ?></td>
          <td class="mono"><?= esc($t['username']) ?></td>
          <td><?= esc($t['email']) ?></td>
          <td><?= esc($t['section'] ?? '—') ?></td>
          <td class="mono"><?= $t['device_count'] ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn btn-secondary btn-sm" onclick="openResetModal(<?= $t['id'] ?>, '<?= esc($t['full_name']) ?>')">Reset PW</button>
              <button class="btn btn-danger btn-sm" onclick="confirmDeleteTeacher(<?= $t['id'] ?>, '<?= esc($t['full_name']) ?>')">Delete</button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($teachers)): ?><tr><td colspan="7"><div class="empty-state"><div class="icon">👩‍🏫</div><p>No teachers yet.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal-overlay" id="add-teacher-modal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add Teacher Account</div><button class="modal-close" onclick="closeModal('add-teacher-modal')">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_teacher">
      <div class="modal-body">
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Full Name *</label><input class="form-control" name="full_name" required></div>
          <div class="form-group"><label class="form-label">Username *</label><input class="form-control" name="username" required></div>
        </div>
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" required></div>
          <div class="form-group"><label class="form-label">Section</label><input class="form-control" name="section" placeholder="Optional"></div>
        </div>
        <div class="form-group"><label class="form-label">Password *</label><input class="form-control" type="password" name="password" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-teacher-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Account</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="reset-modal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Reset Password — <span id="reset-name"></span></div><button class="modal-close" onclick="closeModal('reset-modal')">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="reset_pass"><input type="hidden" name="user_id" id="reset-uid">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">New Password (min 6 chars) *</label><input class="form-control" type="password" name="new_password" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reset-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete form -->
<form method="post" id="del-teacher-form" style="display:none">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete_teacher"><input type="hidden" name="user_id" id="del-teacher-id">
</form>

<?php layout_close(); ?>
<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openResetModal(uid, name) {
  document.getElementById('reset-uid').value = uid;
  document.getElementById('reset-name').textContent = name;
  openModal('reset-modal');
}
function confirmDeleteTeacher(id, name) {
  if (!confirm('Delete teacher "' + name + '"? Their devices will be unassigned.')) return;
  document.getElementById('del-teacher-id').value = id;
  document.getElementById('del-teacher-form').submit();
}
<?php if($action==='add'): ?>openModal('add-teacher-modal');<?php endif; ?>
</script>
</body>
</html>