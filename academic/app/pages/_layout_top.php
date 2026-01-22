<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();
$role = $_SESSION['role'] ?? null;
$email = $_SESSION['email'] ?? null;
$idStudent = $_SESSION['id_student'] ?? null;
?>
<!doctype html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'Student LA') ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="/academic/public/<?= ($role === 'admin') ? 'admin/requests' : 'me' ?>">
        Student LA
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
        aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarContent">
        <!-- Меню по центру -->
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
          <?php if ($role === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link text-white px-3" href="/academic/public/admin/requests">Заявки</a>
            </li>
            <li class="nav-item">
              <a class="nav-link text-white px-3" href="/academic/public/admin/users">Пользователи</a>
            </li>
            <li class="nav-item">
              <a class="nav-link text-white px-3" href="/academic/public/analytics">Аналитика</a>
            </li>
             
          <?php endif; ?>
        </ul>

        <!-- Правая часть -->
        <div class="d-flex align-items-center text-white">
          <?php if ($role): ?>
            <?php if ($role === 'student'): ?>
              <span class="me-3">
                <?= htmlspecialchars((string) $email) ?>
                <span class="opacity-50 mx-1">|</span>
                ID: <b><?= (int) $idStudent ?></b>
              </span>
              <a class="btn btn-outline-light btn-sm me-2" href="/academic/public/account">Аккаунт</a>
            <?php else: ?>
              <span class="me-3 fw-bold text-uppercase small ls-1 text-warning">Administrator</span>
            <?php endif; ?>
            <a class="btn btn-danger btn-sm" href="/academic/public/logout">Выйти</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <main class="container my-4">