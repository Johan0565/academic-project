<?php
// если уже залогинен
session_start();
if (isset($_SESSION['id_student'])) {
  header('Location: index.php?page=me');
  exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id_student'] ?? 0);

  if ($id > 0) {
    // проверим что студент существует
    $stmt = db()->prepare("SELECT 1 FROM studentinfo WHERE id_student = ? LIMIT 1");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn()) {
      login_student($id);
      header('Location: index.php?page=me');
      exit;
    } else {
      $error = "Такого id_student нет в базе";
    }
  } else {
    $error = "Введите корректный id_student";
  }
}
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Вход</title></head>
<body>
  <h1>Вход (демо)</h1>
  <form method="post">
    <label>ID студента: <input name="id_student" type="number" required></label>
    <button type="submit">Войти</button>
  </form>
  <?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
</body>
</html>
