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

$id = trim($_POST['id'] ?? '');
$codigoProducto = strtoupper(trim($_POST['codigo_producto'] ?? ''));
$codigoAlterno = strtoupper(trim($_POST['codigo_alterno'] ?? ''));
$descripcion = trim($_POST['descripcion'] ?? '');
$tipo = trim($_POST['tipo'] ?? '');
$cajaId = trim($_POST['caja_id'] ?? '');
$ubicacionId = trim($_POST['ubicacion_id'] ?? '');
$packingListId = trim($_POST['packing_list_id'] ?? '');
$estado = trim($_POST['estado'] ?? 'Disponible');
$observacion = trim($_POST['observacion'] ?? '');

$tiposPermitidos = ['Suelto', 'En caja'];
$estadosPermitidos = [
    'Disponible',
    'Alerta pendiente',
    'Retirado',
    'Reubicado',
    'No encontrado',
    'Revisar',
    'Inactivo'
];

if ($codigoProducto === '') {
    header('Location: ../pages/productos.php?error=faltan_datos');
    exit;
}

if (!in_array($tipo, $tiposPermitidos)) {
    header('Location: ../pages/productos.php?error=tipo_invalido');
    exit;
}

if (!in_array($estado, $estadosPermitidos)) {
    $estado = 'Disponible';
}

$cajaId = $cajaId !== '' ? $cajaId : null;
$ubicacionId = $ubicacionId !== '' ? $ubicacionId : null;
$packingListId = $packingListId !== '' ? $packingListId : null;

if ($tipo === 'En caja' && !$cajaId) {
    header('Location: ../pages/productos.php?error=caja_requerida');
    exit;
}

if ($tipo === 'Suelto') {
    $cajaId = null;

    if (!$ubicacionId) {
        header('Location: ../pages/productos.php?error=ubicacion_requerida');
        exit;
    }
}

/*
    Si el producto está en caja y no se seleccionó ubicación,
    intentamos tomar la ubicación de la caja.
*/
try {
    if ($tipo === 'En caja' && $cajaId && !$ubicacionId) {
        $stmtCajaUbicacion = $pdo->prepare("
            SELECT ubicacion_id
            FROM cajas
            WHERE id = :id
            LIMIT 1
        ");
        $stmtCajaUbicacion->execute([
            ':id' => $cajaId
        ]);

        $ubicacionCaja = $stmtCajaUbicacion->fetchColumn();

        if ($ubicacionCaja) {
            $ubicacionId = $ubicacionCaja;
        }
    }

    $pdo->beginTransaction();

    if ($id === '') {
        // Crear producto

        $stmt = $pdo->prepare("
            INSERT INTO productos (
                codigo_producto,
                codigo_alterno,
                descripcion,
                tipo,
                caja_id,
                ubicacion_id,
                packing_list_id,
                estado,
                observacion,
                creado_por,
                actualizado_por
            ) VALUES (
                :codigo_producto,
                :codigo_alterno,
                :descripcion,
                :tipo,
                :caja_id,
                :ubicacion_id,
                :packing_list_id,
                :estado,
                :observacion,
                :creado_por,
                :actualizado_por
            )
        ");

        $stmt->execute([
            ':codigo_producto' => $codigoProducto,
            ':codigo_alterno' => $codigoAlterno ?: null,
            ':descripcion' => $descripcion ?: null,
            ':tipo' => $tipo,
            ':caja_id' => $cajaId,
            ':ubicacion_id' => $ubicacionId,
            ':packing_list_id' => $packingListId,
            ':estado' => $estado,
            ':observacion' => $observacion ?: null,
            ':creado_por' => $usuario_id,
            ':actualizado_por' => $usuario_id
        ]);

        $productoId = $pdo->lastInsertId();

        if ($ubicacionId && in_array($estado, ['Disponible', 'Alerta pendiente', 'Revisar'])) {
            $stmtUb = $pdo->prepare("
                UPDATE ubicaciones
                SET estado = 'Ocupado'
                WHERE id = :id
            ");
            $stmtUb->execute([':id' => $ubicacionId]);
        }

        $stmtMov = $pdo->prepare("
            INSERT INTO movimientos (
                tipo_movimiento,
                producto_id,
                caja_destino_id,
                ubicacion_destino_id,
                usuario_id,
                descripcion
            ) VALUES (
                'Ingreso',
                :producto_id,
                :caja_destino_id,
                :ubicacion_destino_id,
                :usuario_id,
                :descripcion
            )
        ");

        $stmtMov->execute([
            ':producto_id' => $productoId,
            ':caja_destino_id' => $cajaId,
            ':ubicacion_destino_id' => $ubicacionId,
            ':usuario_id' => $usuario_id,
            ':descripcion' => 'Producto creado: ' . $codigoProducto
        ]);

        $pdo->commit();

        header('Location: ../pages/productos.php?msg=creado');
        exit;

    } else {
        // Editar producto

        $stmtActual = $pdo->prepare("
            SELECT *
            FROM productos
            WHERE id = :id
            LIMIT 1
        ");
        $stmtActual->execute([
            ':id' => $id
        ]);
        $productoActual = $stmtActual->fetch();

        if (!$productoActual) {
            $pdo->rollBack();
            header('Location: ../pages/productos.php?error=general');
            exit;
        }

        $cajaAnteriorId = $productoActual['caja_id'];
        $ubicacionAnteriorId = $productoActual['ubicacion_id'];
        $estadoAnterior = $productoActual['estado'];
        $tipoAnterior = $productoActual['tipo'];
        $codigoAnterior = $productoActual['codigo_producto'];

        $stmt = $pdo->prepare("
            UPDATE productos
            SET codigo_producto = :codigo_producto,
                codigo_alterno = :codigo_alterno,
                descripcion = :descripcion,
                tipo = :tipo,
                caja_id = :caja_id,
                ubicacion_id = :ubicacion_id,
                packing_list_id = :packing_list_id,
                estado = :estado,
                observacion = :observacion,
                actualizado_por = :actualizado_por
            WHERE id = :id
        ");

        $stmt->execute([
            ':codigo_producto' => $codigoProducto,
            ':codigo_alterno' => $codigoAlterno ?: null,
            ':descripcion' => $descripcion ?: null,
            ':tipo' => $tipo,
            ':caja_id' => $cajaId,
            ':ubicacion_id' => $ubicacionId,
            ':packing_list_id' => $packingListId,
            ':estado' => $estado,
            ':observacion' => $observacion ?: null,
            ':actualizado_por' => $usuario_id,
            ':id' => $id
        ]);

        if ($ubicacionId && in_array($estado, ['Disponible', 'Alerta pendiente', 'Revisar'])) {
            $stmtUbNueva = $pdo->prepare("
                UPDATE ubicaciones
                SET estado = 'Ocupado'
                WHERE id = :id
            ");
            $stmtUbNueva->execute([':id' => $ubicacionId]);
        }

        /*
            Si la ubicación anterior quedó sin cajas activas y sin productos sueltos,
            se puede liberar.
        */
        if (!empty($ubicacionAnteriorId) && (string)$ubicacionAnteriorId !== (string)$ubicacionId) {
            $stmtCajasActivas = $pdo->prepare("
                SELECT COUNT(*)
                FROM cajas
                WHERE ubicacion_id = :ubicacion_id
                  AND estado IN ('Activa', 'Revisar')
            ");
            $stmtCajasActivas->execute([
                ':ubicacion_id' => $ubicacionAnteriorId
            ]);
            $totalCajasActivas = (int)$stmtCajasActivas->fetchColumn();

            $stmtProductosSueltos = $pdo->prepare("
                SELECT COUNT(*)
                FROM productos
                WHERE ubicacion_id = :ubicacion_id
                  AND caja_id IS NULL
                  AND estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
            ");
            $stmtProductosSueltos->execute([
                ':ubicacion_id' => $ubicacionAnteriorId
            ]);
            $totalProductosSueltos = (int)$stmtProductosSueltos->fetchColumn();

            if ($totalCajasActivas === 0 && $totalProductosSueltos === 0) {
                $stmtLiberar = $pdo->prepare("
                    UPDATE ubicaciones
                    SET estado = 'Libre'
                    WHERE id = :ubicacion_id
                ");
                $stmtLiberar->execute([
                    ':ubicacion_id' => $ubicacionAnteriorId
                ]);
            }
        }

        $tipoMovimiento = 'Correccion';

        if ((string)$cajaAnteriorId !== (string)$cajaId || (string)$ubicacionAnteriorId !== (string)$ubicacionId) {
            $tipoMovimiento = 'Reubicacion';
        }

        if ($estado === 'Retirado' && $estadoAnterior !== 'Retirado') {
            $tipoMovimiento = 'Retiro';
        }

        $descripcionMovimiento = 'Producto actualizado: ' . $codigoAnterior . ' → ' . $codigoProducto . '.';

        if ($tipoAnterior !== $tipo) {
            $descripcionMovimiento .= ' Tipo cambiado de ' . $tipoAnterior . ' a ' . $tipo . '.';
        }

        if ((string)$cajaAnteriorId !== (string)$cajaId) {
            $descripcionMovimiento .= ' Caja modificada.';
        }

        if ((string)$ubicacionAnteriorId !== (string)$ubicacionId) {
            $descripcionMovimiento .= ' Ubicación modificada.';
        }

        if ($estadoAnterior !== $estado) {
            $descripcionMovimiento .= ' Estado cambiado de ' . $estadoAnterior . ' a ' . $estado . '.';
        }

        $stmtMov = $pdo->prepare("
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
                :tipo_movimiento,
                :producto_id,
                :caja_origen_id,
                :caja_destino_id,
                :ubicacion_origen_id,
                :ubicacion_destino_id,
                :usuario_id,
                :descripcion
            )
        ");

        $stmtMov->execute([
            ':tipo_movimiento' => $tipoMovimiento,
            ':producto_id' => $id,
            ':caja_origen_id' => $cajaAnteriorId,
            ':caja_destino_id' => $cajaId,
            ':ubicacion_origen_id' => $ubicacionAnteriorId,
            ':ubicacion_destino_id' => $ubicacionId,
            ':usuario_id' => $usuario_id,
            ':descripcion' => $descripcionMovimiento
        ]);

        $pdo->commit();

        header('Location: ../pages/productos.php?msg=actualizado');
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ../pages/productos.php?error=general');
    exit;
}