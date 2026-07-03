<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$producto_id = $_POST['producto_id'] ?? null;
$caja_id = $_POST['caja_id'] ?? null;
$ubicacion_id = $_POST['ubicacion_id'] ?? null;
$tipo_alerta = trim($_POST['tipo_alerta'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$usuario_id = $_SESSION['usuario_id'];

if ($tipo_alerta === '' || $descripcion === '') {
    header('Location: ../pages/buscar.php');
    exit;
}

$producto_id = $producto_id !== '' ? $producto_id : null;
$caja_id = $caja_id !== '' ? $caja_id : null;
$ubicacion_id = $ubicacion_id !== '' ? $ubicacion_id : null;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO alertas (
            producto_id,
            caja_id,
            ubicacion_id,
            tipo_alerta,
            descripcion,
            estado,
            creado_por
        ) VALUES (
            :producto_id,
            :caja_id,
            :ubicacion_id,
            :tipo_alerta,
            :descripcion,
            'Pendiente',
            :creado_por
        )
    ");

    $stmt->execute([
        ':producto_id' => $producto_id,
        ':caja_id' => $caja_id,
        ':ubicacion_id' => $ubicacion_id,
        ':tipo_alerta' => $tipo_alerta,
        ':descripcion' => $descripcion,
        ':creado_por' => $usuario_id
    ]);

    $alerta_id = $pdo->lastInsertId();

    if ($producto_id) {
        $stmtUpdate = $pdo->prepare("
            UPDATE productos
            SET estado = 'Alerta pendiente',
                actualizado_por = :usuario_id
            WHERE id = :producto_id
        ");

        $stmtUpdate->execute([
            ':usuario_id' => $usuario_id,
            ':producto_id' => $producto_id
        ]);
    }

    $stmtMov = $pdo->prepare("
        INSERT INTO movimientos (
            tipo_movimiento,
            producto_id,
            caja_origen_id,
            ubicacion_origen_id,
            alerta_id,
            usuario_id,
            descripcion
        ) VALUES (
            'Alerta creada',
            :producto_id,
            :caja_id,
            :ubicacion_id,
            :alerta_id,
            :usuario_id,
            :descripcion
        )
    ");

    $stmtMov->execute([
        ':producto_id' => $producto_id,
        ':caja_id' => $caja_id,
        ':ubicacion_id' => $ubicacion_id,
        ':alerta_id' => $alerta_id,
        ':usuario_id' => $usuario_id,
        ':descripcion' => 'Alerta creada: ' . $tipo_alerta . '. ' . $descripcion
    ]);

    $pdo->commit();

    header('Location: ../pages/alertas.php?msg=alerta_creada');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die('Error al crear alerta: ' . $e->getMessage());
}
