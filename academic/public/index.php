<?php
require __DIR__ . '/../app/lib/db.php';
require __DIR__ . '/../app/lib/auth.php';

$page = $_GET['page'] ?? 'login';

$allowed = ['login', 'me', 'course', 'logout'];
if (!in_array($page, $allowed, true)) $page = 'login';

if ($page === 'logout') {
  logout();
  header('Location: index.php?page=login');
  exit;
}

require __DIR__ . '/../app/pages/' . $page . '.php';
