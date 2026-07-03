<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/permisos.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!puedeModificar()) {
    header('Location: ../pages/buscar.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

$cajaOrigenId = $_POST['caja_origen_id'] ?? '';
$cajaDestinoId = $_POST['caja_destino_id'] ?? '';
$observacion = trim($_POST['observacion'] ?? '');
$liberarUbicacion = isset($_POST['liberar_ubicacion']);

if ($cajaOrigenId === '' || $cajaDestinoId === '') {
    header('Location: ../pages/consolidar-cajas.php?error=faltan_datos');
    exit;
}

if ($cajaOrigenId == $cajaDestinoId) {
    header('Location: ../pages/consolidar-cajas.php?error=misma_caja');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtOrigen = $pdo->prepare("
        SELECT 
            c.*,
            u.codigo AS ubicacion_codigo
        FROM cajas c
        LEFT JOIN ubicaciones u ON u.id = c.ubicacion_id
        WHERE c.id = :id
        LIMIT 1
    ");
    $stmtOrigen->execute([':id' => $cajaOrigenId]);
    $cajaOrigen = $stmtOrigen->fetch();

    $stmtDestino = $pdo->prepare("
        SELECT 
            c.*,
            u.codigo AS ubicacion_codigo
        FROM cajas c
        LEFT JOIN ubicaciones u ON u.id = c.ubicacion_id
        WHERE c.id = :id
        LIMIT 1
    ");
    $stmtDestino->execute([':id' => $cajaDestinoId]);
    $cajaDestino = $stmtDestino->fetch();

    if (!$cajaOrigen || !$cajaDestino) {
        $pdo->rollBack();
        header('Location: ../pages/consolidar-cajas.php?error=caja_no_encontrada');
        exit;
    }

    if (!in_array($cajaOrigen['estado'], ['Activa', 'Revisar'])) {
        $pdo->rollBack();
        header('Location: ../pages/consolidar-cajas.php?error=caja_origen_invalida');
        exit;
    }

    if (!in_array($cajaDestino['estado'], ['Activa', 'Revisar'])) {
        $pdo->rollBack();
        header('Location: ../pages/consolidar-cajas.php?error=caja_destino_invalida');
        exit;
    }

    $ubicacionOrigenId = $cajaOrigen['ubicacion_id'];
    $ubicacionDestinoId = $cajaDestino['ubicacion_id'];

    /*
        Mueve todos los productos disponibles o pendientes/revisión de la caja origen
        hacia la caja destino.
    */
    $stmtProductos = $pdo->prepare("
        SELECT id
        FROM productos
        WHERE caja_id = :caja_origen_id
          AND estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
    ");
    $stmtProductos->execute([
        ':caja_origen_id' => $cajaOrigenId
    ]);

    $productos = $stmtProductos->fetchAll();
    $totalProductosMovidos = count($productos);

    $stmtMoverProductos = $pdo->prepare("
        UPDATE productos
        SET caja_id = :caja_destino_id,
            ubicacion_id = :ubicacion_destino_id,
            estado = CASE 
                WHEN estado = 'Alerta pendiente' THEN 'Alerta pendiente'
                ELSE 'Disponible'
            END,
            observacion = CONCAT(
                COALESCE(observacion, ''),
                CASE 
                    WHEN COALESCE(observacion, '') = '' THEN ''
                    ELSE ' | '
                END,
                :observacion_producto
            ),
            actualizado_por = :usuario_id
        WHERE caja_id = :caja_origen_id
          AND estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
    ");

    $stmtMoverProductos->execute([
        ':caja_destino_id' => $cajaDestinoId,
        ':ubicacion_destino_id' => $ubicacionDestinoId,
        ':observacion_producto' => 'Consolidado desde caja ' . $cajaOrigen['numero_caja'] . ' hacia caja ' . $cajaDestino['numero_caja'],
        ':usuario_id' => $usuario_id,
        ':caja_origen_id' => $cajaOrigenId
    ]);

    /*
        Marca la caja origen como consolidada.
    */
    $observacionCajaOrigen = 'Caja consolidada en caja ' . $cajaDestino['numero_caja'];

    if ($observacion !== '') {
        $observacionCajaOrigen .= '. ' . $observacion;
    }

    $stmtActualizarCajaOrigen = $pdo->prepare("
        UPDATE cajas
        SET estado = 'Consolidada',
            caja_destino_id = :caja_destino_id,
            observacion = CONCAT(
                COALESCE(observacion, ''),
                CASE 
                    WHEN COALESCE(observacion, '') = '' THEN ''
                    ELSE ' | '
                END,
                :observacion
            ),
            actualizado_por = :usuario_id
        WHERE id = :caja_origen_id
    ");

    $stmtActualizarCajaOrigen->execute([
        ':caja_destino_id' => $cajaDestinoId,
        ':observacion' => $observacionCajaOrigen,
        ':usuario_id' => $usuario_id,
        ':caja_origen_id' => $cajaOrigenId
    ]);

    /*
        Registra movimiento general de consolidación.
    */
    $descripcionMovimiento = 'Consolidación de caja ' . $cajaOrigen['numero_caja'] .
        ' hacia caja ' . $cajaDestino['numero_caja'] .
        '. Productos movidos: ' . $totalProductosMovidos . '.';

    if ($observacion !== '') {
        $descripcionMovimiento .= ' Observación: ' . $observacion;
    }

    $stmtMovimiento = $pdo->prepare("
        INSERT INTO movimientos (
            tipo_movimiento,
            caja_origen_id,
            caja_destino_id,
            ubicacion_origen_id,
            ubicacion_destino_id,
            usuario_id,
            descripcion
        ) VALUES (
            'Consolidacion',
            :caja_origen_id,
            :caja_destino_id,
            :ubicacion_origen_id,
            :ubicacion_destino_id,
            :usuario_id,
            :descripcion
        )
    ");

    $stmtMovimiento->execute([
        ':caja_origen_id' => $cajaOrigenId,
        ':caja_destino_id' => $cajaDestinoId,
        ':ubicacion_origen_id' => $ubicacionOrigenId,
        ':ubicacion_destino_id' => $ubicacionDestinoId,
        ':usuario_id' => $usuario_id,
        ':descripcion' => $descripcionMovimiento
    ]);

    /*
        También registra movimiento por cada producto movido.
        Esto ayuda a tener auditoría detallada.
    */
    if ($totalProductosMovidos > 0) {
        $stmtMovimientoProducto = $pdo->prepare("
            INSERT INTO movimientos (
                tipo_movimiento,
                producto_id,
                caja_origen_id,
                caja_destino_id,
                ubicacion_origen_id,
                ubicacion_destino_id,
                usuario_id,
                descripcion
            ) VALUES (
                'Reubicacion',
                :producto_id,
                :caja_origen_id,
                :caja_destino_id,
                :ubicacion_origen_id,
                :ubicacion_destino_id,
                :usuario_id,
                :descripcion
            )
        ");

        foreach ($productos as $producto) {
            $stmtMovimientoProducto->execute([
                ':producto_id' => $producto['id'],
                ':caja_origen_id' => $cajaOrigenId,
                ':caja_destino_id' => $cajaDestinoId,
                ':ubicacion_origen_id' => $ubicacionOrigenId,
                ':ubicacion_destino_id' => $ubicacionDestinoId,
                ':usuario_id' => $usuario_id,
                ':descripcion' => 'Producto movido por consolidación de caja ' . $cajaOrigen['numero_caja'] . ' hacia caja ' . $cajaDestino['numero_caja']
            ]);
        }
    }

    /*
        Liberar ubicación origen solo si:
        - El check está marcado.
        - La caja origen tenía ubicación.
        - No queda ninguna caja activa/revisar en esa ubicación.
        - No queda ningún producto suelto disponible en esa ubicación.
    */
    if ($liberarUbicacion && !empty($ubicacionOrigenId)) {
        $stmtCajasEnUbicacion = $pdo->prepare("
            SELECT COUNT(*)
            FROM cajas
            WHERE ubicacion_id = :ubicacion_id
              AND estado IN ('Activa', 'Revisar')
        ");
        $stmtCajasEnUbicacion->execute([
            ':ubicacion_id' => $ubicacionOrigenId
        ]);
        $cajasActivasEnUbicacion = (int)$stmtCajasEnUbicacion->fetchColumn();

        $stmtProductosSueltos = $pdo->prepare("
            SELECT COUNT(*)
            FROM productos
            WHERE ubicacion_id = :ubicacion_id
              AND caja_id IS NULL
              AND estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
        ");
        $stmtProductosSueltos->execute([
            ':ubicacion_id' => $ubicacionOrigenId
        ]);
        $productosSueltosEnUbicacion = (int)$stmtProductosSueltos->fetchColumn();

        if ($cajasActivasEnUbicacion === 0 && $productosSueltosEnUbicacion === 0) {
            $stmtLiberarUbicacion = $pdo->prepare("
                UPDATE ubicaciones
                SET estado = 'Libre',
                    observacion = CONCAT(
                        COALESCE(observacion, ''),
                        CASE 
                            WHEN COALESCE(observacion, '') = '' THEN ''
                            ELSE ' | '
                        END,
                        :observacion
                    )
                WHERE id = :ubicacion_id
            ");

            $stmtLiberarUbicacion->execute([
                ':observacion' => 'Ubicación liberada por consolidación de caja ' . $cajaOrigen['numero_caja'],
                ':ubicacion_id' => $ubicacionOrigenId
            ]);

            $stmtMovimientoLiberacion = $pdo->prepare("
                INSERT INTO movimientos (
                    tipo_movimiento,
                    caja_origen_id,
                    caja_destino_id,
                    ubicacion_origen_id,
                    ubicacion_destino_id,
                    usuario_id,
                    descripcion
                ) VALUES (
                    'Caja desechada',
                    :caja_origen_id,
                    :caja_destino_id,
                    :ubicacion_origen_id,
                    :ubicacion_destino_id,
                    :usuario_id,
                    :descripcion
                )
            ");

            $stmtMovimientoLiberacion->execute([
                ':caja_origen_id' => $cajaOrigenId,
                ':caja_destino_id' => $cajaDestinoId,
                ':ubicacion_origen_id' => $ubicacionOrigenId,
                ':ubicacion_destino_id' => $ubicacionDestinoId,
                ':usuario_id' => $usuario_id,
                ':descripcion' => 'Ubicación origen liberada al consolidar caja ' . $cajaOrigen['numero_caja'] . ' en caja ' . $cajaDestino['numero_caja']
            ]);
        }
    }

    $pdo->commit();

    header('Location: ../pages/consolidar-cajas.php?msg=ok');
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Para producción podrías redirigir sin mostrar detalle.
    // die('Error: ' . $e->getMessage());
    header('Location: ../pages/consolidar-cajas.php?error=general');
    exit;
}
