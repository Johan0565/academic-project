<?php
session_start();
if (isset($_SESSION['id_student'])) {
  header('Location: /academic/public/me');
  exit;
}

$title = "Регистрация";

$perPage = 20;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$errors = [];
$success = null;

$sqlCount = "
SELECT COUNT(DISTINCT si.id_student) AS total
FROM studentinfo si
LEFT JOIN app_users au ON au.id_student = si.id_student
WHERE au.id_student IS NULL
";
$total = (int)db()->query($sqlCount)->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sqlList = "
SELECT DISTINCT si.id_student
FROM studentinfo si
LEFT JOIN app_users au ON au.id_student = si.id_student
WHERE au.id_student IS NULL
ORDER BY si.id_student
LIMIT :lim OFFSET :off
";
$stmt = db()->prepare($sqlList);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $id_student = (int)($_POST['id_student'] ?? 0);
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($id_student <= 0) $errors[] = "Выберите id_student.";
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Введите корректный email.";
  if (mb_strlen($password) < 6) $errors[] = "Пароль должен быть минимум 6 символов.";

  if (!$errors) {
    $st1 = db()->prepare("SELECT 1 FROM studentinfo WHERE id_student = ? LIMIT 1");
    $st1->execute([$id_student]);
    if (!$st1->fetchColumn()) $errors[] = "Выбранный id_student не найден в studentinfo.";

    $st2 = db()->prepare("SELECT 1 FROM app_users WHERE id_student = ? LIMIT 1");
    $st2->execute([$id_student]);
    if ($st2->fetchColumn()) $errors[] = "Этот id_student уже зарегистрирован.";

    $st3 = db()->prepare("SELECT 1 FROM app_users WHERE email = ? LIMIT 1");
    $st3->execute([$email]);
    if ($st3->fetchColumn()) $errors[] = "Этот email уже используется.";

    if (!$errors) {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      $ins = db()->prepare("
        INSERT INTO app_users (id_student, email, password_hash, status)
        VALUES (?, ?, ?, 'pending')
      ");
      $ins->execute([$id_student, $email, $hash]);

      $success = "Заявка создана и отправлена на подтверждение администратору.";
    }
  }
}

require __DIR__ . '/_layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <h1 class="h3 mb-3">Регистрация</h1>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <a class="btn btn-primary" href="/academic/public/login">Перейти ко входу</a>
      <?php require __DIR__ . '/_layout_bottom.php'; exit; ?>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="card card-body">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  
      <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">ID студента</label>
            <input
            class="form-control"
            name="id_student"
            id="idStudent"
            type="text"
            inputmode="numeric"
            pattern="\d*"
            list="studentOptions"
            value="<?= htmlspecialchars((string)($_POST['id_student'] ?? '')) ?>"
            required
        >
        <datalist id="studentOptions"></datalist>
<div class="form-text">Начните вводить ID — появятся подсказки.</div>

        </div>

        <div class="col-md-8">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" type="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" required>

          <label class="form-label mt-3">Пароль</label>
          <input class="form-control" name="password" type="password" required>

          <button class="btn btn-success mt-4" type="submit" <?= !$students ? 'disabled' : '' ?>>
            Отправить на подтверждение
          </button>

          <div class="mt-3">
            Уже есть аккаунт? <a href="/academic/public/login">Войти</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
