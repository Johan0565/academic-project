<?php
require_admin();

$success = null;
$error = null;

// Обработка действий approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $action = $_POST['action'] ?? '';
  $userId = (int)($_POST['user_id'] ?? 0);

  if ($userId <= 0) {
    $error = "Некорректный user_id";
  } elseif (!in_array($action, ['approve','reject'], true)) {
    $error = "Некорректное действие";
  } else {
    if ($action === 'approve') {
      $st = db()->prepare("
        UPDATE app_users
        SET status = 'approved', approved_at = NOW()
        WHERE id = ? AND status = 'pending'
      ");
      $st->execute([$userId]);
      $success = ($st->rowCount() > 0) ? "Пользователь подтверждён." : "Заявка уже обработана.";
    }

    if ($action === 'reject') {
      $st = db()->prepare("DELETE FROM app_users WHERE id = ? AND status = 'pending'");
      $st->execute([$userId]);
      $success = ($st->rowCount() > 0) ? "Заявка отклонена и удалена." : "Заявка уже обработана.";
    }
  }
}

// Список pending
$st2 = db()->query("
  SELECT id, id_student, email, created_at
  FROM app_users
  WHERE status = 'pending'
  ORDER BY created_at DESC
");
$pending = $st2->fetchAll();

$title = "Админ: заявки";
require __DIR__ . '/_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Заявки на регистрацию</h1>
  <a class="btn btn-outline-secondary btn-sm" href="/academic/public/logout">Выйти</a>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$pending): ?>
  <div class="alert alert-info">Нет заявок (pending).</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th>id</th>
          <th>id_student</th>
          <th>email</th>
          <th>created_at</th>
          <th class="text-end">действия</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= (int)$u['id_student'] ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['created_at']) ?></td>
            <td class="text-end">
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button class="btn btn-success btn-sm" type="submit">Approve</button>
              </form>

              <form method="post" class="d-inline" onsubmit="return confirm('Отклонить заявку?');">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button class="btn btn-danger btn-sm" type="submit">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
