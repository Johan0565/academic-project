<?php
require_login();
$id_student = (int)$_SESSION['id_student'];

$sql = "
SELECT
  si.code_module, si.code_presentation,
  c.module_presentation_length,
  si.final_result
FROM studentinfo si
JOIN courses c
  ON c.code_module = si.code_module
 AND c.code_presentation = si.code_presentation
WHERE si.id_student = ?
ORDER BY si.code_module, si.code_presentation
";
$stmt = db()->prepare($sql);
$stmt->execute([$id_student]);
$courses = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>ЛК студента</title></head>
<body>
  <p>
    Вы вошли как студент: <b><?= $id_student ?></b> |
    <a href="index.php?page=logout">Выйти</a>
  </p>

  <h1>Мои курсы</h1>

  <?php if (!$courses): ?>
    <p>Курсы не найдены.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($courses as $c): ?>
        <li>
          <a href="index.php?page=course&module=<?= urlencode($c['code_module']) ?>&presentation=<?= urlencode($c['code_presentation']) ?>">
            <?= htmlspecialchars($c['code_module']) ?> / <?= htmlspecialchars($c['code_presentation']) ?>
          </a>
          — итог: <?= htmlspecialchars($c['final_result']) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</body>
</html>
