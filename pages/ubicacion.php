<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

if (!puedeModificar()) {
    header('Location: ../dashboard.php');
    exit;
}

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$buscar = trim($_GET['buscar'] ?? '');

$where = "";
$params = [];

if ($buscar !== '') {
    $where = "WHERE codigo LIKE :buscar OR zona LIKE :buscar OR estante LIKE :buscar";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql = "
    SELECT 
        u.id,
        u.codigo,
        u.zona,
        u.estante,
        u.posicion,
        u.estado,
        u.observacion,
        COUNT(DISTINCT c.id) AS total_cajas,
        COUNT(DISTINCT p.id) AS total_productos_sueltos
    FROM ubicaciones u
    LEFT JOIN cajas c 
        ON c.ubicacion_id = u.id 
        AND c.estado IN ('Activa', 'Revisar')
    LEFT JOIN productos p 
        ON p.ubicacion_id = u.id 
        AND p.caja_id IS NULL 
        AND p.estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
    $where
    GROUP BY 
        u.id,
        u.codigo,
        u.zona,
        u.estante,
        u.posicion,
        u.estado,
        u.observacion
    ORDER BY 
        u.estante ASC,
        CAST(u.posicion AS UNSIGNED) ASC,
        u.codigo ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ubicaciones = $stmt->fetchAll();

$totalUbicaciones = count($ubicaciones);
$totalLibres = count(array_filter($ubicaciones, fn($u) => $u['estado'] === 'Libre'));
$totalOcupadas = count(array_filter($ubicaciones, fn($u) => $u['estado'] === 'Ocupado'));
$totalRevisar = count(array_filter($ubicaciones, fn($u) => $u['estado'] === 'Revisar'));

function badgeEstadoUbicacion($estado): string
{
    return match ($estado) {
        'Libre' => 'bg-success',
        'Ocupado' => 'bg-primary',
        'Revisar' => 'bg-warning text-dark',
        'Inactivo' => 'bg-danger',
        default => 'bg-secondary',
    };
}

function iconoEstadoUbicacion($estado): string
{
    return match ($estado) {
        'Libre' => 'bi-check-circle',
        'Ocupado' => 'bi-box-seam',
        'Revisar' => 'bi-exclamation-triangle',
        'Inactivo' => 'bi-x-circle',
        default => 'bi-info-circle',
    };
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Ubicaciones - Almacén</title>
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
        .search-card,
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
                    <i class="bi bi-geo-alt me-2"></i>
                    Ubicaciones
                </h2>
                <p class="mb-0 text-white-50">
                    Administra códigos de ubicación, zonas, estantes y disponibilidad del almacén.
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

    <?php if ($msg === 'creada'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>Ubicación creada correctamente.</div>
        </div>
    <?php elseif ($msg === 'actualizada'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>Ubicación actualizada correctamente.</div>
        </div>
    <?php endif; ?>

    <?php if ($error === 'duplicado'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>Ya existe una ubicación con ese código.</div>
        </div>
    <?php elseif ($error === 'faltan_datos'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>Debes ingresar el código de ubicación.</div>
        </div>
    <?php elseif ($error === 'general'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>Ocurrió un error al guardar la ubicación.</div>
        </div>
    <?php endif; ?>

    <section class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <div class="card summary-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="summary-icon bg-primary-subtle text-primary">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div>
                        <p class="text-muted-small mb-1">Total ubicaciones</p>
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalUbicaciones) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="card summary-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="summary-icon bg-success-subtle text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <p class="text-muted-small mb-1">Libres</p>
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalLibres) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="card summary-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="summary-icon bg-primary-subtle text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <p class="text-muted-small mb-1">Ocupadas</p>
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalOcupadas) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="card summary-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="summary-icon bg-warning-subtle text-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <p class="text-muted-small mb-1">En revisión</p>
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalRevisar) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card form-card mb-4">
        <div class="card-header bg-white border-0 p-3 p-md-4">
            <h5 class="section-title mb-1">
                <i class="bi bi-plus-circle me-1"></i>
                Crear nueva ubicación
            </h5>
            <p class="text-muted-small mb-0">
                Registra una nueva posición física dentro del almacén.
            </p>
        </div>

        <div class="card-body p-3 p-md-4 pt-md-0">
            <form action="../actions/guardar_ubicacion.php" method="POST" class="row g-3">

                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Código de ubicación</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-qr-code"></i>
                        </span>
                        <input
                            type="text"
                            name="codigo"
                            class="form-control"
                            placeholder="Ejemplo: A21, B1, C5"
                            required>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Zona</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-building"></i>
                        </span>
                        <input
                            type="text"
                            name="zona"
                            class="form-control"
                            value="Almacen principal">
                    </div>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label fw-semibold">Estante</label>
                    <input
                        type="text"
                        name="estante"
                        class="form-control"
                        placeholder="A, B, C...">
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label fw-semibold">Posición</label>
                    <input
                        type="text"
                        name="posicion"
                        class="form-control"
                        placeholder="1, 2, 3...">
                </div>

                <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label fw-semibold">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="Libre">Libre</option>
                        <option value="Ocupado">Ocupado</option>
                        <option value="Revisar">Revisar</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Observación</label>
                    <textarea
                        name="observacion"
                        class="form-control"
                        rows="2"
                        placeholder="Opcional. Ejemplo: ubicación temporal, zona de revisión, etc."></textarea>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-plus-circle me-1"></i>
                        Crear ubicación
                    </button>
                </div>

            </form>
        </div>
    </section>

    <section class="card search-card mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="ubicaciones.php" class="row g-3 align-items-end">
                <div class="col-12 col-md-9 col-lg-10">
                    <label class="form-label fw-semibold">Buscar ubicación</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar por código, zona o estante"
                            value="<?= htmlspecialchars($buscar) ?>">
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <button class="btn btn-outline-primary w-100" type="submit">
                        <i class="bi bi-search me-1"></i>
                        Buscar
                    </button>
                </div>

                <?php if ($buscar !== ''): ?>
                    <div class="col-6 col-md-3 col-lg-2">
                        <a href="ubicaciones.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1"></i>
                            Limpiar
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <section class="card main-card">
        <div class="card-header bg-white border-0 p-3 p-md-4">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <h5 class="section-title mb-1">
                        Lista de ubicaciones
                    </h5>
                    <p class="text-muted-small mb-0">
                        Revisa disponibilidad, cajas activas y productos sueltos por ubicación.
                    </p>
                </div>

                <div class="col-md-4 text-md-end">
                    <?php if ($buscar !== ''): ?>
                        <span class="badge rounded-pill bg-primary px-3 py-2">
                            Buscando: <?= htmlspecialchars($buscar) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge rounded-pill bg-secondary px-3 py-2">
                            Sin filtro
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (count($ubicaciones) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Código</th>
                            <th>Zona</th>
                            <th>Estante</th>
                            <th>Posición</th>
                            <th>Estado</th>
                            <th>Cajas activas</th>
                            <th>Productos sueltos</th>
                            <th>Observación</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($ubicaciones as $u): ?>
                            <tr>
                                <td>
                                    <span class="code-pill">
                                        <i class="bi bi-geo-alt"></i>
                                        <?= htmlspecialchars($u['codigo']) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($u['zona'] ?? '-') ?></td>

                                <td>
                                    <?php if (!empty($u['estante'])): ?>
                                        <span class="code-pill">
                                            <i class="bi bi-columns-gap"></i>
                                            <?= htmlspecialchars($u['estante']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($u['posicion'])): ?>
                                        <span class="code-pill">
                                            <i class="bi bi-grid-3x3-gap"></i>
                                            <?= htmlspecialchars($u['posicion']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge rounded-pill <?= badgeEstadoUbicacion($u['estado']) ?> px-3 py-2">
                                        <i class="bi <?= iconoEstadoUbicacion($u['estado']) ?> me-1"></i>
                                        <?= htmlspecialchars($u['estado']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="code-pill">
                                        <i class="bi bi-archive"></i>
                                        <?= htmlspecialchars($u['total_cajas']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="code-pill">
                                        <i class="bi bi-box-seam"></i>
                                        <?= htmlspecialchars($u['total_productos_sueltos']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if (!empty($u['observacion'])): ?>
                                        <?= htmlspecialchars($u['observacion']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end">
                                    <div class="action-group justify-content-end">
                                        <a
                                            href="ubicaciones.php?codigo=<?= urlencode($u['codigo']) ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>
                                            Ver
                                        </a>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-dark"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editarUbicacionModal"
                                            data-id="<?= htmlspecialchars($u['id']) ?>"
                                            data-codigo="<?= htmlspecialchars($u['codigo']) ?>"
                                            data-zona="<?= htmlspecialchars($u['zona'] ?? '') ?>"
                                            data-estante="<?= htmlspecialchars($u['estante'] ?? '') ?>"
                                            data-posicion="<?= htmlspecialchars($u['posicion'] ?? '') ?>"
                                            data-estado="<?= htmlspecialchars($u['estado']) ?>"
                                            data-observacion="<?= htmlspecialchars($u['observacion'] ?? '') ?>">
                                            <i class="bi bi-pencil-square me-1"></i>
                                            Editar
                                        </button>
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
                    <i class="bi bi-inbox"></i>
                </div>
                <h5 class="fw-bold mb-2">No hay ubicaciones registradas</h5>
                <p class="mb-0">
                    Crea una nueva ubicación o modifica los filtros de búsqueda.
                </p>
            </div>
        <?php endif; ?>
    </section>

</main>

<!-- Modal Editar Ubicación -->
<div class="modal fade" id="editarUbicacionModal" tabindex="-1" aria-labelledby="editarUbicacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="../actions/guardar_ubicacion.php" class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editarUbicacionModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>
                    Editar ubicación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4">

                <input type="hidden" name="id" id="edit_id">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Código</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-qr-code"></i>
                        </span>
                        <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Zona</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-building"></i>
                        </span>
                        <input type="text" name="zona" id="edit_zona" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Estante</label>
                        <input type="text" name="estante" id="edit_estante" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Posición</label>
                        <input type="text" name="posicion" id="edit_posicion" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Estado</label>
                    <select name="estado" id="edit_estado" class="form-select">
                        <option value="Libre">Libre</option>
                        <option value="Ocupado">Ocupado</option>
                        <option value="Revisar">Revisar</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Observación</label>
                    <textarea name="observacion" id="edit_observacion" class="form-control" rows="3"></textarea>
                </div>

                <div class="alert alert-info border-0 rounded-4 mb-0 d-flex gap-2">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div>
                        Al guardar, se actualizarán los datos de esta ubicación.
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

<!-- Bootstrap JS necesario para navbar, sidebar, offcanvas y modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const editarModal = document.getElementById('editarUbicacionModal');

    if (editarModal) {
        editarModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;

            document.getElementById('edit_id').value = button.getAttribute('data-id');
            document.getElementById('edit_codigo').value = button.getAttribute('data-codigo');
            document.getElementById('edit_zona').value = button.getAttribute('data-zona');
            document.getElementById('edit_estante').value = button.getAttribute('data-estante');
            document.getElementById('edit_posicion').value = button.getAttribute('data-posicion');
            document.getElementById('edit_estado').value = button.getAttribute('data-estado');
            document.getElementById('edit_observacion').value = button.getAttribute('data-observacion');
        });
    }
</script>

<?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</body>

</html>