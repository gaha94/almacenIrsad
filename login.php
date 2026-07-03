<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Login - Sistema de Almacén</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php require_once __DIR__ . '/config/app.php'; ?>
    <?php include __DIR__ . '/includes/pwa-head.php'; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(13, 110, 253, 0.18), transparent 32%),
                linear-gradient(135deg, #111827 0%, #1f2937 45%, #f4f6f9 45%, #f4f6f9 100%);
        }

        .login-wrapper {
            min-height: 100vh;
        }

        .brand-panel {
            color: white;
            padding: 32px;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background-color: #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            box-shadow: 0 14px 30px rgba(13, 110, 253, 0.35);
            margin-bottom: 18px;
        }

        .login-card {
            border: none;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
        }

        .login-card .card-body {
            padding: 34px;
        }

        .form-control,
        .input-group-text {
            border-radius: 12px;
        }

        .input-group .input-group-text:first-child {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group .form-control {
            border-radius: 0 12px 12px 0;
        }

        .input-group.password-group .form-control {
            border-radius: 0;
        }

        .input-group.password-group .btn {
            border-radius: 0 12px 12px 0;
        }

        .btn-login {
            border-radius: 12px;
            padding: 11px 16px;
            font-weight: 700;
        }

        .small-muted {
            font-size: 0.9rem;
            color: #6c757d;
        }

        @media (max-width: 991.98px) {
            body {
                background: #f4f6f9;
            }

            .brand-panel {
                color: #212529;
                text-align: center;
                padding-bottom: 0;
            }

            .brand-icon {
                margin-left: auto;
                margin-right: auto;
            }
        }
    </style>
</head>

<body>

    <main class="container login-wrapper d-flex align-items-center py-4">
        <div class="row justify-content-center align-items-center w-100 g-4">

            <div class="col-lg-5 d-none d-lg-block">
                <section class="brand-panel">
                    <div class="brand-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>

                    <h1 class="fw-bold mb-3">
                        Sistema de Almacén
                    </h1>

                    <p class="lead text-white-50 mb-4">
                        Gestiona productos, cajas, ubicaciones y movimientos desde una plataforma ordenada y segura.
                    </p>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-check-circle-fill text-primary fs-4"></i>
                            <span>Consulta rápida de productos y ubicaciones.</span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-check-circle-fill text-primary fs-4"></i>
                            <span>Control de cajas, alertas e historial.</span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-check-circle-fill text-primary fs-4"></i>
                            <span>Acceso por roles de usuario.</span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

                <div class="card login-card">
                    <div class="card-body">

                        <div class="text-center mb-4">
                            <div class="brand-icon d-lg-none">
                                <i class="bi bi-box-seam"></i>
                            </div>

                            <h4 class="fw-bold mb-1">
                                Bienvenido
                            </h4>

                            <p class="small-muted mb-0">
                                Ingresa tus credenciales para continuar.
                            </p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-4 d-flex align-items-start gap-2">
                                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                                <div>
                                    <strong>No se pudo iniciar sesión.</strong><br>
                                    Usuario o contraseña incorrectos.
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="actions/login_process.php" method="POST">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input 
                                        type="text" 
                                        name="usuario" 
                                        class="form-control" 
                                        placeholder="Ingresa tu usuario"
                                        autocomplete="username"
                                        required 
                                        autofocus>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Contraseña</label>
                                <div class="input-group password-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input 
                                        type="password" 
                                        name="password" 
                                        id="passwordInput"
                                        class="form-control" 
                                        placeholder="Ingresa tu contraseña"
                                        autocomplete="current-password"
                                        required>

                                    <button 
                                        class="btn btn-outline-secondary" 
                                        type="button"
                                        id="togglePassword"
                                        aria-label="Mostrar u ocultar contraseña">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="bi bi-box-arrow-in-right me-1"></i>
                                Ingresar
                            </button>

                        </form>

                    </div>
                </div>

                <p class="text-center small-muted mt-3 mb-0">
                    Acceso exclusivo para usuarios autorizados.
                </p>

            </div>

        </div>
    </main>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                const icon = this.querySelector('i');
                const isPassword = passwordInput.type === 'password';

                passwordInput.type = isPassword ? 'text' : 'password';
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        }
    </script>

    <?php include __DIR__ . '/includes/pwa-script.php'; ?>

</body>

</html>