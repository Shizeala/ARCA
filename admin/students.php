<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';

require_role('admin');

$action = $_GET['action'] ?? '';
$errors = [];
$msg    = '';

// ── Handle POST actions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $act = $_POST['action'] ?? '';

  if ($act === 'add' || $act === 'edit') {
    $sid  = trim($_POST['student_id'] ?? '');
    $fn   = trim($_POST['first_name'] ?? '');
    $ln   = trim($_POST['last_name'] ?? '');
    $mi   = strtoupper(trim($_POST['middle_initial'] ?? ''));
    $sex  = $_POST['sex'] ?? 'M';
    $sec  = trim($_POST['section'] ?? '');
    $em   = trim($_POST['email'] ?? '');
    $uname = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if (!$sid || !$fn || !$ln || !$sec || !$em) $errors[] = 'All required fields must be filled.';
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

    if (empty($errors)) {
      if ($act === 'add') {
        try {
          execute('INSERT INTO students (student_id,first_name,last_name,middle_initial,sex,section,email)
                             VALUES (?,?,?,?,?,?,?)', [$sid, $fn, $ln, $mi, $sex, $sec, $em]);
          // Create linked user account
          if ($uname && $pass) {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            execute(
              'INSERT INTO users (username,password_hash,role,student_id,section,email,full_name)
                                 VALUES (?,?,?,?,?,?,?)',
              [$uname, $hash, 'student', $sid, $sec, $em, "$fn $ln"]
            );
          }
          flash('success', "Student $fn $ln added successfully.");
        } catch (PDOException $e) {
          $errors[] = 'DB error: ' . $e->getMessage();
        }
      } else {
        $origId = $_POST['orig_id'] ?? $sid;
        execute('UPDATE students SET student_id=?,first_name=?,last_name=?,middle_initial=?,sex=?,section=?,email=?
                         WHERE student_id=?', [$sid, $fn, $ln, $mi, $sex, $sec, $em, $origId]);
        flash('success', "Student updated.");
      }
      if (empty($errors)) {
        header('Location: students.php');
        exit;
      }
    }
  } elseif ($act === 'delete') {
    $sid = $_POST['student_id'] ?? '';
    execute('DELETE FROM submissions WHERE student_id=?', [$sid]);
    execute('DELETE FROM users WHERE student_id=?', [$sid]);
    execute('DELETE FROM students WHERE student_id=?', [$sid]);
    flash('success', 'Student deleted.');
    header('Location: students.php');
    exit;
  }
}

// ── Load data ─────────────────────────────────────────────────────
$q      = trim($_GET['q'] ?? '');
$where  = $q ? 'WHERE student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR section LIKE ?' : '';
$params = $q ? ["%$q%", "%$q%", "%$q%", "%$q%"] : [];
$students = fetch_all("SELECT * FROM students $where ORDER BY last_name,first_name", $params);

// Edit mode
$editStudent = null;
if ($action === 'edit' && isset($_GET['id'])) {
  $editStudent = fetch_one('SELECT * FROM students WHERE student_id=?', [$_GET['id']]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Students — ARCA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/arca.css">
  <link rel="icon" href="<?= BASE_URL ?>/ARCA.svg" type="image/x-icon">
</head>

<body>
  <div class="app-shell">
    <?php layout_sidebar('admin', 'students'); ?>
    <?php layout_topbar('Students', 'Manage Records'); ?>

    <?php render_flash(); ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= esc($e) ?></div><?php endforeach; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
      <form method="get" style="display:flex;gap:8px">
        <input class="form-control" style="width:260px" type="text" name="q" value="<?= esc($q) ?>" placeholder="Search students…">
        <button class="btn btn-secondary" type="submit">Search</button>
        <?php if ($q): ?><a href="students.php" class="btn btn-secondary">Clear</a><?php endif; ?>
      </form>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Student
      </button>
    </div>

    <div class="card animate-in">
      <div class="card-header">
        <div class="card-title">Student Records</div>
        <span style="font-size:12px;color:var(--text-soft);font-family:var(--font-data)"><?= count($students) ?> record(s)</span>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Student ID</th>
              <th>Name</th>
              <th>Sex</th>
              <th>Section</th>
              <th>Email</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s): ?>
              <tr>
                <td class="mono"><?= esc($s['student_id']) ?></td>
                <td>
                  <div style="font-weight:500"><?= esc($s['last_name'] . ', ' . $s['first_name'] . ($s['middle_initial'] ? ' ' . $s['middle_initial'] . '.' : '')) ?></div>
                </td>
                <td><?= esc($s['sex']) ?></td>
                <td><?= esc($s['section']) ?></td>
                <td style="font-size:12px"><?= esc($s['email']) ?></td>
                <td>
                  <div style="display:flex;gap:6px">
                    <a href="?action=edit&id=<?= urlencode($s['student_id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <button class="btn btn-danger btn-sm" onclick="confirmDelete('<?= esc($s['student_id']) ?>','<?= esc($s['first_name'] . ' ' . $s['last_name']) ?>')">Delete</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?><tr>
                <td colspan="6">
                  <div class="empty-state">
                    <div class="icon">👤</div>
                    <p>No students found.</p>
                  </div>
                </td>
              </tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Modal -->
    <div class="modal-overlay" id="add-modal">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-title">Add New Student</div>
          <button class="modal-close" onclick="closeModal('add-modal')">×</button>
        </div>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="add">
          <div class="modal-body">
            <div class="form-row cols-2">
              <div class="form-group"><label class="form-label">Student ID *</label><input class="form-control" name="student_id" required placeholder="e.g. 2024-0001"></div>
              <div class="form-group"><label class="form-label">Section *</label><input class="form-control" name="section" required placeholder="e.g. BSCS-3A"></div>
            </div>
            <div class="form-row cols-3">
              <div class="form-group"><label class="form-label">First Name *</label><input class="form-control" name="first_name" required></div>
              <div class="form-group"><label class="form-label">Last Name *</label><input class="form-control" name="last_name" required></div>
              <div class="form-group"><label class="form-label">M.I.</label><input class="form-control" name="middle_initial" maxlength="1" style="width:60px"></div>
            </div>
            <div class="form-row cols-2">
              <div class="form-group"><label class="form-label">Sex *</label>
                <select class="form-control" name="sex">
                  <option value="M">Male</option>
                  <option value="F">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" required></div>
            </div>
            <div style="border-top:1px solid var(--line);padding-top:16px;margin-top:4px">
              <div style="font-size:12px;font-weight:600;margin-bottom:12px;color:var(--text-soft)">Create Login Account (optional)</div>
              <div class="form-row cols-2">
                <div class="form-group"><label class="form-label">Username</label><input class="form-control" name="username" placeholder="Leave blank to skip"></div>
                <div class="form-group"><label class="form-label">Password</label><input class="form-control" type="password" name="password"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Add Student</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Modal (auto-open if action=edit) -->
    <?php if ($editStudent): ?>
      <div class="modal-overlay open" id="edit-modal">
        <div class="modal">
          <div class="modal-header">
            <div class="modal-title">Edit Student</div>
            <a href="students.php" class="modal-close">×</a>
          </div>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="edit">
            <input type="hidden" name="orig_id" value="<?= esc($editStudent['student_id']) ?>">
            <div class="modal-body">
              <div class="form-row cols-2">
                <div class="form-group"><label class="form-label">Student ID *</label><input class="form-control" name="student_id" value="<?= esc($editStudent['student_id']) ?>" required></div>
                <div class="form-group"><label class="form-label">Section *</label><input class="form-control" name="section" value="<?= esc($editStudent['section']) ?>" required></div>
              </div>
              <div class="form-row cols-3">
                <div class="form-group"><label class="form-label">First Name *</label><input class="form-control" name="first_name" value="<?= esc($editStudent['first_name']) ?>" required></div>
                <div class="form-group"><label class="form-label">Last Name *</label><input class="form-control" name="last_name" value="<?= esc($editStudent['last_name']) ?>" required></div>
                <div class="form-group"><label class="form-label">M.I.</label><input class="form-control" name="middle_initial" value="<?= esc($editStudent['middle_initial']) ?>" maxlength="1" style="width:60px"></div>
              </div>
              <div class="form-row cols-2">
                <div class="form-group"><label class="form-label">Sex</label>
                  <select class="form-control" name="sex">
                    <?php foreach (['M', 'F', 'Other'] as $sx): ?><option value="<?= $sx ?>" <?= $editStudent['sex'] === $sx ? 'selected' : '' ?>><?= $sx === 'M' ? 'Male' : ($sx === 'F' ? 'Female' : 'Other') ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" value="<?= esc($editStudent['email']) ?>" required></div>
              </div>
            </div>
            <div class="modal-footer">
              <a href="students.php" class="btn btn-secondary">Cancel</a>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- Delete confirm form -->
    <form method="post" id="delete-form" style="display:none">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <input type="hidden" name="student_id" id="delete-sid">
    </form>

    <?php layout_close(); ?>
    <script>
      function openModal(id) {
        document.getElementById(id).classList.add('open');
      }

      function closeModal(id) {
        document.getElementById(id).classList.remove('open');
      }

      function confirmDelete(sid, name) {
        if (!confirm('Delete student "' + name + '"? This also removes their submissions and login account.')) return;
        document.getElementById('delete-sid').value = sid;
        document.getElementById('delete-form').submit();
      }
      // Auto-open add modal if URL has action=add
      <?php if ($action === 'add'): ?>openModal('add-modal');
      <?php endif; ?>
    </script>
</body>

</html>