<?php

$host = $_SERVER['HTTP_HOST'] ?? '';

$isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');

define('BASE_URL', $isLocal ? '/almacen-ubicaciones' : '');