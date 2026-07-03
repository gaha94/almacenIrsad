<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

$whereAlertas = "";
$paramsAlertas = [];

if (esOperario()) {
    $whereAlertas = "WHERE a.creado_por = :usuario_id";
    $paramsAlertas[':usuario_id'] = $_SESSION['usuario_id'];
}

$sql = "
    SELECT 
        a.id,
        a.tipo_alerta,
        a.descripcion,
        a.estado,
        a.created_at,
        p.codigo_producto,
        p.codigo_alterno,
        c.numero_caja,
        u.codigo AS ubicacion_codigo,
        creador.nombre AS creado_por_nombre,
        revisor.nombre AS revisado_por_nombre,
        a.fecha_revision,
        a.accion_tomada
    FROM alertas a
    LEFT JOIN productos p ON p.id = a.producto_id
    LEFT JOIN cajas c ON c.id = a.caja_id
    LEFT JOIN ubicaciones u ON u.id = a.ubicacion_id
    INNER JOIN usuarios creador ON creador.id = a.creado_por
    LEFT JOIN usuarios revisor ON revisor.id = a.revisado_por
    $whereAlertas
    ORDER BY 
        FIELD(a.estado, 'Pendiente', 'En revision', 'Aprobada', 'Rechazada', 'Cerrada'),
        a.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($paramsAlertas);
$alertas = $stmt->fetchAll();

$msg = $_GET['msg'] ?? '';

$totalAlertas = count($alertas);
$totalPendientes = count(array_filter($alertas, fn($a) => $a['estado'] === 'Pendiente'));
$totalAprobadas = count(array_filter($alertas, fn($a) => $a['estado'] === 'Aprobada'));
$totalRechazadas = count(array_filter($alertas, fn($a) => $a['estado'] === 'Rechazada'));

function estadoBadge($estado)
{
    return match ($estado) {
        'Pendiente' => 'bg-warning text-dark',
        'En revision' => 'bg-info text-dark',
        'Aprobada' => 'bg-success',
        'Rechazada' => 'bg-danger',
        'Cerrada' => 'bg-secondary',
        default => 'bg-secondary',
    };
}

function estadoIcono($estado)
{
    return match ($estado) {
        'Pendiente' => 'bi-hourglass-split',
        'En revision' => 'bi-search',
        'Aprobada' => 'bi-check-circle',
        'Rechazada' => 'bi-x-circle',
        'Cerrada' => 'bi-lock',
        default => 'bi-info-circle',
    };
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Alertas - Almacén</title>
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
            padding: 26px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .summary-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
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

        .main-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.06);
            overflow: hidden;
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

        .alert-description {
            max-width: 330px;
            white-space: normal;
        }

        .code-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background-color: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
        }

        .empty-state-icon {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background-color: #f1f3f5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            color: #6c757d;
            margin-bottom: 16px;
        }

        .btn-action {
            min-width: 92px;
        }

        .text-muted-small {
            font-size: 0.9rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 22px;
            }

            .alert-description {
                max-width: 220px;
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
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Alertas
                    </h2>
                    <p class="mb-0 text-white-50">
                        Revisa, aprueba o rechaza alertas generadas en productos, cajas y ubicaciones.
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

        <?php if ($msg === 'alerta_creada'): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>
                    Alerta creada correctamente.
                </div>
            </div>
        <?php endif; ?>

        <section class="mb-4">
            <div class="row g-3">

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card summary-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="summary-icon bg-primary-subtle text-primary">
                                <i class="bi bi-list-check"></i>
                            </div>
                            <div>
                                <p class="text-muted-small mb-1">Total alertas</p>
                                <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalAlertas) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card summary-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="summary-icon bg-warning-subtle text-warning">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div>
                                <p class="text-muted-small mb-1">Pendientes</p>
                                <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalPendientes) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card summary-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="summary-icon bg-success-subtle text-success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div>
                                <p class="text-muted-small mb-1">Aprobadas</p>
                                <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalAprobadas) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card summary-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="summary-icon bg-danger-subtle text-danger">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div>
                                <p class="text-muted-small mb-1">Rechazadas</p>
                                <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalRechazadas) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <section class="card main-card">

            <div class="card-header bg-white border-0 p-3 p-md-4">
                <div class="row align-items-center g-3">
                    <div class="col-md-7">
                        <h5 class="fw-bold mb-1">Listado de alertas</h5>
                        <p class="text-muted-small mb-0">
                            Ordenadas por estado y fecha de creación.
                        </p>
                    </div>

                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input 
                                type="text" 
                                id="buscarTabla" 
                                class="form-control border-start-0" 
                                placeholder="Buscar por producto, caja, ubicación o estado..."
                            >
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($alertas) > 0): ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaAlertas">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Caja</th>
                                <th>Ubicación</th>
                                <th>Tipo</th>
                                <th>Comentario</th>
                                <th>Creado por</th>
                                <th>Estado</th>

                                <?php if (puedeModificar()): ?>
                                    <th class="text-center">Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($alertas as $a): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars(date('d/m/Y', strtotime($a['created_at']))) ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars(date('H:i', strtotime($a['created_at']))) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?php if (!empty($a['codigo_producto'])): ?>
                                            <span class="code-pill">
                                                <i class="bi bi-box-seam"></i>
                                                <?= htmlspecialchars($a['codigo_producto']) ?>
                                            </span>

                                            <?php if (!empty($a['codigo_alterno'])): ?>
                                                <br>
                                                <small class="text-muted ms-1">
                                                    Alt: <?= htmlspecialchars($a['codigo_alterno']) ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($a['numero_caja'])): ?>
                                            <span class="code-pill">
                                                <i class="bi bi-archive"></i>
                                                <?= htmlspecialchars($a['numero_caja']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($a['ubicacion_codigo'])): ?>
                                            <span class="code-pill">
                                                <i class="bi bi-geo-alt"></i>
                                                <?= htmlspecialchars($a['ubicacion_codigo']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="fw-semibold">
                                            <?= htmlspecialchars($a['tipo_alerta']) ?>
                                        </span>
                                    </td>

                                    <td class="alert-description">
                                        <?= htmlspecialchars($a['descripcion']) ?>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-person-circle text-muted"></i>
                                            <span><?= htmlspecialchars($a['creado_por_nombre']) ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge rounded-pill <?= estadoBadge($a['estado']) ?> px-3 py-2">
                                            <i class="bi <?= estadoIcono($a['estado']) ?> me-1"></i>
                                            <?= htmlspecialchars($a['estado']) ?>
                                        </span>
                                    </td>

                                    <?php if (puedeModificar()): ?>
                                        <td class="text-center">
                                            <?php if ($a['estado'] === 'Pendiente'): ?>
                                                <div class="d-flex flex-column flex-lg-row gap-2 justify-content-center">
                                                    <a 
                                                        href="../actions/revisar_alerta.php?id=<?= urlencode($a['id']) ?>&accion=aprobar"
                                                        class="btn btn-sm btn-success btn-action"
                                                        onclick="return confirm('¿Aprobar esta alerta?')"
                                                    >
                                                        <i class="bi bi-check-circle me-1"></i>
                                                        Aprobar
                                                    </a>

                                                    <a 
                                                        href="../actions/revisar_alerta.php?id=<?= urlencode($a['id']) ?>&accion=rechazar"
                                                        class="btn btn-sm btn-outline-danger btn-action"
                                                        onclick="return confirm('¿Rechazar esta alerta?')"
                                                    >
                                                        <i class="bi bi-x-circle me-1"></i>
                                                        Rechazar
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-start small text-muted">
                                                    <div>
                                                        <i class="bi bi-person-check me-1"></i>
                                                        <?= htmlspecialchars($a['revisado_por_nombre'] ?? 'Sin revisor') ?>
                                                    </div>

                                                    <?php if (!empty($a['fecha_revision'])): ?>
                                                        <div>
                                                            <i class="bi bi-calendar-check me-1"></i>
                                                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime($a['fecha_revision']))) ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($a['accion_tomada'])): ?>
                                                        <div class="mt-1">
                                                            <?= htmlspecialchars($a['accion_tomada']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
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
                    <h5 class="fw-bold mb-2">No hay alertas registradas</h5>
                    <p class="text-muted mb-0">
                        Cuando se genere una alerta, aparecerá en esta sección.
                    </p>
                </div>

            <?php endif; ?>

        </section>

    </main>

    <!-- Bootstrap JS necesario para navbar responsive y dropdowns -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const inputBusqueda = document.getElementById('buscarTabla');
        const tablaAlertas = document.getElementById('tablaAlertas');

        if (inputBusqueda && tablaAlertas) {
            inputBusqueda.addEventListener('keyup', function () {
                const filtro = this.value.toLowerCase().trim();
                const filas = tablaAlertas.querySelectorAll('tbody tr');

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