    <?php
    // actions/login_process.php

    session_start();

    require_once __DIR__ . '/../config/database.php';

    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        header('Location: ../login.php?error=1');
        exit;
    }

    $sql = "
        SELECT 
            u.id,
            u.nombre,
            u.usuario,
            u.password_hash,
            u.estado,
            r.nombre AS rol_nombre
        FROM usuarios u
        INNER JOIN roles r ON r.id = u.rol_id
        WHERE u.usuario = :usuario
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':usuario' => $usuario
    ]);

    $user = $stmt->fetch();

    if (!$user || $user['estado'] != 1) {
        header('Location: ../login.php?error=1');
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        header('Location: ../login.php?error=1');
        exit;
    }

    $_SESSION['usuario_id'] = $user['id'];
    $_SESSION['nombre'] = $user['nombre'];
    $_SESSION['usuario'] = $user['usuario'];
    $_SESSION['rol_nombre'] = $user['rol_nombre'];

    $update = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
    $update->execute([
        ':id' => $user['id']
    ]);

    if ($user['rol_nombre'] === 'operario') {
        header('Location: ../pages/buscar.php');
        exit;
    }

    header('Location: ../dashboard.php');
    exit;
