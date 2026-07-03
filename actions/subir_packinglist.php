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
$estado = trim($_POST['estado'] ?? 'Cargado');
$descripcion = trim($_POST['descripcion'] ?? '');

$estadosPermitidos = ['Cargado', 'Pendiente', 'Revisar', 'Inactivo'];

if ($codigo === '') {
    header('Location: ../pages/packing-lists.php?error=faltan_datos');
    exit;
}

if (!in_array($estado, $estadosPermitidos)) {
    $estado = 'Cargado';
}

$uploadDirRelativa = 'uploads/packinglists/';
$uploadDirAbsoluta = __DIR__ . '/../' . $uploadDirRelativa;

if (!is_dir($uploadDirAbsoluta)) {
    mkdir($uploadDirAbsoluta, 0775, true);
}

function limpiarNombreArchivo($texto)
{
    $texto = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $texto);
    $texto = preg_replace('/_+/', '_', $texto);
    return trim($texto, '_');
}

function procesarArchivoPDF($inputName, $codigo, $uploadDirAbsoluta, $uploadDirRelativa)
{
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'upload_error'];
    }

    $maxBytes = 20 * 1024 * 1024; // 20 MB

    if ($_FILES[$inputName]['size'] > $maxBytes) {
        return ['error' => 'archivo_pesado'];
    }

    $tmpName = $_FILES[$inputName]['tmp_name'];
    $originalName = $_FILES[$inputName]['name'];

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension !== 'pdf') {
        return ['error' => 'archivo_invalido'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    if ($mimeType !== 'application/pdf' && $mimeType !== 'application/octet-stream') {
        return ['error' => 'archivo_invalido'];
    }

    $codigoLimpio = limpiarNombreArchivo($codigo);
    $fecha = date('Ymd_His');
    $nombreFinal = $codigoLimpio . '_' . $fecha . '.pdf';

    $rutaFinalAbsoluta = $uploadDirAbsoluta . $nombreFinal;
    $rutaFinalRelativa = $uploadDirRelativa . $nombreFinal;

    if (!move_uploaded_file($tmpName, $rutaFinalAbsoluta)) {
        return ['error' => 'upload_error'];
    }

    return [
        'nombre_archivo' => $nombreFinal,
        'ruta_archivo' => $rutaFinalRelativa
    ];
}

try {
    $pdo->beginTransaction();

    $archivoProcesado = procesarArchivoPDF(
        'archivo_pdf',
        $codigo,
        $uploadDirAbsoluta,
        $uploadDirRelativa
    );

    if (is_array($archivoProcesado) && isset($archivoProcesado['error'])) {
        $pdo->rollBack();
        header('Location: ../pages/packing-lists.php?error=' . $archivoProcesado['error']);
        exit;
    }

    if ($id === '') {
        // Crear packing list

        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM packing_lists
            WHERE codigo = :codigo
            LIMIT 1
        ");
        $stmtExiste->execute([
            ':codigo' => $codigo
        ]);

        if ($stmtExiste->fetch()) {
            $pdo->rollBack();
            header('Location: ../pages/packing-lists.php?error=duplicado');
            exit;
        }

        $nombreArchivo = $archivoProcesado['nombre_archivo'] ?? null;
        $rutaArchivo = $archivoProcesado['ruta_archivo'] ?? null;

        if (!$rutaArchivo && $estado === 'Cargado') {
            $estado = 'Pendiente';
        }

        $stmt = $pdo->prepare("
            INSERT INTO packing_lists (
                codigo,
                nombre_archivo,
                ruta_archivo,
                descripcion,
                estado,
                creado_por
            ) VALUES (
                :codigo,
                :nombre_archivo,
                :ruta_archivo,
                :descripcion,
                :estado,
                :creado_por
            )
        ");

        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre_archivo' => $nombreArchivo,
            ':ruta_archivo' => $rutaArchivo,
            ':descripcion' => $descripcion ?: null,
            ':estado' => $estado,
            ':creado_por' => $usuario_id
        ]);

        $packingListId = $pdo->lastInsertId();

        $stmtMov = $pdo->prepare("
            INSERT INTO movimientos (
                tipo_movimiento,
                usuario_id,
                descripcion
            ) VALUES (
                'Ingreso',
                :usuario_id,
                :descripcion
            )
        ");

        $stmtMov->execute([
            ':usuario_id' => $usuario_id,
            ':descripcion' => 'Packing list creado: ' . $codigo
        ]);

        $pdo->commit();

        header('Location: ../pages/packing-lists.php?msg=creado');
        exit;

    } else {
        // Editar packing list

        $stmtActual = $pdo->prepare("
            SELECT *
            FROM packing_lists
            WHERE id = :id
            LIMIT 1
        ");
        $stmtActual->execute([
            ':id' => $id
        ]);
        $actual = $stmtActual->fetch();

        if (!$actual) {
            $pdo->rollBack();
            header('Location: ../pages/packing-lists.php?error=general');
            exit;
        }

        $stmtDuplicado = $pdo->prepare("
            SELECT id
            FROM packing_lists
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
            header('Location: ../pages/packing-lists.php?error=duplicado');
            exit;
        }

        $nombreArchivo = $actual['nombre_archivo'];
        $rutaArchivo = $actual['ruta_archivo'];

        if ($archivoProcesado) {
            $nombreArchivo = $archivoProcesado['nombre_archivo'];
            $rutaArchivo = $archivoProcesado['ruta_archivo'];

            if ($estado === 'Pendiente') {
                $estado = 'Cargado';
            }
        }

        if (!$rutaArchivo && $estado === 'Cargado') {
            $estado = 'Pendiente';
        }

        $stmt = $pdo->prepare("
            UPDATE packing_lists
            SET codigo = :codigo,
                nombre_archivo = :nombre_archivo,
                ruta_archivo = :ruta_archivo,
                descripcion = :descripcion,
                estado = :estado
            WHERE id = :id
        ");

        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre_archivo' => $nombreArchivo,
            ':ruta_archivo' => $rutaArchivo,
            ':descripcion' => $descripcion ?: null,
            ':estado' => $estado,
            ':id' => $id
        ]);

        $descripcionMov = 'Packing list actualizado: ' . $actual['codigo'] . ' → ' . $codigo;

        if ($archivoProcesado) {
            $descripcionMov .= '. PDF actualizado.';
        }

        if ($actual['estado'] !== $estado) {
            $descripcionMov .= ' Estado cambiado de ' . $actual['estado'] . ' a ' . $estado . '.';
        }

        $stmtMov = $pdo->prepare("
            INSERT INTO movimientos (
                tipo_movimiento,
                usuario_id,
                descripcion
            ) VALUES (
                'Correccion',
                :usuario_id,
                :descripcion
            )
        ");

        $stmtMov->execute([
            ':usuario_id' => $usuario_id,
            ':descripcion' => $descripcionMov
        ]);

        $pdo->commit();

        header('Location: ../pages/packing-lists.php?msg=actualizado');
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ../pages/packing-lists.php?error=general');
    exit;
}