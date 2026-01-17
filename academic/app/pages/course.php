<?php
require_login();
$id_student = (int)$_SESSION['id_student'];

$module = $_GET['module'] ?? '';
$pres = $_GET['presentation'] ?? '';

if ($module === '' || $pres === '') {
  header('Location: index.php?page=me');
  exit;
}

// оценки
$sqlAssess = "
SELECT
  a.id_assessment,
  a.assessment_type,
  a.date_day,
  a.weight,
  sa.date_submitted,
  sa.score_num
FROM v_assessments a
LEFT JOIN v_studentassessment sa
  ON sa.id_assessment = a.id_assessment
 AND sa.id_student = ?
WHERE a.code_module = ?
  AND a.code_presentation = ?
ORDER BY a.date_day IS NULL, a.date_day
";
$st = db()->prepare($sqlAssess);
$st->execute([$id_student, $module, $pres]);
$assessments = $st->fetchAll();

// активность по типам
$sqlAct = "
SELECT
  v.activity_type,
  SUM(sv.clicks) AS clicks
FROM v_studentvle_agg sv
JOIN v_vle v
  ON v.id_site = sv.id_site
 AND v.code_module = sv.code_module
 AND v.code_presentation = sv.code_presentation
WHERE sv.code_module = ?
  AND sv.code_presentation = ?
  AND sv.id_student = ?
GROUP BY v.activity_type
ORDER BY clicks DESC
";
$st2 = db()->prepare($sqlAct);
$st2->execute([$module, $pres, $id_student]);
$activity = $st2->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Курс</title></head>
<body>
  <p><a href="index.php?page=me">← назад</a></p>
  <h1>Курс <?= htmlspecialchars($module) ?> / <?= htmlspecialchars($pres) ?></h1>

  <h2>Оценивания</h2>
  <table border="1" cellpadding="6">
    <tr><th>id</th><th>type</th><th>date_day</th><th>weight</th><th>submitted</th><th>score</th></tr>
    <?php foreach ($assessments as $a): ?>
      <tr>
        <td><?= (int)$a['id_assessment'] ?></td>
        <td><?= htmlspecialchars($a['assessment_type']) ?></td>
        <td><?= htmlspecialchars((string)$a['date_day']) ?></td>
        <td><?= htmlspecialchars((string)$a['weight']) ?></td>
        <td><?= htmlspecialchars((string)$a['date_submitted']) ?></td>
        <td><?= htmlspecialchars((string)$a['score_num']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Активность по типам (VLE)</h2>
  <table border="1" cellpadding="6">
    <tr><th>activity_type</th><th>clicks</th></tr>
    <?php foreach ($activity as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['activity_type']) ?></td>
        <td><?= htmlspecialchars((string)$r['clicks']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
