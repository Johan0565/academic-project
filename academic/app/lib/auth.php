<?php

function require_login(): void {
  session_start();
  if (!isset($_SESSION['id_student'])) {
    header('Location: /academic/public/index.php?page=login');
    exit;
  }
}

function login_student(int $id): void {
  session_start();
  $_SESSION['id_student'] = $id;
}

function logout(): void {
  session_start();
  session_destroy();
}
