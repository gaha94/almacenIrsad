<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/permisos.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!esAdmin()) {
    header('Location: ../dashboard.php');
    exit;
}

$id = trim($_GET['id'] ?? '');
$estado = trim($_GET['estado'] ?? '');

if ($id === '' || !in_array($estado, ['0', '1'])) {
    header('Location: ../pages/usuarios.php?error=general');
    exit;
}

if ((int)$id === (int)$_SESSION['usuario_id'] && $estado === '0') {
    header('Location: ../pages/usuarios.php?error=no_auto_desactivar');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtUsuario = $pdo->prepare("
        SELECT usuario, estado
        FROM usuarios
        WHERE id = :id
        LIMIT 1
    ");
    $stmtUsuario->execute([
        ':id' => $id
    ]);
    $usuario = $stmtUsuario->fetch();

    if (!$usuario) {
        $pdo->rollBack();
        header('Location: ../pages/usuarios.php?error=general');
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE usuarios
        SET estado = :estado
        WHERE id = :id
    ");

    $stmt->execute([
        ':estado' => $estado,
        ':id' => $id
    ]);

    $accionTexto = $estado === '1' ? 'activado' : 'desactivado';

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
        ':usuario_id' => $_SESSION['usuario_id'],
        ':descripcion' => 'Usuario ' . $accionTexto . ': ' . $usuario['usuario']
    ]);

    $pdo->commit();

    header('Location: ../pages/usuarios.php?msg=estado_actualizado');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ../pages/usuarios.php?error=general');
    exit;
}