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

if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../pages/importar.php?error=1');
    exit;
}

$tmpFile = $_FILES['archivo_csv']['tmp_name'];

function normalizarTexto($valor)
{
    $valor = trim((string)$valor);
    $valor = preg_replace('/^\xEF\xBB\xBF/', '', $valor); // elimina BOM UTF-8
    return $valor;
}

function obtenerOCrearUbicacion(PDO $pdo, string $codigoUbicacion)
{
    if ($codigoUbicacion === '') {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM ubicaciones WHERE codigo = :codigo LIMIT 1");
    $stmt->execute([':codigo' => $codigoUbicacion]);
    $row = $stmt->fetch();

    if ($row) {
        return $row['id'];
    }

    $estante = substr($codigoUbicacion, 0, 1);
    $posicion = substr($codigoUbicacion, 1);

    $stmt = $pdo->prepare("
        INSERT INTO ubicaciones (codigo, zona, estante, posicion, estado)
        VALUES (:codigo, 'Almacen principal', :estante, :posicion, 'Ocupado')
    ");

    $stmt->execute([
        ':codigo' => $codigoUbicacion,
        ':estante' => $estante,
        ':posicion' => $posicion
    ]);

    return $pdo->lastInsertId();
}

function obtenerOCrearPackingList(PDO $pdo, ?string $codigoPacking, int $usuarioId)
{
    if (!$codigoPacking) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM packing_lists WHERE codigo = :codigo LIMIT 1");
    $stmt->execute([':codigo' => $codigoPacking]);
    $row = $stmt->fetch();

    if ($row) {
        return $row['id'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO packing_lists (codigo, estado, creado_por)
        VALUES (:codigo, 'Pendiente', :creado_por)
    ");

    $stmt->execute([
        ':codigo' => $codigoPacking,
        ':creado_por' => $usuarioId
    ]);

    return $pdo->lastInsertId();
}

function obtenerOCrearCaja(PDO $pdo, ?string $numeroCaja, ?int $ubicacionId, ?int $packingListId, int $usuarioId)
{
    if (!$numeroCaja) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM cajas WHERE numero_caja = :numero_caja LIMIT 1");
    $stmt->execute([':numero_caja' => $numeroCaja]);
    $row = $stmt->fetch();

    if ($row) {
        $stmtUpdate = $pdo->prepare("
            UPDATE cajas
            SET ubicacion_id = COALESCE(:ubicacion_id, ubicacion_id),
                packing_list_id = COALESCE(:packing_list_id, packing_list_id),
                estado = 'Activa',
                actualizado_por = :actualizado_por
            WHERE id = :id
        ");

        $stmtUpdate->execute([
            ':ubicacion_id' => $ubicacionId,
            ':packing_list_id' => $packingListId,
            ':actualizado_por' => $usuarioId,
            ':id' => $row['id']
        ]);

        return $row['id'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO cajas (
            numero_caja,
            ubicacion_id,
            packing_list_id,
            estado,
            creado_por
        ) VALUES (
            :numero_caja,
            :ubicacion_id,
            :packing_list_id,
            'Activa',
            :creado_por
        )
    ");

    $stmt->execute([
        ':numero_caja' => $numeroCaja,
        ':ubicacion_id' => $ubicacionId,
        ':packing_list_id' => $packingListId,
        ':creado_por' => $usuarioId
    ]);

    return $pdo->lastInsertId();
}

try {
    $pdo->beginTransaction();

    $handle = fopen($tmpFile, 'r');

    if (!$handle) {
        throw new Exception('No se pudo abrir el archivo CSV.');
    }

    $headers = fgetcsv($handle, 0, ',');

    if (!$headers) {
        throw new Exception('El archivo CSV está vacío.');
    }

    $headers = array_map('normalizarTexto', $headers);

    $esperadas = [
        'codigo_producto',
        'codigo_alterno',
        'numero_caja',
        'ubicacion',
        'packing_list',
        'tipo',
        'descripcion'
    ];

    foreach ($esperadas as $columna) {
        if (!in_array($columna, $headers)) {
            throw new Exception('Falta la columna: ' . $columna);
        }
    }

    $map = array_flip($headers);
    $importados = 0;

    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $codigoProducto = normalizarTexto($row[$map['codigo_producto']] ?? '');
        $codigoAlterno = normalizarTexto($row[$map['codigo_alterno']] ?? '');
        $numeroCaja = normalizarTexto($row[$map['numero_caja']] ?? '');
        $codigoUbicacion = normalizarTexto($row[$map['ubicacion']] ?? '');
        $codigoPacking = normalizarTexto($row[$map['packing_list']] ?? '');
        $tipo = normalizarTexto($row[$map['tipo']] ?? '');
        $descripcion = normalizarTexto($row[$map['descripcion']] ?? '');

        if ($codigoProducto === '' && $numeroCaja === '') {
            continue;
        }

        if (!in_array($tipo, ['Suelto', 'En caja'])) {
            $tipo = $numeroCaja !== '' ? 'En caja' : 'Suelto';
        }

        $ubicacionId = obtenerOCrearUbicacion($pdo, $codigoUbicacion);
        $packingListId = obtenerOCrearPackingList($pdo, $codigoPacking ?: null, $usuario_id);
        $cajaId = obtenerOCrearCaja($pdo, $numeroCaja ?: null, $ubicacionId, $packingListId, $usuario_id);

        if ($ubicacionId) {
            $stmtUb = $pdo->prepare("UPDATE ubicaciones SET estado = 'Ocupado' WHERE id = :id");
            $stmtUb->execute([':id' => $ubicacionId]);
        }

        if ($codigoProducto === '' && $numeroCaja !== '') {
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
                ':descripcion' => 'Caja importada desde CSV sin productos cargados.'
            ]);

            $importados++;
            continue;
        }

        $stmtExiste = $pdo->prepare("
            SELECT id 
            FROM productos
            WHERE codigo_producto = :codigo_producto
              AND COALESCE(codigo_alterno, '') = :codigo_alterno
              AND COALESCE(caja_id, 0) = COALESCE(:caja_id, 0)
            LIMIT 1
        ");

        $stmtExiste->execute([
            ':codigo_producto' => $codigoProducto,
            ':codigo_alterno' => $codigoAlterno,
            ':caja_id' => $cajaId
        ]);

        $productoExistente = $stmtExiste->fetch();

        if ($productoExistente) {
            $stmtProducto = $pdo->prepare("
                UPDATE productos
                SET codigo_alterno = :codigo_alterno,
                    descripcion = :descripcion,
                    tipo = :tipo,
                    caja_id = :caja_id,
                    ubicacion_id = :ubicacion_id,
                    packing_list_id = :packing_list_id,
                    estado = 'Disponible',
                    actualizado_por = :actualizado_por
                WHERE id = :id
            ");

            $stmtProducto->execute([
                ':codigo_alterno' => $codigoAlterno ?: null,
                ':descripcion' => $descripcion ?: null,
                ':tipo' => $tipo,
                ':caja_id' => $cajaId,
                ':ubicacion_id' => $ubicacionId,
                ':packing_list_id' => $packingListId,
                ':actualizado_por' => $usuario_id,
                ':id' => $productoExistente['id']
            ]);

            $productoId = $productoExistente['id'];
        } else {
            $stmtProducto = $pdo->prepare("
                INSERT INTO productos (
                    codigo_producto,
                    codigo_alterno,
                    descripcion,
                    tipo,
                    caja_id,
                    ubicacion_id,
                    packing_list_id,
                    estado,
                    creado_por
                ) VALUES (
                    :codigo_producto,
                    :codigo_alterno,
                    :descripcion,
                    :tipo,
                    :caja_id,
                    :ubicacion_id,
                    :packing_list_id,
                    'Disponible',
                    :creado_por
                )
            ");

            $stmtProducto->execute([
                ':codigo_producto' => $codigoProducto,
                ':codigo_alterno' => $codigoAlterno ?: null,
                ':descripcion' => $descripcion ?: null,
                ':tipo' => $tipo,
                ':caja_id' => $cajaId,
                ':ubicacion_id' => $ubicacionId,
                ':packing_list_id' => $packingListId,
                ':creado_por' => $usuario_id
            ]);

            $productoId = $pdo->lastInsertId();
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
            ':descripcion' => 'Producto importado desde CSV.'
        ]);

        $importados++;
    }

    fclose($handle);

    $pdo->commit();

    header('Location: ../pages/importar.php?msg=ok&total=' . $importados);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Error al importar CSV: ' . $e->getMessage());
}
