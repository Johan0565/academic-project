<?php
require_admin();

$module = $_GET['module'] ?? '';
$pres   = $_GET['presentation'] ?? '';
$kChosen = isset($_GET['k']) ? (int)$_GET['k'] : null;

$title = "Analyze: Клики + Средний балл (K-Means)";
require __DIR__ . '/_layout_top.php';

function h($s){ return htmlspecialchars((string)$s); }

/* =========================
   Helpers: quantiles, levels
   ========================= */

function quantile(array $arr, float $q) {
  $n = count($arr);
  if ($n === 0) return null;
  sort($arr, SORT_NUMERIC);
  if ($n === 1) return (float)$arr[0];

  $pos = ($n - 1) * $q;
  $lo = (int)floor($pos);
  $hi = (int)ceil($pos);
  if ($lo === $hi) return (float)$arr[$lo];
  $w = $pos - $lo;
  return (float)$arr[$lo] * (1 - $w) + (float)$arr[$hi] * $w;
}

function level3($value, $q33, $q66) {
  if ($q33 === null || $q66 === null) return 'средний';
  if ($value < $q33) return 'низкий';
  if ($value > $q66) return 'высокий';
  return 'средний';
}

function describe_cluster($clickLevel, $scoreLevel) {
  if ($clickLevel === 'высокий' && $scoreLevel === 'высокий') return 'Активные и успешные';
  if ($clickLevel === 'высокий' && $scoreLevel === 'низкий')  return 'Активные, но низкие баллы';
  if ($clickLevel === 'низкий'  && $scoreLevel === 'высокий') return 'Мало активности, но хорошие баллы';
  if ($clickLevel === 'низкий'  && $scoreLevel === 'низкий')  return 'Пассивные и слабые';

  if ($clickLevel === 'высокий' && $scoreLevel === 'средний') return 'Очень активные со средними баллами';
  if ($clickLevel === 'средний' && $scoreLevel === 'высокий') return 'Стабильные с хорошими баллами';
  if ($clickLevel === 'средний' && $scoreLevel === 'низкий')  return 'Стабильные, но низкие баллы';
  if ($clickLevel === 'низкий'  && $scoreLevel === 'средний') return 'Низкая активность со средними баллами';

  return 'Средний профиль';
}

/* =========================
   KMeans helpers (2D, PHP)
   ========================= */

function rand_int($min, $max) {
  return $min + (int)floor((mt_rand() / mt_getrandmax()) * (($max - $min) + 1));
}

function sq_dist($a, $b) {
  $dx = $a[0] - $b[0];
  $dy = $a[1] - $b[1];
  return $dx*$dx + $dy*$dy;
}

function standardize_matrix_2d($X) {
  $n = count($X);
  if ($n === 0) return [$X, [0,0], [1,1]];

  $mx = 0.0; $my = 0.0;
  foreach ($X as $r) { $mx += $r[0]; $my += $r[1]; }
  $mx /= $n; $my /= $n;

  $sx = 0.0; $sy = 0.0;
  foreach ($X as $r) {
    $sx += ($r[0]-$mx)*($r[0]-$mx);
    $sy += ($r[1]-$my)*($r[1]-$my);
  }
  $sx = sqrt($sx / $n); if ($sx == 0.0) $sx = 1.0;
  $sy = sqrt($sy / $n); if ($sy == 0.0) $sy = 1.0;

  $Z = [];
  foreach ($X as $r) {
    $Z[] = [ ($r[0]-$mx)/$sx, ($r[1]-$my)/$sy ];
  }
  return [$Z, [$mx,$my], [$sx,$sy]];
}

function kmeans_once_2d($X, $k, $maxIter = 60) {
  $n = count($X);
  if ($n === 0) return [[], [], 0.0];
  $k = min($k, $n);

  $chosen = [];
  $centroids = [];
  while (count($centroids) < $k) {
    $idx = rand_int(0, $n-1);
    if (isset($chosen[$idx])) continue;
    $chosen[$idx] = true;
    $centroids[] = $X[$idx];
  }

  $labels = array_fill(0, $n, 0);

  for ($iter=0; $iter<$maxIter; $iter++) {
    $changed = false;

    for ($i=0; $i<$n; $i++) {
      $best = 0;
      $bestD = sq_dist($X[$i], $centroids[0]);
      for ($c=1; $c<$k; $c++) {
        $d = sq_dist($X[$i], $centroids[$c]);
        if ($d < $bestD) { $bestD = $d; $best = $c; }
      }
      if ($labels[$i] !== $best) {
        $labels[$i] = $best;
        $changed = true;
      }
    }

    $sum = array_fill(0, $k, [0.0, 0.0]);
    $cnt = array_fill(0, $k, 0);

    for ($i=0; $i<$n; $i++) {
      $c = $labels[$i];
      $cnt[$c]++;
      $sum[$c][0] += $X[$i][0];
      $sum[$c][1] += $X[$i][1];
    }

    for ($c=0; $c<$k; $c++) {
      if ($cnt[$c] === 0) {
        $centroids[$c] = $X[rand_int(0, $n-1)];
      } else {
        $centroids[$c] = [ $sum[$c][0]/$cnt[$c], $sum[$c][1]/$cnt[$c] ];
      }
    }

    if (!$changed) break;
  }

  $inertia = 0.0;
  for ($i=0; $i<$n; $i++) $inertia += sq_dist($X[$i], $centroids[$labels[$i]]);

  return [$labels, $centroids, $inertia];
}

function kmeans_best_2d($X, $k, $nInit = 8, $maxIter = 60) {
  $best = null;
  $bestIn = INF;
  for ($r=0; $r<$nInit; $r++) {
    [$lab,$cen,$in] = kmeans_once_2d($X, $k, $maxIter);
    if ($in < $bestIn) { $bestIn = $in; $best = [$lab,$cen,$in]; }
  }
  return $best;
}

/* =========================
   Course selector
   ========================= */

$courses = db()->query("
  SELECT DISTINCT code_module, code_presentation
  FROM courses
  ORDER BY code_module, code_presentation
")->fetchAll();
?>

<h1 class="h3 mb-3">Analyze: кластеризация по кликам и среднему баллу</h1>

<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Модуль (code_module)</label>
      <select class="form-select" name="module" required>
        <option value="">— выберите —</option>
        <?php
          $mods = [];
          foreach ($courses as $c) $mods[$c['code_module']] = true;
          foreach (array_keys($mods) as $m):
        ?>
          <option value="<?= h($m) ?>" <?= $m===$module?'selected':'' ?>><?= h($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Презентация (code_presentation)</label>
      <select class="form-select" name="presentation" required>
        <option value="">— выберите —</option>
        <?php foreach ($courses as $c): ?>
          <?php if ($module !== '' && $c['code_module'] !== $module) continue; ?>
          <option value="<?= h($c['code_presentation']) ?>" <?= $c['code_presentation']===$pres?'selected':'' ?>>
            <?= h($c['code_presentation']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Сначала выберите модуль — список презентаций сузится.</div>
    </div>

    <div class="col-md-2">
      <label class="form-label">k (2..8)</label>
      <input class="form-control" type="number" name="k" min="2" max="8"
             value="<?= $kChosen ?? 3 ?>">
    </div>

    <div class="col-md-2">
      <button class="btn btn-primary w-100" type="submit">Построить</button>
    </div>
  </div>
</form>

<?php
if ($module === '' || $pres === '') {
  echo '<div class="alert alert-info">Выберите курс, чтобы посчитать точки (total_clicks, avg_score) и выполнить K-Means.</div>';
  require __DIR__ . '/_layout_bottom.php';
  exit;
}

/* =========================
   Data: clicks + avg_score
   ========================= */

// total_clicks per student
$stClicks = db()->prepare("
  SELECT id_student, COALESCE(SUM(clicks),0) AS total_clicks
  FROM v_studentvle_agg
  WHERE code_module=? AND code_presentation=?
  GROUP BY id_student
");
$stClicks->execute([$module, $pres]);
$clickRows = $stClicks->fetchAll();

$clickMap = [];
foreach ($clickRows as $r) {
  $sid = (int)$r['id_student'];
  $clickMap[$sid] = (int)$r['total_clicks'];
}

// avg_score per student (mean of available scores for this course)
$stScore = db()->prepare("
  SELECT sa.id_student, AVG(sa.score_num) AS avg_score
  FROM v_assessments a
  JOIN v_studentassessment sa
    ON sa.id_assessment = a.id_assessment
  WHERE a.code_module=? AND a.code_presentation=?
    AND sa.score_num IS NOT NULL
  GROUP BY sa.id_student
");
$stScore->execute([$module, $pres]);
$scoreRows = $stScore->fetchAll();

$scoreMap = [];
foreach ($scoreRows as $r) {
  $sid = (int)$r['id_student'];
  $scoreMap[$sid] = (float)$r['avg_score'];
}

// build intersection (students with BOTH clicks and score)
$ids = [];
$Xraw = []; // [clicks, avg_score]
$excludedNoScore = 0;

foreach ($clickMap as $sid => $clicks) {
  if (!isset($scoreMap[$sid])) { $excludedNoScore++; continue; }
  $ids[] = $sid;
  $Xraw[] = [ (float)$clicks, (float)$scoreMap[$sid] ];
}

$n = count($Xraw);
$kChosen = ($kChosen === null) ? 3 : max(2, min(8, $kChosen));
if ($n > 0) $kChosen = min($kChosen, $n);

// sample if too big (speed)
$maxN = 2000;
$sampled = false;
if ($n > $maxN) {
  $sampled = true;
  $idx = range(0, $n-1);
  shuffle($idx);
  $idx = array_slice($idx, 0, $maxN);
  sort($idx);

  $ids2 = [];
  $X2 = [];
  foreach ($idx as $i) { $ids2[] = $ids[$i]; $X2[] = $Xraw[$i]; }
  $ids = $ids2; $Xraw = $X2;
  $n = count($Xraw);
  $kChosen = min($kChosen, $n);
}

/* =========================================================
   NEW: entropy_types + share_forum (для новых scatter-графиков)
   ========================================================= */
$stTypes = db()->prepare("
  SELECT sv.id_student, v.activity_type, SUM(sv.clicks) AS clicks
  FROM v_studentvle_agg sv
  JOIN v_vle v
    ON v.id_site = sv.id_site
   AND v.code_module = sv.code_module
   AND v.code_presentation = sv.code_presentation
  WHERE sv.code_module=? AND sv.code_presentation=?
  GROUP BY sv.id_student, v.activity_type
");
$stTypes->execute([$module, $pres]);
$typeRows = $stTypes->fetchAll();

// соберём type clicks
$typeMap = []; // [id_student][type] = clicks
foreach ($typeRows as $r) {
  $sid = (int)$r['id_student'];
  $t = (string)$r['activity_type'];
  $c = (int)$r['clicks'];
  $typeMap[$sid][$t] = $c;
}

// вычислим энтропию и долю форума для студентов, которые участвуют в кластеризации
$entropyByStudent = [];
$forumShareByStudent = [];

foreach ($ids as $sid) {
  $totalClicks = (float)($clickMap[$sid] ?? 0);
  if ($totalClicks <= 0) {
    $entropyByStudent[$sid] = 0.0;
    $forumShareByStudent[$sid] = 0.0;
    continue;
  }

  $types = $typeMap[$sid] ?? [];
  $forumClicks = (float)($types['forumng'] ?? 0);

  // entropy
  $H = 0.0;
  $m = 0;
  foreach ($types as $t => $c) {
    if ($c <= 0) continue;
    $p = $c / $totalClicks;
    if ($p > 0) $H -= $p * log($p);
    $m++;
  }
  $Hnorm = ($m > 1) ? ($H / log($m)) : 0.0;

  $entropyByStudent[$sid] = $Hnorm;
  $forumShareByStudent[$sid] = ($forumClicks / $totalClicks);
}

// точки для новых графиков
$entropyScorePoints = [];
$forumScorePoints = [];

for ($i=0; $i<count($ids); $i++) {
  $sid = (int)$ids[$i];
  $clicks = (float)$Xraw[$i][0];
  $avgScore = (float)$Xraw[$i][1];
  if ($clicks <= 0) continue;

  $entropyScorePoints[] = ['x' => (float)($entropyByStudent[$sid] ?? 0.0), 'y' => $avgScore];
  $forumScorePoints[]   = ['x' => (float)($forumShareByStudent[$sid] ?? 0.0), 'y' => $avgScore];
}

// лимит точек для скорости рендера
$maxPts = 2000;
if (count($entropyScorePoints) > $maxPts) $entropyScorePoints = array_slice($entropyScorePoints, 0, $maxPts);
if (count($forumScorePoints) > $maxPts)   $forumScorePoints   = array_slice($forumScorePoints, 0, $maxPts);

/* =========================
   KMeans (как было)
   ========================= */

// standardize
[$X, $meanVec, $stdVec] = standardize_matrix_2d($Xraw);

// elbow for k=2..8
$ks = [];
$inertias = [];
mt_srand(42);
for ($k=2; $k<=8; $k++) {
  if ($k > $n) break;
  $ks[] = $k;
  [$labTmp, $cenTmp, $inTmp] = kmeans_best_2d($X, $k, 6, 60);
  $inertias[] = $inTmp;
}

// run chosen k
$labels = null; $centroids = null; $inertia = null;
if ($n >= 2 && $kChosen <= $n) {
  mt_srand(123);
  [$labels, $centroids, $inertia] = kmeans_best_2d($X, $kChosen, 10, 80);
}

// global thresholds (tertiles) in ORIGINAL scale
$allClicks = array_map(fn($r) => (float)$r[0], $Xraw);
$allScores = array_map(fn($r) => (float)$r[1], $Xraw);
$q33Clicks = quantile($allClicks, 0.33);
$q66Clicks = quantile($allClicks, 0.66);
$q33Score  = quantile($allScores, 0.33);
$q66Score  = quantile($allScores, 0.66);

// cluster stats + automatic names
$clusterStats = [];
$scatterByCluster = [];
$clusterLabelText = [];

if ($labels !== null) {
  for ($c=0; $c<$kChosen; $c++) {
    $clusterStats[$c] = ['size'=>0,'sum_clicks'=>0.0,'sum_score'=>0.0];
    $scatterByCluster[$c] = [];
  }
  for ($i=0; $i<$n; $i++) {
    $c = (int)$labels[$i];
    $clusterStats[$c]['size']++;
    $clusterStats[$c]['sum_clicks'] += $Xraw[$i][0];
    $clusterStats[$c]['sum_score']  += $Xraw[$i][1];
    $scatterByCluster[$c][] = ['x'=>$Xraw[$i][0], 'y'=>$Xraw[$i][1]];
  }

  for ($c=0; $c<$kChosen; $c++) {
    $sz = max(1, $clusterStats[$c]['size']);
    $meanClicks = $clusterStats[$c]['sum_clicks'] / $sz;
    $meanScore  = $clusterStats[$c]['sum_score'] / $sz;

    $clusterStats[$c]['mean_clicks'] = $meanClicks;
    $clusterStats[$c]['mean_score']  = $meanScore;

    $clickLevel = level3($meanClicks, $q33Clicks, $q66Clicks);
    $scoreLevel = level3($meanScore,  $q33Score,  $q66Score);
    $desc = describe_cluster($clickLevel, $scoreLevel);

    $clusterStats[$c]['click_level'] = $clickLevel;
    $clusterStats[$c]['score_level'] = $scoreLevel;
    $clusterStats[$c]['desc'] = $desc;

    $clusterLabelText[$c] = "Кластер $c: $desc";
  }
}
?>

<div class="alert alert-secondary">
  <b>Курс:</b> <?= h($module) ?> / <?= h($pres) ?><br>
  <b>Точек для кластеризации (есть и клики, и оценки):</b> <?= (int)$n ?><br>
  <b>Студентов без оценок (исключены):</b> <?= (int)$excludedNoScore ?><br>
  <b>Признаки:</b> total_clicks и avg_score (средний балл по доступным оцениваниям)
  <?php if ($sampled): ?>
    <br><b>Внимание:</b> подвыборка <?= (int)$maxN ?> студентов для скорости.
  <?php endif; ?>
</div>


<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">Метод локтя (inertia)</h2>
        <canvas id="elbow" height="130"></canvas>
        <div class="text-muted small mt-2">
          Inertia — сумма квадратов расстояний до центроидов (чем меньше, тем лучше), но при росте k она всегда падает.
          “Локоть” выбирают визуально: где падение становится небольшим.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">Scatter: total_clicks vs avg_score</h2>
        <?php if ($labels === null): ?>
          <div class="alert alert-warning mb-0">Недостаточно данных для K-Means.</div>
        <?php else: ?>
          <canvas id="scatter" height="130"></canvas>
          <div class="text-muted small mt-2">
            Кластеры считаются в стандартизованных координатах (z-score), но отображаются в исходных единицах.
            Inertia(k=<?= (int)$kChosen ?>): <b><?= number_format((float)$inertia, 2) ?></b>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- NEW: Scatter поведение ↔ успеваемость -->

<?php if ($labels !== null): ?>
<div class="card mt-3">
  <div class="card-body">
    <h2 class="h5">Профили кластеров (с авто-описанием)</h2>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>Кластер</th>
            <th>Описание</th>
            <th>Размер</th>
            <th>Средние клики</th>
            <th>Уровень кликов</th>
            <th>Средний балл</th>
            <th>Уровень балла</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clusterStats as $c => $st): ?>
            <tr>
              <td>#<?= (int)$c ?></td>
              <td><?= h($st['desc']) ?></td>
              <td><?= (int)$st['size'] ?></td>
              <td><?= number_format((float)$st['mean_clicks'], 1) ?></td>
              <td><?= h($st['click_level']) ?></td>
              <td><?= number_format((float)$st['mean_score'], 2) ?></td>
              <td><?= h($st['score_level']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
<?php endif; ?>
<div class="card mt-3">
  <div class="card-body">
    <h2 class="h5 mb-2">Связь поведения и успеваемости (Scatter)</h2>

    <?php if (count($entropyScorePoints) < 10): ?>
      <div class="alert alert-warning mb-0">
        Слишком мало точек для графика (нужно больше студентов с активностью и оценками).
      </div>
    <?php else: ?>
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-body">
              <h3 class="h6">Энтропия типов активности → средний балл</h3>
              <canvas id="scEntropyScore" height="140"></canvas>
              <div class="text-muted small mt-2">
                Энтропия (0..1): ближе к 1 — активность распределена по разным типам более равномерно.
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card">
            <div class="card-body">
              <h3 class="h6">Доля форума → средний балл</h3>
              <canvas id="scForumScore" height="140"></canvas>
              <div class="text-muted small mt-2">
                Доля форума (0..1): какая часть кликов пришлась на <code>forumng</code>.
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>



<script>
const ks = <?= json_encode($ks, JSON_UNESCAPED_UNICODE) ?>;
const inertias = <?= json_encode($inertias, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('elbow'), {
  type: 'line',
  data: {
    labels: ks,
    datasets: [{
      label: 'Inertia',
      data: inertias
    }]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    scales: {
      x: { title: { display: true, text: 'k' } },
      y: { title: { display: true, text: 'inertia' } }
    }
  }
});

<?php if ($labels !== null): ?>
const scatterByCluster = <?= json_encode($scatterByCluster, JSON_UNESCAPED_UNICODE) ?>;
const clusterLabels = <?= json_encode($clusterLabelText, JSON_UNESCAPED_UNICODE) ?>;

const datasets = Object.keys(scatterByCluster).map(k => ({
  label: clusterLabels[k] ?? ('cluster ' + k),
  data: scatterByCluster[k],
  showLine: false
}));

new Chart(document.getElementById('scatter'), {
  type: 'scatter',
  data: { datasets },
  options: {
    responsive: true,
    scales: {
      x: { title: { display: true, text: 'total_clicks' } },
      y: { title: { display: true, text: 'avg_score' }, min: 0, max: 100 }
    }
  }
});
<?php endif; ?>

<?php if (count($entropyScorePoints) >= 10): ?>
const entropyScore = <?= json_encode($entropyScorePoints, JSON_UNESCAPED_UNICODE) ?>;
const forumScore   = <?= json_encode($forumScorePoints, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('scEntropyScore'), {
  type: 'scatter',
  data: { datasets: [{ label: 'Студенты', data: entropyScore }] },
  options: {
    responsive: true,
    scales: {
      x: { title: { display: true, text: 'Энтропия типов активности (0..1)' }, min: 0, max: 1 },
      y: { title: { display: true, text: 'Средний балл (0..100)' }, min: 0, max: 100 }
    }
  }
});

new Chart(document.getElementById('scForumScore'), {
  type: 'scatter',
  data: { datasets: [{ label: 'Студенты', data: forumScore }] },
  options: {
    responsive: true,
    scales: {
      x: { title: { display: true, text: 'Доля форума (0..1)' }, min: 0, max: 1 },
      y: { title: { display: true, text: 'Средний балл (0..100)' }, min: 0, max: 100 }
    }
  }
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
