<?php
require __DIR__ . '/../app/lib/db.php';
require __DIR__ . '/../app/lib/auth.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// если проект лежит в подпапке /academic/public, то URI будет /academic/public/...
// вырежем префикс до /public
$pos = strpos($uri, '/public');
if ($pos !== false) {
  $uri = substr($uri, $pos + strlen('/public'));
  if ($uri === '') $uri = '/';
}

$routes = [
  '/' => 'login',
  '/login' => 'login',
  '/me' => 'me',
  '/me/course' => 'course',
  '/logout' => 'logout',
];

$page = $routes[$uri] ?? null;

if ($page === null) {
  http_response_code(404);
  echo "404 Not Found";
  exit;
}

if ($page === 'logout') {
  logout();
  header('Location: /academic/public/login');
  exit;
}

require __DIR__ . '/../app/pages/' . $page . '.php';
