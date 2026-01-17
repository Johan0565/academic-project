<?php

function route(string $path): string {
  $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  $base = rtrim($path, '/');
  $uri = preg_replace('#^' . preg_quote($base, '#') . '#', '', $uri);
  $uri = $uri ?: '/';
  return $uri;
}
