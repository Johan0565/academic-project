<?php
require_student();

$id_student = (int)$_SESSION['id_student'];
$module = $_GET['module'] ?? '';
$pres = $_GET['presentation'] ?? '';

if ($module === '' || $pres === '') {
  http_response_code(400);
  echo "Нужно передать module и presentation";
  exit;
}

// профиль
$stPr = db()->prepare("
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
");
$stPr->execute([$id_student, $module, $pres]);
$profile = $stPr->fetch();

// оценки
$stA = db()->prepare("
SELECT
  a.id_assessment, a.assessment_type, a.date_day, a.weight,
  sa.date_submitted, sa.score_num
FROM v_assessments a
LEFT JOIN v_studentassessment sa
  ON sa.id_assessment = a.id_assessment
 AND sa.id_student = ?
WHERE a.code_module = ?
  AND a.code_presentation = ?
ORDER BY a.date_day IS NULL, a.date_day
");
$stA->execute([$id_student, $module, $pres]);
$assess = $stA->fetchAll();

// активность по типам
$stT = db()->prepare("
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
");
$stT->execute([$module, $pres, $id_student]);
$types = $stT->fetchAll();

$filename = "student_{$id_student}_{$module}_{$pres}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// UTF-8 BOM для Excel
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// Заголовок
fputcsv($out, ["Отчёт студента", $id_student, "Курс", "$module/$pres"], ';');
fputcsv($out, [], ';');

// Профиль
fputcsv($out, ["ПРОФИЛЬ"], ';');
if ($profile) {
  foreach ($profile as $k => $v) {
    fputcsv($out, [$k, (string)$v], ';');
  }
} else {
  fputcsv($out, ["Профиль не найден"], ';');
}
fputcsv($out, [], ';');

// Оценки
fputcsv($out, ["ОЦЕНИВАНИЯ"], ';');
fputcsv($out, ["id_assessment","assessment_type","deadline_day","weight","submitted_day","score"], ';');
foreach ($assess as $a) {
  fputcsv($out, [
    $a['id_assessment'],
    $a['assessment_type'],
    $a['date_day'],
    $a['weight'],
    $a['date_submitted'],
    $a['score_num'],
  ], ';');
}
fputcsv($out, [], ';');

// Активность
fputcsv($out, ["АКТИВНОСТЬ ПО ТИПАМ"], ';');
fputcsv($out, ["activity_type","clicks"], ';');
foreach ($types as $t) {
  fputcsv($out, [$t['activity_type'], $t['clicks']], ';');
}

fclose($out);
exit;
