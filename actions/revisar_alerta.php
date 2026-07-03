<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/permisos.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!puedeModificar()) {
    header('Location: ../dashboard.php');
    exit;
}

$id = $_GET['id'] ?? null;
$accion = $_GET['accion'] ?? null;
$usuario_id = $_SESSION['usuario_id'];

if (!$id || !in_array($accion, ['aprobar', 'rechazar'])) {
    header('Location: ../pages/alertas.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM alertas
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$alerta = $stmt->fetch();

if (!$alerta) {
    header('Location: ../pages/alertas.php');
    exit;
}

try {
    $pdo->beginTransaction();

    if ($accion === 'aprobar') {
        $nuevoEstado = 'Aprobada';
        $tipoMovimiento = 'Alerta aprobada';
        $accionTomada = 'Alerta aprobada por supervisor/administrador. Producto marcado como Revisar.';

        if (!empty($alerta['producto_id'])) {
            $stmtProducto = $pdo->prepare("
                UPDATE productos
                SET estado = 'Revisar',
                    actualizado_por = :usuario_id
                WHERE id = :producto_id
            ");
            $stmtProducto->execute([
                ':usuario_id' => $usuario_id,
                ':producto_id' => $alerta['producto_id']
            ]);
        }
    } else {
        $nuevoEstado = 'Rechazada';
        $tipoMovimiento = 'Alerta rechazada';
        $accionTomada = 'Alerta rechazada. Producto vuelve a estado Disponible si estaba en alerta.';

        if (!empty($alerta['producto_id'])) {
            $stmtProducto = $pdo->prepare("
                UPDATE productos
                SET estado = 'Disponible',
                    actualizado_por = :usuario_id
                WHERE id = :producto_id
                  AND estado = 'Alerta pendiente'
            ");
            $stmtProducto->execute([
                ':usuario_id' => $usuario_id,
                ':producto_id' => $alerta['producto_id']
            ]);
        }
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE alertas
        SET estado = :estado,
            revisado_por = :revisado_por,
            fecha_revision = NOW(),
            accion_tomada = :accion_tomada
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        ':estado' => $nuevoEstado,
        ':revisado_por' => $usuario_id,
        ':accion_tomada' => $accionTomada,
        ':id' => $id
    ]);

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
            :tipo_movimiento,
            :producto_id,
            :caja_id,
            :ubicacion_id,
            :alerta_id,
            :usuario_id,
            :descripcion
        )
    ");

    $stmtMov->execute([
        ':tipo_movimiento' => $tipoMovimiento,
        ':producto_id' => $alerta['producto_id'],
        ':caja_id' => $alerta['caja_id'],
        ':ubicacion_id' => $alerta['ubicacion_id'],
        ':alerta_id' => $alerta['id'],
        ':usuario_id' => $usuario_id,
        ':descripcion' => $accionTomada
    ]);

    $pdo->commit();

    header('Location: ../pages/alertas.php');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die('Error al revisar alerta: ' . $e->getMessage());
}
