<?php

function session_init(): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
}

function login_user(array $userRow): void {
  session_init();
  $_SESSION['role'] = 'student';
  $_SESSION['user_id'] = (int)$userRow['id'];
  $_SESSION['id_student'] = (int)$userRow['id_student'];
  $_SESSION['email'] = (string)$userRow['email'];
}

function login_admin(): void {
  session_init();
  $_SESSION['role'] = 'admin';
  $_SESSION['user_id'] = null;
  $_SESSION['id_student'] = null;
  $_SESSION['email'] = 'admin';
}

function is_logged_in(): bool {
  session_init();
  return isset($_SESSION['role']);
}

function is_admin(): bool {
  session_init();
  return ($_SESSION['role'] ?? null) === 'admin';
}

function require_login(): void {
  if (!is_logged_in()) {
    header('Location: /academic/public/login');
    exit;
  }
}

function require_student(): void {
  require_login();
  if (is_admin()) {
    http_response_code(403);
    echo "403 Forbidden (только для студента)";
    exit;
  }
}

function require_admin(): void {
  require_login();
  if (!is_admin()) {
    http_response_code(403);
    echo "403 Forbidden (только для администратора)";
    exit;
  }
}

function logout(): void {
  session_init();
  $_SESSION = [];
  session_destroy();
}
function csrf_token(): string {
  session_init();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_validate(): void {
  session_init();
  $token = $_POST['csrf_token'] ?? '';
  if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(400);
    echo "Bad Request (CSRF)";
    exit;
  }
}
