<?php
require_student();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  http_response_code(403);
  echo "Нет user_id в сессии";
  exit;
}

$success = null;
$error = null;

// загрузим данные пользователя
$st = db()->prepare("SELECT id, id_student, email, password_hash, status FROM app_users WHERE id = ? LIMIT 1");
$st->execute([$userId]);
$user = $st->fetch();

if (!$user) {
  http_response_code(404);
  echo "Пользователь не найден";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (function_exists('csrf_validate')) csrf_validate();

  $current = (string)($_POST['current_password'] ?? '');
  $new1 = (string)($_POST['new_password'] ?? '');
  $new2 = (string)($_POST['new_password2'] ?? '');

  if (!password_verify($current, $user['password_hash'])) {
    $error = "Текущий пароль неверный.";
  } elseif (mb_strlen($new1) < 6) {
    $error = "Новый пароль должен быть минимум 6 символов.";
  } elseif ($new1 !== $new2) {
    $error = "Новые пароли не совпадают.";
  } else {
    $hash = password_hash($new1, PASSWORD_DEFAULT);
    $upd = db()->prepare("UPDATE app_users SET password_hash = ? WHERE id = ?");
    $upd->execute([$hash, $userId]);
    $success = "Пароль успешно обновлён.";
    $user['password_hash'] = $hash;
  }
}

$title = "Аккаунт";
require __DIR__ . '/_layout_top.php';
?>

<h1 class="h3 mb-3">Аккаунт</h1>

<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">Данные аккаунта</h2>
        <ul class="list-unstyled mb-0">
          <li><b>Email:</b> <?= htmlspecialchars($user['email']) ?></li>
          <li><b>ID студента:</b> <?= (int)$user['id_student'] ?></li>
          <li><b>Статус:</b> <?= htmlspecialchars($user['status']) ?></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">Смена пароля</h2>
        <form method="post">
          <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <?php endif; ?>

          <label class="form-label">Текущий пароль</label>
          <input class="form-control" type="password" name="current_password" required>

          <label class="form-label mt-3">Новый пароль</label>
          <input class="form-control" type="password" name="new_password" required>

          <label class="form-label mt-3">Повтор нового пароля</label>
          <input class="form-control" type="password" name="new_password2" required>

          <button class="btn btn-primary mt-3" type="submit">Сохранить</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
