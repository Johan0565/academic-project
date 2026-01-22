<?php
// Возвращает JSON: [11391, 11392, ...]
header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
$q = preg_replace('/\D+/', '', $q); // оставляем только цифры

if ($q === '') {
  echo json_encode([]);
  exit;
}

$sql = "
SELECT DISTINCT si.id_student
FROM studentinfo si
LEFT JOIN app_users au ON au.id_student = si.id_student
WHERE au.id_student IS NULL
  AND CAST(si.id_student AS CHAR) LIKE CONCAT(?, '%')
ORDER BY si.id_student
LIMIT 10
";
$stmt = db()->prepare($sql);
$stmt->execute([$q]);

echo json_encode(array_map(fn($r) => (int)$r['id_student'], $stmt->fetchAll()));
