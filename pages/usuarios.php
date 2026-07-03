<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

if (!esAdmin()) {
    header('Location: ../dashboard.php');
    exit;
}

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$stmtRoles = $pdo->query("
    SELECT id, nombre, descripcion
    FROM roles
    WHERE estado = 1
    ORDER BY id ASC
");
$roles = $stmtRoles->fetchAll();

$stmtUsuarios = $pdo->query("
    SELECT 
        u.id,
        u.nombre,
        u.usuario,
        u.estado,
        u.ultimo_acceso,
        u.created_at,
        r.id AS rol_id,
        r.nombre AS rol_nombre
    FROM usuarios u
    INNER JOIN roles r ON r.id = u.rol_id
    ORDER BY u.id ASC
");
$usuarios = $stmtUsuarios->fetchAll();

$totalUsuarios = count($usuarios);
$totalActivos = count(array_filter($usuarios, fn($u) => (int)$u['estado'] === 1));
$totalInactivos = count(array_filter($usuarios, fn($u) => (int)$u['estado'] === 0));

function badgeRol($rol): string
{
    return match ($rol) {
        'admin' => 'bg-danger',
        'supervisor' => 'bg-warning text-dark',
        'operario' => 'bg-primary',
        default => 'bg-secondary',
    };
}

function iconoRol($rol): string
{
    return match ($rol) {
        'admin' => 'bi-shield-lock',
        'supervisor' => 'bi-person-gear',
        'operario' => 'bi-person',
        default => 'bi-person-badge',
    };
}

function fechaUsuario($fecha): string
{
    if (empty($fecha)) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($fecha));
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Usuarios - Almacén</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php require_once __DIR__ . '/../config/app.php'; ?>
    <?php include __DIR__ . '/../includes/pwa-head.php'; ?>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .page-header {
            background: linear-gradient(135deg, #212529, #343a40);
            color: #fff;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .summary-card,
        .form-card,
        .main-card {
            border: none;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.06);
        }

        .summary-card {
            height: 100%;
        }

        .summary-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .table thead th {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: #212529;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .code-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 0.84rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
            color: #6c757d;
        }

        .empty-state-icon {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            background-color: #f1f3f5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            color: #6c757d;
            margin-bottom: 16px;
        }

        .section-title {
            font-weight: 700;
            color: #212529;
        }

        .text-muted-small {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .modal-content {
            border: none;
            border-radius: 18px;
            overflow: hidden;
        }

        .modal-header {
            background-color: #212529;
            color: #fff;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 22px;
            }

            .action-group {
                flex-direction: column;
            }

            .action-group .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container py-4">

    <section class="page-header mb-4">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h2 class="fw-bold mb-2">
                    <i class="bi bi-people me-2"></i>
                    Usuarios
                </h2>
                <p class="mb-0 text-white-50">
                    Administra usuarios, roles y accesos del sistema de almacén.
                </p>
            </div>

            <div class="col-md-4 text-md-end">
                <a href="../dashboard.php" class="btn btn-light btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i>
                    Volver al panel
                </a>
            </div>
        </div>
    </section>

    <?php if ($msg === 'creado'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>Usuario creado correctamente.</div>
        </div>
    <?php elseif ($msg === 'actualizado'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>Usuario actualizado correctamente.</div>
        </div>
    <?php elseif ($msg === 'estado_actualizado'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>Estado del usuario actualizado correctamente.</div>
        </div>
    <?php endif; ?>

    <?php if ($error === 'duplicado'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>Ya existe un usuario con ese nombre de usuario.</div>
        </div>
    <?php elseif ($error === 'faltan_datos'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>Faltan datos obligatorios.</div>
        </div>
    <?php elseif ($error === 'password_corta'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>La contraseña debe tener al menos 6 caracteres.</div>
        </div>
    <?php elseif ($error === 'no_auto_desactivar'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>No puedes desactivar tu propio usuario.</div>
        </div>
    <?php elseif ($error === 'general'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>Ocurrió un error al procesar la solicitud.</div>
        </div>
    <?php endif; ?>

    <section class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card summary-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="summary-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <p class="text-muted-small mb-1">Total usuarios</p>
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalUsuarios) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card summary-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="summary-icon bg-success-subtle text-success">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <p class="text-muted-small mb-1">Usuarios activos</p>
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalActivos) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card summary-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="summary-icon bg-danger-subtle text-danger">
                        <i class="bi bi-person-x"></i>
                    </div>
                    <div>
                        <p class="text-muted-small mb-1">Usuarios inactivos</p>
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalInactivos) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card form-card mb-4">
        <div class="card-header bg-white border-0 p-3 p-md-4">
            <h5 class="section-title mb-1">
                <i class="bi bi-person-plus me-1"></i>
                Crear nuevo usuario
            </h5>
            <p class="text-muted-small mb-0">
                Registra un nuevo usuario y asígnale un rol de acceso.
            </p>
        </div>

        <div class="card-body p-3 p-md-4 pt-md-0">
            <form action="../actions/guardar_usuario.php" method="POST" class="row g-3">

                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Nombre completo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-person"></i>
                        </span>
                        <input 
                            type="text" 
                            name="nombre" 
                            class="form-control" 
                            placeholder="Ejemplo: Juan Pérez"
                            required>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-at"></i>
                        </span>
                        <input 
                            type="text" 
                            name="usuario" 
                            class="form-control" 
                            placeholder="Ejemplo: jperez"
                            autocomplete="off"
                            required>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Mínimo 6 caracteres"
                            autocomplete="new-password"
                            required>
                    </div>
                    <div class="form-text">Debe tener al menos 6 caracteres.</div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Rol</label>
                    <select name="rol_id" class="form-select" required>
                        <option value="">Seleccionar rol</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= htmlspecialchars($rol['id']) ?>">
                                <?= htmlspecialchars($rol['nombre']) ?>
                                <?php if (!empty($rol['descripcion'])): ?>
                                    - <?= htmlspecialchars($rol['descripcion']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-person-plus me-1"></i>
                        Crear usuario
                    </button>
                </div>

            </form>
        </div>
    </section>

    <section class="card main-card">
        <div class="card-header bg-white border-0 p-3 p-md-4">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <h5 class="section-title mb-1">
                        Lista de usuarios
                    </h5>
                    <p class="text-muted-small mb-0">
                        Gestiona roles, credenciales y estado de los usuarios registrados.
                    </p>
                </div>

                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-search"></i>
                        </span>
                        <input 
                            type="text" 
                            id="buscarUsuarios" 
                            class="form-control" 
                            placeholder="Buscar usuario..."
                        >
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($usuarios) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaUsuarios">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Último acceso</th>
                            <th>Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php
                            $inicial = strtoupper(mb_substr($u['nombre'] ?? 'U', 0, 1));
                            $esUsuarioActual = (int)$u['id'] === (int)$_SESSION['usuario_id'];
                            ?>
                            <tr>
                                <td>
                                    <span class="code-pill">
                                        #<?= htmlspecialchars($u['id']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar">
                                            <?= htmlspecialchars($inicial) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                                            <?php if ($esUsuarioActual): ?>
                                                <br>
                                                <small class="text-primary">
                                                    <i class="bi bi-person-check me-1"></i>
                                                    Usuario actual
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="code-pill">
                                        <i class="bi bi-at"></i>
                                        <?= htmlspecialchars($u['usuario']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge rounded-pill <?= badgeRol($u['rol_nombre']) ?> px-3 py-2">
                                        <i class="bi <?= iconoRol($u['rol_nombre']) ?> me-1"></i>
                                        <?= htmlspecialchars($u['rol_nombre']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ((int)$u['estado'] === 1): ?>
                                        <span class="badge rounded-pill bg-success px-3 py-2">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-danger px-3 py-2">
                                            <i class="bi bi-x-circle me-1"></i>
                                            Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="text-nowrap">
                                        <?= htmlspecialchars(fechaUsuario($u['ultimo_acceso'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="text-nowrap">
                                        <?= htmlspecialchars(fechaUsuario($u['created_at'])) ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <div class="action-group justify-content-end">
                                        <button 
                                            type="button"
                                            class="btn btn-sm btn-outline-dark"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editarUsuarioModal"
                                            data-id="<?= htmlspecialchars($u['id']) ?>"
                                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                                            data-usuario="<?= htmlspecialchars($u['usuario']) ?>"
                                            data-rol-id="<?= htmlspecialchars($u['rol_id']) ?>"
                                        >
                                            <i class="bi bi-pencil-square me-1"></i>
                                            Editar
                                        </button>

                                        <?php if (!$esUsuarioActual): ?>
                                            <?php if ((int)$u['estado'] === 1): ?>
                                                <a 
                                                    href="../actions/cambiar_estado_usuario.php?id=<?= urlencode($u['id']) ?>&estado=0"
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('¿Desactivar este usuario?')"
                                                >
                                                    <i class="bi bi-person-x me-1"></i>
                                                    Desactivar
                                                </a>
                                            <?php else: ?>
                                                <a 
                                                    href="../actions/cambiar_estado_usuario.php?id=<?= urlencode($u['id']) ?>&estado=1"
                                                    class="btn btn-sm btn-outline-success"
                                                    onclick="return confirm('¿Activar este usuario?')"
                                                >
                                                    <i class="bi bi-person-check me-1"></i>
                                                    Activar
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border px-3 py-2">
                                                <i class="bi bi-lock me-1"></i>
                                                No editable estado
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h5 class="fw-bold mb-2">No hay usuarios registrados</h5>
                <p class="mb-0">Cuando crees usuarios, aparecerán en esta sección.</p>
            </div>
        <?php endif; ?>
    </section>

</main>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="../actions/guardar_usuario.php" class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editarUsuarioModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>
                    Editar usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4">

                <input type="hidden" name="id" id="edit_id">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre completo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-person"></i>
                        </span>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-at"></i>
                        </span>
                        <input type="text" name="usuario" id="edit_usuario" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Rol</label>
                    <select name="rol_id" id="edit_rol_id" class="form-select" required>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= htmlspecialchars($rol['id']) ?>">
                                <?= htmlspecialchars($rol['nombre']) ?>
                                <?php if (!empty($rol['descripcion'])): ?>
                                    - <?= htmlspecialchars($rol['descripcion']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Puedes convertir un usuario en admin, supervisor u operario.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nueva contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control"
                            placeholder="Dejar vacío si no deseas cambiarla">
                    </div>
                    <div class="form-text">
                        Solo escribe una contraseña si quieres cambiarla.
                    </div>
                </div>

                <div class="alert alert-info border-0 rounded-4 mb-0 d-flex gap-2">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div>
                        Los cambios se aplicarán cuando guardes el formulario.
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>
                    Guardar cambios
                </button>
            </div>

        </form>
    </div>
</div>

<!-- Bootstrap JS necesario para navbar, dropdowns y modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const editarUsuarioModal = document.getElementById('editarUsuarioModal');

    if (editarUsuarioModal) {
        editarUsuarioModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;

            document.getElementById('edit_id').value = button.getAttribute('data-id');
            document.getElementById('edit_nombre').value = button.getAttribute('data-nombre');
            document.getElementById('edit_usuario').value = button.getAttribute('data-usuario');
            document.getElementById('edit_rol_id').value = button.getAttribute('data-rol-id');
        });
    }

    const buscarUsuarios = document.getElementById('buscarUsuarios');
    const tablaUsuarios = document.getElementById('tablaUsuarios');

    if (buscarUsuarios && tablaUsuarios) {
        buscarUsuarios.addEventListener('keyup', function () {
            const filtro = this.value.toLowerCase().trim();
            const filas = tablaUsuarios.querySelectorAll('tbody tr');

            filas.forEach(function (fila) {
                const textoFila = fila.textContent.toLowerCase();
                fila.style.display = textoFila.includes(filtro) ? '' : 'none';
            });
        });
    }
</script>

<?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</body>

</html>