<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$id = $_SESSION['id_student'] ?? null;
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'Academic') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="/academic/public/me">Student LA</a>
    <div class="navbar-text text-white ms-auto">
      <?php if ($id): ?>
        ID: <b><?= (int)$id ?></b> · <a class="text-white" href="/academic/public/logout">Выйти</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container my-4">
