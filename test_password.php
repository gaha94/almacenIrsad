<?php

$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
$password = 'password';

if (password_verify($password, $hash)) {
    echo "Contraseña correcta";
} else {
    echo "Contraseña incorrecta";
}
