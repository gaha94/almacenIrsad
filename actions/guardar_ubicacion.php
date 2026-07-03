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
$codigo = strtoupper(trim($_POST['codigo'] ?? ''));
$zona = trim($_POST['zona'] ?? '');
$estante = strtoupper(trim($_POST['estante'] ?? ''));
$posicion = trim($_POST['posicion'] ?? '');
$estado = trim($_POST['estado'] ?? 'Libre');
$observacion = trim($_POST['observacion'] ?? '');

$estadosPermitidos = ['Libre', 'Ocupado', 'Revisar', 'Inactivo'];

if ($codigo === '') {
    header('Location: ../pages/ubicacion.php?error=faltan_datos');
    exit;
}

if (!in_array($estado, $estadosPermitidos)) {
    $estado = 'Libre';
}

/*
    Si el usuario escribe A21 y no completa estante/posición,
    intentamos separarlo automáticamente:
    A21 => estante A, posición 21
    B5 => estante B, posición 5
*/
if ($estante === '' || $posicion === '') {
    if (preg_match('/^([A-Z]+)([0-9]+)$/', $codigo, $matches)) {
        if ($estante === '') {
            $estante = $matches[1];
        }

        if ($posicion === '') {
            $posicion = $matches[2];
        }
    }
}

if ($zona === '') {
    $zona = 'Almacen principal';
}

try {
    $pdo->beginTransaction();

    if ($id === '') {
        // Crear ubicación

        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM ubicaciones
            WHERE codigo = :codigo
            LIMIT 1
        ");
        $stmtExiste->execute([
            ':codigo' => $codigo
        ]);

        if ($stmtExiste->fetch()) {
            $pdo->rollBack();
            header('Location: ../pages/ubicacion.php?error=duplicado');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO ubicaciones (
                codigo,
                zona,
                estante,
                posicion,
                estado,
                observacion
            ) VALUES (
                :codigo,
                :zona,
                :estante,
                :posicion,
                :estado,
                :observacion
            )
        ");

        $stmt->execute([
            ':codigo' => $codigo,
            ':zona' => $zona,
            ':estante' => $estante ?: null,
            ':posicion' => $posicion ?: null,
            ':estado' => $estado,
            ':observacion' => $observacion ?: null
        ]);

        $ubicacionId = $pdo->lastInsertId();

        $stmtMov = $pdo->prepare("
            INSERT INTO movimientos (
                tipo_movimiento,
                ubicacion_destino_id,
                usuario_id,
                descripcion
            ) VALUES (
                'Correccion',
                :ubicacion_destino_id,
                :usuario_id,
                :descripcion
            )
        ");

        $stmtMov->execute([
            ':ubicacion_destino_id' => $ubicacionId,
            ':usuario_id' => $usuario_id,
            ':descripcion' => 'Ubicación creada: ' . $codigo
        ]);

        $pdo->commit();

        header('Location: ../pages/ubicacion.php?msg=creada');
        exit;
    } else {
        // Editar ubicación

        $stmtUbicacion = $pdo->prepare("
            SELECT *
            FROM ubicaciones
            WHERE id = :id
            LIMIT 1
        ");
        $stmtUbicacion->execute([
            ':id' => $id
        ]);
        $ubicacionActual = $stmtUbicacion->fetch();

        if (!$ubicacionActual) {
            $pdo->rollBack();
            header('Location: ../pages/ubicacion.php?error=general');
            exit;
        }

        $stmtDuplicado = $pdo->prepare("
            SELECT id
            FROM ubicaciones
            WHERE codigo = :codigo
              AND id <> :id
            LIMIT 1
        ");
        $stmtDuplicado->execute([
            ':codigo' => $codigo,
            ':id' => $id
        ]);

        if ($stmtDuplicado->fetch()) {
            $pdo->rollBack();
            header('Location: ../pages/ubicacion.php?error=duplicado');
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE ubicaciones
            SET codigo = :codigo,
                zona = :zona,
                estante = :estante,
                posicion = :posicion,
                estado = :estado,
                observacion = :observacion
            WHERE id = :id
        ");

        $stmt->execute([
            ':codigo' => $codigo,
            ':zona' => $zona,
            ':estante' => $estante ?: null,
            ':posicion' => $posicion ?: null,
            ':estado' => $estado,
            ':observacion' => $observacion ?: null,
            ':id' => $id
        ]);

        $stmtMov = $pdo->prepare("
            INSERT INTO movimientos (
                tipo_movimiento,
                ubicacion_origen_id,
                ubicacion_destino_id,
                usuario_id,
                descripcion
            ) VALUES (
                'Correccion',
                :ubicacion_origen_id,
                :ubicacion_destino_id,
                :usuario_id,
                :descripcion
            )
        ");

        $descripcion = 'Ubicación actualizada: ' . $ubicacionActual['codigo'] . ' → ' . $codigo;

        $stmtMov->execute([
            ':ubicacion_origen_id' => $id,
            ':ubicacion_destino_id' => $id,
            ':usuario_id' => $usuario_id,
            ':descripcion' => $descripcion
        ]);

        $pdo->commit();

        header('Location: ../pages/ubicacion.php?msg=actualizada');
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ../pages/ubicacion.php?error=general');
    exit;
}
