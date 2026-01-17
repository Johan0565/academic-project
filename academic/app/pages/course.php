<?php
require_login();
$id_student = (int)$_SESSION['id_student'];

$module = $_GET['module'] ?? '';
$pres = $_GET['presentation'] ?? '';
if ($module === '' || $pres === '') {
  header('Location: /academic/public/me');
  exit;
}

# 1) оценки
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

# 2) активность по типам
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

# 3) активность по дням (для графика)
$sqlDays = "
SELECT date, SUM(clicks) AS clicks
FROM v_studentvle_agg
WHERE code_module = ?
  AND code_presentation = ?
  AND id_student = ?
GROUP BY date
ORDER BY date
";
$st3 = db()->prepare($sqlDays);
$st3->execute([$module, $pres, $id_student]);
$days = $st3->fetchAll();

$labels = array_map(fn($r) => (int)$r['date'], $days);
$values = array_map(fn($r) => (int)$r['clicks'], $days);

$title = "Курс $module / $pres";
require __DIR__ . '/_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0"><?= htmlspecialchars($module) ?> / <?= htmlspecialchars($pres) ?></h1>
  <a class="btn btn-outline-secondary btn-sm" href="/academic/public/me">← Назад</a>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h5">Активность по дням</h2>
    <canvas id="clicksChart" height="90"></canvas>
  </div>
</div>

<script>
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
const data = <?= json_encode($values, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('clicksChart'), {
  type: 'line',
  data: { labels, datasets: [{ label: 'Клики', data }] },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    scales: {
      x: { title: { display: true, text: 'День (от старта курса)' } },
      y: { title: { display: true, text: 'Клики' } }
    }
  }
});
</script>

<div class="row">
  <div class="col-lg-7">
    <div class="card mb-4">
      <div class="card-body">
        <h2 class="h5">Оценивания</h2>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th>id</th><th>type</th><th>day</th><th>weight</th><th>submitted</th><th>score</th></tr></thead>
            <tbody>
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
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-4">
      <div class="card-body">
        <h2 class="h5">Активность по типам</h2>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>type</th><th>clicks</th></tr></thead>
            <tbody>
            <?php foreach ($activity as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['activity_type']) ?></td>
                <td><?= htmlspecialchars((string)$r['clicks']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
