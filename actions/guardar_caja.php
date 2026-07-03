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
$numeroCaja = strtoupper(trim($_POST['numero_caja'] ?? ''));
$ubicacionId = trim($_POST['ubicacion_id'] ?? '');
$packingListId = trim($_POST['packing_list_id'] ?? '');
$estado = trim($_POST['estado'] ?? 'Activa');
$cajaDestinoId = trim($_POST['caja_destino_id'] ?? '');
$observacion = trim($_POST['observacion'] ?? '');

$estadosPermitidos = ['Activa', 'Consolidada', 'Desechada', 'Vacia', 'Revisar', 'Inactiva'];

if ($numeroCaja === '') {
    header('Location: ../pages/cajas.php?error=faltan_datos');
    exit;
}

if (!in_array($estado, $estadosPermitidos)) {
    $estado = 'Activa';
}

$ubicacionId = $ubicacionId !== '' ? $ubicacionId : null;
$packingListId = $packingListId !== '' ? $packingListId : null;
$cajaDestinoId = $cajaDestinoId !== '' ? $cajaDestinoId : null;

if ($id !== '' && $cajaDestinoId !== null && (int)$id === (int)$cajaDestinoId) {
    header('Location: ../pages/cajas.php?error=misma_caja');
    exit;
}

try {
    $pdo->beginTransaction();

    if ($id === '') {
        // Crear caja

        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM cajas
            WHERE numero_caja = :numero_caja
            LIMIT 1
        ");
        $stmtExiste->execute([
            ':numero_caja' => $numeroCaja
        ]);

        if ($stmtExiste->fetch()) {
            $pdo->rollBack();
            header('Location: ../pages/cajas.php?error=duplicado');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO cajas (
                numero_caja,
                ubicacion_id,
                packing_list_id,
                estado,
                caja_destino_id,
                observacion,
                creado_por,
                actualizado_por
            ) VALUES (
                :numero_caja,
                :ubicacion_id,
                :packing_list_id,
                :estado,
                :caja_destino_id,
                :observacion,
                :creado_por,
                :actualizado_por
            )
        ");

        $stmt->execute([
            ':numero_caja' => $numeroCaja,
            ':ubicacion_id' => $ubicacionId,
            ':packing_list_id' => $packingListId,
            ':estado' => $estado,
            ':caja_destino_id' => $cajaDestinoId,
            ':observacion' => $observacion ?: null,
            ':creado_por' => $usuario_id,
            ':actualizado_por' => $usuario_id
        ]);

        $cajaId = $pdo->lastInsertId();

        if ($ubicacionId && in_array($estado, ['Activa', 'Revisar'])) {
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
                caja_destino_id,
                ubicacion_destino_id,
                usuario_id,
                descripcion
            ) VALUES (
                'Ingreso',
                :caja_destino_id,
                :ubicacion_destino_id,
                :usuario_id,
                :descripcion
            )
        ");

        $stmtMov->execute([
            ':caja_destino_id' => $cajaId,
            ':ubicacion_destino_id' => $ubicacionId,
            ':usuario_id' => $usuario_id,
            ':descripcion' => 'Caja creada: ' . $numeroCaja
        ]);

        $pdo->commit();

        header('Location: ../pages/cajas.php?msg=creada');
        exit;

    } else {
        // Editar caja

        $stmtActual = $pdo->prepare("
            SELECT *
            FROM cajas
            WHERE id = :id
            LIMIT 1
        ");
        $stmtActual->execute([
            ':id' => $id
        ]);
        $cajaActual = $stmtActual->fetch();

        if (!$cajaActual) {
            $pdo->rollBack();
            header('Location: ../pages/cajas.php?error=general');
            exit;
        }

        $stmtDuplicado = $pdo->prepare("
            SELECT id
            FROM cajas
            WHERE numero_caja = :numero_caja
              AND id <> :id
            LIMIT 1
        ");
        $stmtDuplicado->execute([
            ':numero_caja' => $numeroCaja,
            ':id' => $id
        ]);

        if ($stmtDuplicado->fetch()) {
            $pdo->rollBack();
            header('Location: ../pages/cajas.php?error=duplicado');
            exit;
        }

        $ubicacionAnteriorId = $cajaActual['ubicacion_id'];
        $estadoAnterior = $cajaActual['estado'];

        $stmt = $pdo->prepare("
            UPDATE cajas
            SET numero_caja = :numero_caja,
                ubicacion_id = :ubicacion_id,
                packing_list_id = :packing_list_id,
                estado = :estado,
                caja_destino_id = :caja_destino_id,
                observacion = :observacion,
                actualizado_por = :actualizado_por
            WHERE id = :id
        ");

        $stmt->execute([
            ':numero_caja' => $numeroCaja,
            ':ubicacion_id' => $ubicacionId,
            ':packing_list_id' => $packingListId,
            ':estado' => $estado,
            ':caja_destino_id' => $cajaDestinoId,
            ':observacion' => $observacion ?: null,
            ':actualizado_por' => $usuario_id,
            ':id' => $id
        ]);

        /*
            Si cambió la ubicación de la caja, también actualizamos la ubicación
            de los productos que están dentro de esa caja.
        */
        if ((string)$ubicacionAnteriorId !== (string)$ubicacionId) {
            $stmtProductos = $pdo->prepare("
                UPDATE productos
                SET ubicacion_id = :ubicacion_id,
                    actualizado_por = :actualizado_por
                WHERE caja_id = :caja_id
                  AND estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
            ");
            $stmtProductos->execute([
                ':ubicacion_id' => $ubicacionId,
                ':actualizado_por' => $usuario_id,
                ':caja_id' => $id
            ]);
        }

        /*
            Marcar nueva ubicación como ocupada si la caja queda activa.
        */
        if ($ubicacionId && in_array($estado, ['Activa', 'Revisar'])) {
            $stmtUbNueva = $pdo->prepare("
                UPDATE ubicaciones
                SET estado = 'Ocupado'
                WHERE id = :id
            ");
            $stmtUbNueva->execute([':id' => $ubicacionId]);
        }

        /*
            Si la ubicación anterior quedó sin cajas activas y sin productos sueltos,
            se puede marcar como Libre.
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

        /*
            Si se marca como Consolidada, Desechada, Vacía o Inactiva,
            ya no debería quedar como caja activa ocupando ubicación.
            Si no hay más cosas ahí, se libera la ubicación.
        */
        if (in_array($estado, ['Consolidada', 'Desechada', 'Vacia', 'Inactiva']) && !empty($ubicacionId)) {
            $stmtCajasActivasNueva = $pdo->prepare("
                SELECT COUNT(*)
                FROM cajas
                WHERE ubicacion_id = :ubicacion_id
                  AND estado IN ('Activa', 'Revisar')
            ");
            $stmtCajasActivasNueva->execute([
                ':ubicacion_id' => $ubicacionId
            ]);
            $totalCajasActivasNueva = (int)$stmtCajasActivasNueva->fetchColumn();

            $stmtProductosSueltosNueva = $pdo->prepare("
                SELECT COUNT(*)
                FROM productos
                WHERE ubicacion_id = :ubicacion_id
                  AND caja_id IS NULL
                  AND estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
            ");
            $stmtProductosSueltosNueva->execute([
                ':ubicacion_id' => $ubicacionId
            ]);
            $totalProductosSueltosNueva = (int)$stmtProductosSueltosNueva->fetchColumn();

            if ($totalCajasActivasNueva === 0 && $totalProductosSueltosNueva === 0) {
                $stmtLiberarNueva = $pdo->prepare("
                    UPDATE ubicaciones
                    SET estado = 'Libre'
                    WHERE id = :ubicacion_id
                ");
                $stmtLiberarNueva->execute([
                    ':ubicacion_id' => $ubicacionId
                ]);
            }
        }

        $descripcion = 'Caja actualizada: ' . $cajaActual['numero_caja'] . ' → ' . $numeroCaja;

        if ((string)$ubicacionAnteriorId !== (string)$ubicacionId) {
            $descripcion .= '. Ubicación modificada.';
        }

        if ($estadoAnterior !== $estado) {
            $descripcion .= ' Estado cambiado de ' . $estadoAnterior . ' a ' . $estado . '.';
        }

        $stmtMov = $pdo->prepare("
            INSERT INTO movimientos (
                tipo_movimiento,
                caja_origen_id,
                caja_destino_id,
                ubicacion_origen_id,
                ubicacion_destino_id,
                usuario_id,
                descripcion
            ) VALUES (
                'Correccion',
                :caja_origen_id,
                :caja_destino_id,
                :ubicacion_origen_id,
                :ubicacion_destino_id,
                :usuario_id,
                :descripcion
            )
        ");

        $stmtMov->execute([
            ':caja_origen_id' => $id,
            ':caja_destino_id' => $cajaDestinoId,
            ':ubicacion_origen_id' => $ubicacionAnteriorId,
            ':ubicacion_destino_id' => $ubicacionId,
            ':usuario_id' => $usuario_id,
            ':descripcion' => $descripcion
        ]);

        $pdo->commit();

        header('Location: ../pages/cajas.php?msg=actualizada');
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ../pages/cajas.php?error=general');
    exit;
}