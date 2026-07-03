<?php
require_once __DIR__ . '/config/database.php';

$nuevaClave = 'password';
$nuevoHash = password_hash($nuevaClave, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE usuarios 
    SET password_hash = :password_hash
    WHERE usuario = 'admin'
");

$stmt->execute([
    ':password_hash' => $nuevoHash
]);

echo "<h3>Contraseña del admin actualizada correctamente</h3>";
echo "<p>Usuario: <strong>admin</strong></p>";
echo "<p>Contraseña: <strong>password</strong></p>";
echo "<p>Hash generado:</p>";
echo "<pre>" . htmlspecialchars($nuevoHash) . "</pre>";
