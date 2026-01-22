<?php
require_admin();

$success = null;
$error = null;

$perPage = 50;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$status = $_GET['status'] ?? 'all';
$allowedStatus = ['all', 'approved', 'pending'];
if (!in_array($status, $allowedStatus, true)) $status = 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (function_exists('csrf_validate')) csrf_validate();

  $action = $_POST['action'] ?? '';

  if ($action === 'delete_by_student') {
    $idStudent = (int)($_POST['id_student'] ?? 0);
    if ($idStudent <= 0) {
      $error = "Введите корректный ID студента.";
    } else {
      $st = db()->prepare("DELETE FROM app_users WHERE id_student = ?");
      $st->execute([$idStudent]);
      $success = ($st->rowCount() > 0) ? "Пользователь с id_student=$idStudent удалён." : "Не найден пользователь с таким id_student.";
    }
  }

  if ($action === 'delete_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
      $error = "Некорректный user_id.";
    } else {
      $st = db()->prepare("DELETE FROM app_users WHERE id = ?");
      $st->execute([$userId]);
      $success = ($st->rowCount() > 0) ? "Пользователь удалён." : "Пользователь уже удалён.";
    }
  }
}

$where = "";
$params = [];
if ($status !== 'all') {
  $where = "WHERE status = ?";
  $params[] = $status;
}

$stCount = db()->prepare("SELECT COUNT(*) FROM app_users $where");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "
SELECT id, id_student, email, status, created_at, approved_at
FROM app_users
$where
ORDER BY created_at DESC
LIMIT :lim OFFSET :off
";
$st = db()->prepare($sql);
if ($status !== 'all') $st->bindValue(1, $status, PDO::PARAM_STR);
$st->bindValue(':lim', $perPage, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$users = $st->fetchAll();

$title = "Админ: пользователи";
require __DIR__ . '/_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Пользователи</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="/academic/public/admin/requests">Заявки</a>
    <a class="btn btn-outline-secondary btn-sm" href="/academic/public/logout">Выйти</a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <h2 class="h5">Удалить пользователя по ID студента</h2>
    <form method="post" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">ID студента (id_student)</label>
        <input class="form-control" name="id_student" type="number" min="1" required>
      </div>

      <div class="col-md-3">
        <input type="hidden" name="action" value="delete_by_student">
        <?php if (function_exists('csrf_token')): ?>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <?php endif; ?>
        <button class="btn btn-danger" type="submit" onclick="return confirm('Удалить пользователя?');">
          Удалить
        </button>
      </div>

      <div class="col-md-5 text-muted small">
        Удалится запись из <code>app_users</code> (email+пароль+статус). Студент сможет зарегистрироваться заново.
      </div>
    </form>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <div class="btn-group btn-group-sm">
    <a class="btn btn-outline-primary <?= $status==='all'?'active':'' ?>" href="/academic/public/admin/users?status=all">Все</a>
  </div>
  <div class="text-muted small">Всего: <?= $total ?> | Стр. <?= $page ?> / <?= $totalPages ?></div>
</div>

<div class="table-responsive">
  <table class="table table-sm table-striped align-middle">
    <thead>
      <tr>
        <th>user_id</th>
        <th>id_student</th>
        <th>email</th>
        <th>status</th>
        <th>created_at</th>
        <th>approved_at</th>
        <th class="text-end">действия</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$users): ?>
        <tr><td colspan="7" class="text-muted">Нет пользователей.</td></tr>
      <?php endif; ?>

      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= (int)$u['id_student'] ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?php if ($u['status'] === 'approved'): ?>
              <span class="badge bg-success">approved</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark">pending</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($u['created_at']) ?></td>
          <td><?= htmlspecialchars((string)$u['approved_at']) ?></td>
          <td class="text-end">
            <form method="post" class="d-inline" onsubmit="return confirm('Удалить этого пользователя?');">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <?php endif; ?>
              <button class="btn btn-danger btn-sm" type="submit">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<nav class="mt-2">
  <ul class="pagination pagination-sm">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="/academic/public/admin/users?status=<?= urlencode($status) ?>&p=<?= max(1, $page-1) ?>">‹</a>
    </li>
    <li class="page-item disabled">
      <span class="page-link">стр. <?= $page ?> / <?= $totalPages ?></span>
    </li>
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <a class="page-link" href="/academic/public/admin/users?status=<?= urlencode($status) ?>&p=<?= min($totalPages, $page+1) ?>">›</a>
    </li>
  </ul>
</nav>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
