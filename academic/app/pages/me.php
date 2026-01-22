<?php
require_student();
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

$title = "Мои курсы";
require __DIR__ . '/_layout_top.php';
?>

<h1 class="h3 mb-3">Мои курсы</h1>

<?php if (!$courses): ?>
  <div class="alert alert-warning">Курсы не найдены.</div>
<?php else: ?>
  <div class="list-group">
    <?php foreach ($courses as $c): ?>
      <a class="list-group-item list-group-item-action"
         href="/academic/public/me/course?module=<?= urlencode($c['code_module']) ?>&presentation=<?= urlencode($c['code_presentation']) ?>">
        <div class="d-flex justify-content-between">
          <div>
            <b><?= htmlspecialchars($c['code_module']) ?> / <?= htmlspecialchars($c['code_presentation']) ?></b>
            <div class="text-muted small">Длина: <?= (int)$c['module_presentation_length'] ?> дней</div>
          </div>
          <div class="text-end">
            <span class="badge bg-secondary"><?= htmlspecialchars($c['final_result']) ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
