<?php
require_once __DIR__ . '/config/database.php';

echo "Conexión correcta a la base de datos<br>";

try {
    $stmt = $pdo->query("SELECT DATABASE() AS db_actual");
    $row = $stmt->fetch();
    echo "Base de datos actual: " . $row['db_actual'] . "<br>";

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM usuarios");
    $row = $stmt->fetch();
    echo "Usuarios registrados: " . $row['total'] . "<br>";

    $stmt = $pdo->query("
        SELECT u.id, u.usuario, u.nombre, u.estado, r.nombre AS rol
        FROM usuarios u
        INNER JOIN roles r ON r.id = u.rol_id
    ");

    echo "<pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
} catch (Exception $e) {
    echo "Error en consulta: " . $e->getMessage();
}
