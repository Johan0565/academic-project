<?php
require_student();
$id_student = (int) $_SESSION['id_student'];

$module = $_GET['module'] ?? '';
$pres = $_GET['presentation'] ?? '';
if ($module === '' || $pres === '') {
  header('Location: /academic/public/me');
  exit;
}

/* ===== Переводы/подписи ===== */

$genderMap = ['M' => 'М', 'F' => 'Ж'];
$yesNoMap = ['Y' => 'Да', 'N' => 'Нет'];

$ageMap = [
  '0-35' => 'до 35',
  '35-55' => '35–55',
  '55<=' => '55+',
];

$finalMap = [
  'Pass' => 'Зачёт',
  'Fail' => 'Незачёт',
  'Withdrawn' => 'Отчислен',
  'Distinction' => 'С отличием',
];

$educationMap = [
  'No Formal quals' => 'Без формального образования',
  'Lower Than A Level' => 'Ниже A-level',
  'A Level or Equivalent' => 'A-level или эквивалент',
  'HE Qualification' => 'Высшее образование',
  'Post Graduate Qualification' => 'Послевузовское образование',
];

$assessmentTypeMap = [
  'TMA' => 'TMA (письменная работа)',
  'CMA' => 'CMA (компьютерная работа)',
  'Exam' => 'Экзамен',
  'EMA' => 'EMA (итоговая работа)',
];

$activityMap = [
  'forumng' => 'Форум',
  'oucontent' => 'Материалы курса',
  'homepage' => 'Главная',
  'resource' => 'Файлы/ресурсы',
  'subpage' => 'Страницы',
  'url' => 'Ссылки',
  'ouwiki' => 'Вики',
  'quiz' => 'Тесты',
  'dataplus' => 'Data+',
  'page' => 'Страница',
  'externalquiz' => 'Внешний тест',
];

function tval($v, $map, $default = null)
{
  if ($v === null || $v === '')
    return '—';
  return $map[$v] ?? ($default ?? $v);
}

/* ===== Запросы ===== */

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

# Расчёты по оценкам: выполненный вес и вклад
$totalWeight = 0.0;
$doneWeight = 0.0;
$doneContribution = 0.0; // сумма score*weight

foreach ($assessments as &$a) {
  $w = (float) ($a['weight'] ?? 0);
  if ($w > 0)
    $totalWeight += $w;

  $score = $a['score_num'];
  $hasScore = ($score !== null && $score !== '');
  $contrib = null;

  if ($w > 0 && $hasScore) {
    $doneWeight += $w;
    $contrib = (float) $score * $w;
    $doneContribution += $contrib;
  }
  $a['contribution'] = $contrib;
}
unset($a);

$donePercent = ($totalWeight > 0) ? round(($doneWeight / $totalWeight) * 100, 1) : null;

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

# 3) активность по дням
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

# 4) активность по неделям
$sqlWeeks = "
SELECT FLOOR(date / 7) AS week_index, SUM(clicks) AS clicks
FROM v_studentvle_agg
WHERE code_module = ?
  AND code_presentation = ?
  AND id_student = ?
GROUP BY FLOOR(date / 7)
ORDER BY week_index
";
$stW = db()->prepare($sqlWeeks);
$stW->execute([$module, $pres, $id_student]);
$weeks = $stW->fetchAll();

$weekLabels = array_map(fn($r) => (int) $r['week_index'], $weeks);
$weekValues = array_map(fn($r) => (int) $r['clicks'], $weeks);

# 5) прогресс по оценкам (взвеш.)
$sqlProgress = "
SELECT
  SUM(sa.score_num * a.weight) / NULLIF(SUM(a.weight), 0) AS weighted_score
FROM v_assessments a
JOIN v_studentassessment sa
  ON sa.id_assessment = a.id_assessment
 AND sa.id_student = ?
WHERE a.code_module = ?
  AND a.code_presentation = ?
  AND sa.score_num IS NOT NULL
  AND a.weight > 0
";
$stP = db()->prepare($sqlProgress);
$stP->execute([$id_student, $module, $pres]);
$weighted = $stP->fetchColumn();

# 6) профиль
$sqlProfile = "
SELECT
  si.id_student, si.gender, si.region, si.highest_education, si.imd_band,
  si.age_band, si.num_of_prev_attempts, si.studied_credits, si.disability,
  si.final_result,
  sr.date_registration_day, sr.date_unregistration_day
FROM studentinfo si
LEFT JOIN v_studentregistration sr
  ON sr.code_module = si.code_module
 AND sr.code_presentation = si.code_presentation
 AND sr.id_student = si.id_student
WHERE si.id_student = ?
  AND si.code_module = ?
  AND si.code_presentation = ?
LIMIT 1
";
$stPr = db()->prepare($sqlProfile);
$stPr->execute([$id_student, $module, $pres]);
$profile = $stPr->fetch();

$labels = array_map(fn($r) => (int) $r['date'], $days);
$values = array_map(fn($r) => (int) $r['clicks'], $days);

# ===== Сравнение с группой =====

# мои total clicks
$stMy = db()->prepare("
SELECT COALESCE(SUM(clicks),0) AS total_clicks
FROM v_studentvle_agg
WHERE code_module=? AND code_presentation=? AND id_student=?
");
$stMy->execute([$module, $pres, $id_student]);
$myTotalClicks = (int) $stMy->fetchColumn();

# totals всех студентов курса
$stAll = db()->prepare("
SELECT id_student, SUM(clicks) AS total_clicks
FROM v_studentvle_agg
WHERE code_module=? AND code_presentation=?
GROUP BY id_student
");
$stAll->execute([$module, $pres]);
$allTotals = $stAll->fetchAll();

$totals = array_map(fn($r) => (int) $r['total_clicks'], $allTotals);
sort($totals);
$n = count($totals);

$avg = ($n > 0) ? array_sum($totals) / $n : 0;
$median = 0;
if ($n > 0) {
  $mid = intdiv($n, 2);
  $median = ($n % 2 === 0) ? (($totals[$mid - 1] + $totals[$mid]) / 2) : $totals[$mid];
}

$le = 0;
foreach ($totals as $v) {
  if ($v <= $myTotalClicks)
    $le++;
}
$percentile = ($n > 0) ? round(($le / $n) * 100, 1) : null;

# среднее по типам (клики на студента)
$stAvgType = db()->prepare("
SELECT activity_type, AVG(clicks_per_student) AS avg_clicks
FROM (
  SELECT sv.id_student, v.activity_type, SUM(sv.clicks) AS clicks_per_student
  FROM v_studentvle_agg sv
  JOIN v_vle v
    ON v.id_site = sv.id_site
   AND v.code_module = sv.code_module
   AND v.code_presentation = sv.code_presentation
  WHERE sv.code_module=? AND sv.code_presentation=?
  GROUP BY sv.id_student, v.activity_type
) x
GROUP BY activity_type
");
$stAvgType->execute([$module, $pres]);
$avgTypeRows = $stAvgType->fetchAll();

$avgByType = [];
foreach ($avgTypeRows as $r) {
  $avgByType[$r['activity_type']] = (float) $r['avg_clicks'];
}

/* ===== Рендер ===== */

$title = "Курс $module / $pres";
require __DIR__ . '/_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3 mb-0"><?= htmlspecialchars($module) ?> / <?= htmlspecialchars($pres) ?></h1>
    </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="/academic/public/me">← Назад</a>
    <a class="btn btn-outline-primary btn-sm"
      href="/academic/public/me/export?module=<?= urlencode($module) ?>&presentation=<?= urlencode($pres) ?>">
      Скачать CSV
    </a>
  </div>
</div>

<ul class="nav nav-tabs" id="courseTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-activity"
      type="button">Активность</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-assess" type="button">Оценки</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-compare" type="button">Сравнение с
      группой</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button">О студенте</button>
  </li>
</ul>

<div class="tab-content border border-top-0 p-3 bg-white">
  <!-- Активность -->
  <div class="tab-pane fade show active" id="tab-activity">
    <div class="row">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-body">
            <h2 class="h5">Активность по дням</h2>
            <div class="text-muted small">Дни считаются от старта курса</div>
            <canvas id="clicksByDay" height="90"></canvas>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h2 class="h5">Активность по неделям</h2>
            <canvas id="clicksByWeek" height="90"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h2 class="h5">Активность по разделам</h2>
            <canvas id="clicksByType" height="220"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Оценки -->
  <div class="tab-pane fade" id="tab-assess">
    <div class="row justify-content-center mb-4 g-4">
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <h5 class="card-title text-success fw-bold mb-3">Процент выполнения курса</h5>
            <div style="position: relative; height: 220px; width: 100%;">
              <canvas id="courseProgressChart"></canvas>
            </div>
            <div class="mt-3 text-muted">
              <small>
                Выполнено веса: <b><?= number_format((float) $doneWeight, 1) ?></b> из
                <b><?= number_format((float) $totalWeight, 1) ?></b>
                <br>
                Суммарный вклад: <b><?= number_format((float) $doneContribution, 2) ?></b>
              </small>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <h5 class="card-title text-primary fw-bold mb-3">Средний балл за курс</h5>
            <div style="position: relative; height: 220px; width: 100%;">
              <canvas id="averageScoreChart"></canvas>
            </div>
            <div class="mt-3 text-muted">
              <small>
                Взвешенный балл: <b><?= $weighted !== null ? number_format((float) $weighted, 2) : '0.00' ?></b>
                <br>
                (по сданным работам)
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Тип оценивания</th>
            <th>Дедлайн (день)</th>
            <th>Вес</th>
            <th>Сдано (день)</th>
            <th>Баллы</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assessments as $a): ?>
              <tr>
                <td><?= (int) $a['id_assessment'] ?></td>
                <td><?= htmlspecialchars(tval($a['assessment_type'], $assessmentTypeMap)) ?></td>
                <td><?= ($a['date_day'] === null ? '—' : (int) $a['date_day']) ?></td>
                <td><?= htmlspecialchars((string) $a['weight']) ?></td>
                <td><?= ($a['date_submitted'] === null ? '—' : (int) $a['date_submitted']) ?></td>
                <td><?= ($a['score_num'] === null ? '—' : htmlspecialchars((string) $a['score_num'])) ?></td>
              </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Сравнение -->
  <div class="tab-pane fade" id="tab-compare">
    <div class="row g-4">
      <!-- Левая колонка: Общие показатели -->
      <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="fw-bold text-dark mb-0">📊 Общая активность</h5>
          </div>
          <div class="card-body">
            <div class="mb-4" style="height: 200px;">
              <canvas id="compareTotalChart"></canvas>
            </div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between bg-transparent">
                <span class="text-muted">Мои клики</span>
                <span class="fw-bold"><?= (int) $myTotalClicks ?></span>
              </li>
              <li class="list-group-item d-flex justify-content-between bg-transparent">
                <span class="text-muted">Среднее по курсу</span>
                <span><?= number_format((float) $avg, 1) ?></span>
              </li>

              <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                <span class="text-muted">Перцентиль</span>
                <span class="badge bg-primary rounded-pill"><?= $percentile ?>%</span>
              </li>
            </ul>
            <div class="text-center mt-3">
              <small class="text-muted">Вы активнее, чем <?= $percentile ?>% группы</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Правая колонка: Радар и Детали -->
      <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-transparent border-0 pt-3">
            <h5 class="fw-bold text-dark mb-0">🕸 Профиль активности</h5>
          </div>
          <div class="card-body">
            <div style="height: 350px;">
              <canvas id="compareRadarChart"></canvas>
            </div>
            <hr>
            <h6 class="fw-bold text-muted small text-uppercase mt-4">Детали (по моей активности)</h6>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Раздел</th>
                    <th class="text-end">Я</th>
                    <th class="text-end">Среднее</th>
                    <th class="text-end">Инфо</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($activity as $r):
                    $type = $r['activity_type'];
                    $my = (int) $r['clicks'];
                    $avgT = $avgByType[$type] ?? 0;
                    $diff = $my - $avgT;
                    $typeRu = $activityMap[$type] ?? $type;
                    $badge = $diff >= 0
                      ? '<span class="badge bg-success bg-opacity-10 text-success">+' . number_format($diff, 0) . '</span>'
                      : '<span class="badge bg-danger bg-opacity-10 text-danger">' . number_format($diff, 0) . '</span>';
                    ?>
                      <tr>
                        <td><?= htmlspecialchars($typeRu) ?></td>
                        <td class="text-end fw-semibold"><?= $my ?></td>
                        <td class="text-end text-muted"><?= number_format((float) $avgT, 0) ?></td>
                        <td class="text-end"><?= $badge ?></td>
                      </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Профиль -->
  <div class="tab-pane fade" id="tab-profile">
    <?php if (!$profile): ?>
        <div class="alert alert-warning">Профиль не найден.</div>
    <?php else: ?>
        <div class="row g-4">
          <!-- Карточка: Личные данные -->
          <div class="col-md-6">
            <div class="card shadow-sm h-100 border-0">
              <div class="card-header bg-transparent border-0 pt-4 pb-2">
                <h2 class="h5 text-primary fw-bold mb-0">👤 Личные данные</h2>
              </div>
              <div class="card-body pt-0">
                <ul class="list-group list-group-flush">
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Пол</span>
                    <span class="fw-semibold"><?= htmlspecialchars(tval($profile['gender'], $genderMap)) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Возраст</span>
                    <span class="fw-semibold"><?= htmlspecialchars(tval($profile['age_band'], $ageMap)) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Регион</span>
                    <span class="fw-semibold text-end w-50"><?= htmlspecialchars(tval($profile['region'], [])) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Образование</span>
                    <span
                      class="fw-semibold text-end w-50"><?= htmlspecialchars(tval($profile['highest_education'], $educationMap)) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Соц. индекс (IMD)</span>
                    <span
                      class="badge bg-light text-dark border"><?= htmlspecialchars(tval($profile['imd_band'], [])) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Инвалидность</span>
                    <span class="fw-semibold"><?= htmlspecialchars(tval($profile['disability'], $yesNoMap)) ?></span>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Карточка: Данные об обучении -->
          <div class="col-md-6">
            <div class="card shadow-sm h-100 border-0">
              <div class="card-header bg-transparent border-0 pt-4 pb-2">
                <h2 class="h5 text-success fw-bold mb-0">🎓 Данные об обучении</h2>
              </div>
              <div class="card-body pt-0">
                <ul class="list-group list-group-flush">
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Финальный итог</span>
                    <?php
                    $res = tval($profile['final_result'], $finalMap);
                    $badgeClass = match ($profile['final_result']) {
                      'Pass', 'Distinction' => 'bg-success',
                      'Fail', 'Withdrawn' => 'bg-danger',
                      default => 'bg-secondary'
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?> rounded-pill"><?= htmlspecialchars($res) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Предыдущие попытки</span>
                    <span class="fw-semibold"><?= htmlspecialchars((string) $profile['num_of_prev_attempts']) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">Учебные кредиты</span>
                    <span class="fw-semibold"><?= htmlspecialchars((string) $profile['studied_credits']) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">День регистрации</span>
                    <span
                      class="font-monospace text-primary"><?= ($profile['date_registration_day'] === null ? '—' : (int) $profile['date_registration_day']) ?></span>
                  </li>
                  <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                    <span class="text-muted">День отчисления</span>
                    <span
                      class="font-monospace text-danger"><?= ($profile['date_unregistration_day'] === null ? '—' : (int) $profile['date_unregistration_day']) ?></span>
                  </li>
                </ul>
                <div class="alert alert-light border mt-3 small text-muted">ℹ️ Примечание: Дни считаются от официального
                  старта курса (день 0).</div>
              </div>
            </div>
          </div>
        </div>
    <?php endif; ?>
  </div>
</div>

<script>
  const labelsDay = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const dataDay = <?= json_encode($values, JSON_UNESCAPED_UNICODE) ?>;

  const labelsWeek = <?= json_encode($weekLabels, JSON_UNESCAPED_UNICODE) ?>;
  const dataWeek = <?= json_encode($weekValues, JSON_UNESCAPED_UNICODE) ?>;

  const typeLabels = <?= json_encode(array_map(
    fn($r) => ($activityMap[$r['activity_type']] ?? $r['activity_type']),
    $activity
  ), JSON_UNESCAPED_UNICODE) ?>;

  const typeData = <?= json_encode(array_map(fn($r) => (int) $r['clicks'], $activity), JSON_UNESCAPED_UNICODE) ?>;

  new Chart(document.getElementById('clicksByDay'), {
    type: 'line',
    data: { labels: labelsDay, datasets: [{ label: 'Клики', data: dataDay }] },
    options: { responsive: true, interaction: { mode: 'index', intersect: false } }
  });

  new Chart(document.getElementById('clicksByWeek'), {
    type: 'bar',
    data: { labels: labelsWeek, datasets: [{ label: 'Клики', data: dataWeek }] },
    options: { responsive: true }
  });

  new Chart(document.getElementById('clicksByType'), {
    type: 'doughnut',
    data: { labels: typeLabels, datasets: [{ label: 'Клики', data: typeData }] },
    options: { responsive: true }
  });

  /* Graph for Course Progress */
  const courseDone = <?= (float) ($donePercent ?? 0) ?>;
  const courseRemain = 100 - courseDone;

  new Chart(document.getElementById('courseProgressChart'), {
    type: 'doughnut',
    data: {
      labels: ['Выполнено', 'Осталось'],
      datasets: [{
        data: [courseDone, courseRemain],
        backgroundColor: [
          'rgba(25, 135, 84, 0.8)', // success
          'rgba(233, 236, 239, 0.8)' // gray
        ],
        hoverBackgroundColor: [
          'rgba(25, 135, 84, 1)',
          'rgba(233, 236, 239, 1)'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { boxWidth: 12 }
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              return context.label + ': ' + context.parsed + '%';
            }
          }
        }
      }
    }
  });

  /* Graph for Average Score */
  const avgScore = <?= $weighted !== null ? (float) $weighted : 0 ?>;
  const avgRemain = 100 - avgScore;

  new Chart(document.getElementById('averageScoreChart'), {
    type: 'doughnut',
    data: {
      labels: ['Балл', 'Остаток'],
      datasets: [{
        data: [avgScore, avgRemain],
        backgroundColor: [
          'rgba(13, 110, 253, 0.8)', // primary blue
          'rgba(233, 236, 239, 0.8)' // gray
        ],
        hoverBackgroundColor: [
          'rgba(13, 110, 253, 1)',
          'rgba(233, 236, 239, 1)'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { boxWidth: 12 }
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              return context.label + ': ' + context.parsed;
            }
          }
        }
      }
    }
  });
  /* Сравнение Total BarChart */
  new Chart(document.getElementById('compareTotalChart'), {
    type: 'bar',
    data: {
      labels: ['Я', 'Среднее'],
      datasets: [{
        label: 'Клики',
        data: [<?= (int) $myTotalClicks ?>, <?= (float) $avg ?>],
        backgroundColor: [
          'rgba(13, 110, 253, 0.7)',
          'rgba(108, 117, 125, 0.7)'
        ],
        borderColor: [
          'rgb(13, 110, 253)',
          'rgb(108, 117, 125)'
        ],
        borderWidth: 1,
        borderRadius: 5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  /* Сравнение Radar Chart */
  const allAvgMap = <?= json_encode($avgByType) ?>;
  const myActivityList = <?= json_encode($activity) ?>;
  const actMap = <?= json_encode($activityMap, JSON_UNESCAPED_UNICODE) ?>;

  // Собираем все ключи (из моих кликов и средних по курсу)
  const allKeysSet = new Set([...Object.keys(allAvgMap), ...myActivityList.map(a => a.activity_type)]);
  const allKeys = Array.from(allKeysSet);

  const radarLabels = [];
  const radarDataMy = [];
  const radarDataAvg = [];

  allKeys.forEach(k => {
    radarLabels.push(actMap[k] || k);

    // my data
    const myItem = myActivityList.find(a => a.activity_type === k);
    radarDataMy.push(myItem ? parseInt(myItem.clicks) : 0);

    // avg data
    radarDataAvg.push(Math.round(allAvgMap[k] || 0));
  });

  new Chart(document.getElementById('compareRadarChart'), {
    type: 'radar',
    data: {
      labels: radarLabels,
      datasets: [
        {
          label: 'Я',
          data: radarDataMy,
          fill: true,
          backgroundColor: 'rgba(13, 110, 253, 0.2)',
          borderColor: 'rgb(13, 110, 253)',
          pointBackgroundColor: 'rgb(13, 110, 253)',
          pointBorderColor: '#fff',
          pointHoverBackgroundColor: '#fff',
          pointHoverBorderColor: 'rgb(13, 110, 253)'
        },
        {
          label: 'Среднее',
          data: radarDataAvg,
          fill: true,
          backgroundColor: 'rgba(108, 117, 125, 0.2)',
          borderColor: 'rgb(108, 117, 125)',
          pointBackgroundColor: 'rgb(108, 117, 125)',
          pointBorderColor: '#fff',
          pointHoverBackgroundColor: '#fff',
          pointHoverBorderColor: 'rgb(108, 117, 125)'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      elements: {
        line: { borderWidth: 2 }
      },
      scales: {
        r: {
          angleLines: { display: true },
          suggestedMin: 0
        }
      }
    }
  });
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>