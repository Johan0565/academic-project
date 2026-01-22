<?php
session_start();
if (isset($_SESSION['role'])) {
  if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /academic/public/admin/requests');
  } else {
    header('Location: /academic/public/me');
  }
  exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  // 1) вход админа (жестко в коде)
  if ($email === 'admin@admin' && $password === 'admin') {
    login_admin();
    header('Location: /academic/public/admin/requests');
    exit;
  }

  // 2) вход студента по email+пароль только если approved
  $stmt = db()->prepare("
    SELECT id, id_student, email, password_hash, status
    FROM app_users
    WHERE email = ?
    LIMIT 1
  ");
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if (!$user) {
    $error = "Неверный email или пароль.";
  } elseif ($user['status'] !== 'approved') {
    $error = "Аккаунт еще не подтвержден администратором.";
  } elseif (!password_verify($password, $user['password_hash'])) {
    $error = "Неверный email или пароль.";
  } else {
    login_user($user);
    header('Location: /academic/public/me');
    exit;
  }
}

$title = "Вход";
require __DIR__ . '/_layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-4">
    <h1 class="h3 mb-3">Вход</h1>

    <form method="post" class="card card-body">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label class="form-label">Email</label>
      <input class="form-control" name="email" type="email" required>

      <label class="form-label mt-3">Пароль</label>
      <input class="form-control" name="password" type="password" required>

      <button class="btn btn-primary mt-3" type="submit">Войти</button>
    </form>

    <?php if ($error): ?>
      <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <p class="mt-3">
      Нет аккаунта? <a href="/academic/public/register">Зарегистрироваться</a>
    </p>

    <div class="text-muted small">
      Админ: <code>admin</code> / <code>admin</code>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
