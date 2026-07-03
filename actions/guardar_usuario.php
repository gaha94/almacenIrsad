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

$id = trim($_POST['id'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$usuario = strtolower(trim($_POST['usuario'] ?? ''));
$password = trim($_POST['password'] ?? '');
$rol_id = trim($_POST['rol_id'] ?? '');

if ($nombre === '' || $usuario === '' || $rol_id === '') {
    header('Location: ../pages/usuarios.php?error=faltan_datos');
    exit;
}

try {
    $pdo->beginTransaction();

    if ($id === '') {
        // Crear usuario

        if ($password === '') {
            header('Location: ../pages/usuarios.php?error=faltan_datos');
            exit;
        }

        if (strlen($password) < 6) {
            header('Location: ../pages/usuarios.php?error=password_corta');
            exit;
        }

        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE usuario = :usuario
            LIMIT 1
        ");
        $stmtExiste->execute([
            ':usuario' => $usuario
        ]);

        if ($stmtExiste->fetch()) {
            $pdo->rollBack();
            header('Location: ../pages/usuarios.php?error=duplicado');
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                rol_id,
                nombre,
                usuario,
                password_hash,
                estado
            ) VALUES (
                :rol_id,
                :nombre,
                :usuario,
                :password_hash,
                1
            )
        ");

        $stmt->execute([
            ':rol_id' => $rol_id,
            ':nombre' => $nombre,
            ':usuario' => $usuario,
            ':password_hash' => $passwordHash
        ]);

        $nuevoUsuarioId = $pdo->lastInsertId();

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
            ':descripcion' => 'Usuario creado: ' . $usuario
        ]);

        $pdo->commit();

        header('Location: ../pages/usuarios.php?msg=creado');
        exit;

    } else {
        // Editar usuario

        $stmtUsuarioActual = $pdo->prepare("
            SELECT u.*, r.nombre AS rol_nombre
            FROM usuarios u
            INNER JOIN roles r ON r.id = u.rol_id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmtUsuarioActual->execute([
            ':id' => $id
        ]);
        $usuarioActual = $stmtUsuarioActual->fetch();

        if (!$usuarioActual) {
            $pdo->rollBack();
            header('Location: ../pages/usuarios.php?error=general');
            exit;
        }

        $stmtDuplicado = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE usuario = :usuario
              AND id <> :id
            LIMIT 1
        ");
        $stmtDuplicado->execute([
            ':usuario' => $usuario,
            ':id' => $id
        ]);

        if ($stmtDuplicado->fetch()) {
            $pdo->rollBack();
            header('Location: ../pages/usuarios.php?error=duplicado');
            exit;
        }

        if ($password !== '') {
            if (strlen($password) < 6) {
                $pdo->rollBack();
                header('Location: ../pages/usuarios.php?error=password_corta');
                exit;
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET rol_id = :rol_id,
                    nombre = :nombre,
                    usuario = :usuario,
                    password_hash = :password_hash
                WHERE id = :id
            ");

            $stmt->execute([
                ':rol_id' => $rol_id,
                ':nombre' => $nombre,
                ':usuario' => $usuario,
                ':password_hash' => $passwordHash,
                ':id' => $id
            ]);

        } else {
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET rol_id = :rol_id,
                    nombre = :nombre,
                    usuario = :usuario
                WHERE id = :id
            ");

            $stmt->execute([
                ':rol_id' => $rol_id,
                ':nombre' => $nombre,
                ':usuario' => $usuario,
                ':id' => $id
            ]);
        }

        $stmtRolNuevo = $pdo->prepare("
            SELECT nombre
            FROM roles
            WHERE id = :rol_id
            LIMIT 1
        ");
        $stmtRolNuevo->execute([
            ':rol_id' => $rol_id
        ]);
        $rolNuevo = $stmtRolNuevo->fetchColumn();

        $detalle = 'Usuario actualizado: ' . $usuarioActual['usuario'] . ' → ' . $usuario;

        if ($usuarioActual['rol_id'] != $rol_id) {
            $detalle .= '. Rol cambiado de ' . $usuarioActual['rol_nombre'] . ' a ' . $rolNuevo;
        }

        if ($password !== '') {
            $detalle .= '. Contraseña actualizada';
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
            ':usuario_id' => $_SESSION['usuario_id'],
            ':descripcion' => $detalle
        ]);

        /*
            Si el admin se editó a sí mismo, actualizamos datos de sesión.
            No permitimos cambiar su estado aquí, pero sí nombre/usuario/rol.
        */
        if ((int)$id === (int)$_SESSION['usuario_id']) {
            $_SESSION['nombre'] = $nombre;
            $_SESSION['usuario'] = $usuario;

            if ($rolNuevo) {
                $_SESSION['rol_nombre'] = $rolNuevo;
            }
        }

        $pdo->commit();

        header('Location: ../pages/usuarios.php?msg=actualizado');
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ../pages/usuarios.php?error=general');
    exit;
}