<?php
session_start();
if (isset($_SESSION['id_student'])) {
  header('Location: /academic/public/me');
  exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id_student'] ?? 0);

  if ($id > 0) {
    $stmt = db()->prepare("SELECT 1 FROM studentinfo WHERE id_student = ? LIMIT 1");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn()) {
      login_student($id);
      header('Location: /academic/public/me');
      exit;
    } else $error = "Такого id_student нет в базе";
  } else $error = "Введите корректный id_student";
}

$title = "Вход";
require __DIR__ . '/_layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-4">
    <h1 class="h3 mb-3">Вход (демо)</h1>

    <form method="post" class="card card-body">
      <label class="form-label">ID студента</label>
      <input class="form-control" name="id_student" type="number" required>
      <button class="btn btn-primary mt-3" type="submit">Войти</button>
    </form>

    <?php if ($error): ?>
      <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
