<?php
$lines = file('storage/logs/laravel.log');
$lines = file('storage/logs/laravel.log');
$log = implode('', array_slice($lines, -500));
preg_match_all('/\{"error":\{.*\}\}/', $log, $matches);
if (!empty($matches[0])) { print_r(end($matches[0])); } else { echo "No Facebook error found."; }
