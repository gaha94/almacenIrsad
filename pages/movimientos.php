<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

if (!puedeModificar()) {
    header('Location: ../dashboard.php');
    exit;
}

$tipo = trim($_GET['tipo'] ?? '');
$usuario = trim($_GET['usuario'] ?? '');
$fechaDesde = trim($_GET['fecha_desde'] ?? '');
$fechaHasta = trim($_GET['fecha_hasta'] ?? '');
$buscar = trim($_GET['buscar'] ?? '');

$stmtUsuarios = $pdo->query("
    SELECT id, nombre, usuario
    FROM usuarios
    ORDER BY nombre ASC
");

$usuarios = $stmtUsuarios->fetchAll();

$where = [];
$params = [];

if ($tipo !== '') {
    $where[] = "m.tipo_movimiento = :tipo";
    $params[':tipo'] = $tipo;
}

if ($usuario !== '') {
    $where[] = "m.usuario_id = :usuario_id";
    $params[':usuario_id'] = $usuario;
}

if ($fechaDesde !== '') {
    $where[] = "DATE(m.created_at) >= :fecha_desde";
    $params[':fecha_desde'] = $fechaDesde;
}

if ($fechaHasta !== '') {
    $where[] = "DATE(m.created_at) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fechaHasta;
}

if ($buscar !== '') {
    $where[] = "(
        p.codigo_producto LIKE :buscar1
        OR p.codigo_alterno LIKE :buscar2
        OR co.numero_caja LIKE :buscar3
        OR cd.numero_caja LIKE :buscar4
        OR uo.codigo LIKE :buscar5
        OR ud.codigo LIKE :buscar6
        OR m.descripcion LIKE :buscar7
    )";

    $params[':buscar1'] = '%' . $buscar . '%';
    $params[':buscar2'] = '%' . $buscar . '%';
    $params[':buscar3'] = '%' . $buscar . '%';
    $params[':buscar4'] = '%' . $buscar . '%';
    $params[':buscar5'] = '%' . $buscar . '%';
    $params[':buscar6'] = '%' . $buscar . '%';
    $params[':buscar7'] = '%' . $buscar . '%';
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        m.id,
        m.tipo_movimiento,
        m.descripcion,
        m.created_at,

        u.nombre AS usuario_nombre,
        u.usuario AS usuario_login,

        p.codigo_producto,
        p.codigo_alterno,

        co.numero_caja AS caja_origen,
        cd.numero_caja AS caja_destino,

        uo.codigo AS ubicacion_origen,
        ud.codigo AS ubicacion_destino,

        a.tipo_alerta,
        a.estado AS estado_alerta
    FROM movimientos m
    INNER JOIN usuarios u ON u.id = m.usuario_id
    LEFT JOIN productos p ON p.id = m.producto_id
    LEFT JOIN cajas co ON co.id = m.caja_origen_id
    LEFT JOIN cajas cd ON cd.id = m.caja_destino_id
    LEFT JOIN ubicaciones uo ON uo.id = m.ubicacion_origen_id
    LEFT JOIN ubicaciones ud ON ud.id = m.ubicacion_destino_id
    LEFT JOIN alertas a ON a.id = m.alerta_id
    $whereSql
    ORDER BY m.created_at DESC
    LIMIT 300
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

$tiposMovimiento = [
    'Ingreso',
    'Retiro',
    'Reubicacion',
    'Consolidacion',
    'Caja desechada',
    'Correccion',
    'Alerta creada',
    'Alerta aprobada',
    'Alerta rechazada'
];

$totalMovimientos = count($movimientos);
$filtrosActivos = 0;

foreach ([$tipo, $usuario, $fechaDesde, $fechaHasta, $buscar] as $filtro) {
    if ($filtro !== '') {
        $filtrosActivos++;
    }
}

function badgeMovimiento($tipo): string
{
    return match ($tipo) {
        'Ingreso' => 'bg-success',
        'Retiro' => 'bg-danger',
        'Reubicacion' => 'bg-primary',
        'Consolidacion' => 'bg-warning text-dark',
        'Caja desechada' => 'bg-dark',
        'Correccion' => 'bg-secondary',
        'Alerta creada' => 'bg-info text-dark',
        'Alerta aprobada' => 'bg-success',
        'Alerta rechazada' => 'bg-danger',
        default => 'bg-secondary',
    };
}

function iconoMovimiento($tipo): string
{
    return match ($tipo) {
        'Ingreso' => 'bi-box-arrow-in-down',
        'Retiro' => 'bi-box-arrow-up-right',
        'Reubicacion' => 'bi-arrow-left-right',
        'Consolidacion' => 'bi-boxes',
        'Caja desechada' => 'bi-trash',
        'Correccion' => 'bi-pencil-square',
        'Alerta creada' => 'bi-exclamation-triangle',
        'Alerta aprobada' => 'bi-check-circle',
        'Alerta rechazada' => 'bi-x-circle',
        default => 'bi-clock-history',
    };
}

function fechaLegible($fecha): string
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
    <title>Historial de movimientos - Almacén</title>
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

        .filter-card,
        .main-card,
        .summary-card {
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

        .detail-cell {
            min-width: 260px;
            max-width: 420px;
            white-space: normal;
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

        @media (max-width: 768px) {
            .page-header {
                padding: 22px;
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
                        <i class="bi bi-clock-history me-2"></i>
                        Historial de movimientos
                    </h2>
                    <p class="mb-0 text-white-50">
                        Consulta ingresos, retiros, reubicaciones, consolidaciones y alertas del almacén.
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

        <section class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card summary-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="summary-icon bg-primary-subtle text-primary">
                            <i class="bi bi-list-check"></i>
                        </div>
                        <div>
                            <p class="text-muted-small mb-1">Movimientos encontrados</p>
                            <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalMovimientos) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card summary-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="summary-icon bg-info-subtle text-info">
                            <i class="bi bi-funnel"></i>
                        </div>
                        <div>
                            <p class="text-muted-small mb-1">Filtros activos</p>
                            <h3 class="fw-bold mb-0"><?= htmlspecialchars($filtrosActivos) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card summary-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="summary-icon bg-secondary-subtle text-secondary">
                            <i class="bi bi-database"></i>
                        </div>
                        <div>
                            <p class="text-muted-small mb-1">Límite mostrado</p>
                            <h3 class="fw-bold mb-0">300</h3>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card filter-card mb-4">
            <div class="card-header bg-white border-0 p-3 p-md-4">
                <h5 class="section-title mb-1">
                    <i class="bi bi-funnel me-1"></i>
                    Filtros
                </h5>
                <p class="text-muted-small mb-0">
                    Refina la búsqueda por producto, caja, ubicación, usuario, tipo o fecha.
                </p>
            </div>

            <div class="card-body p-3 p-md-4 pt-md-0">
                <form method="GET" action="movimientos.php" class="row g-3">

                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold">Buscar</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bi bi-search"></i>
                            </span>
                            <input
                                type="text"
                                name="buscar"
                                class="form-control"
                                placeholder="Producto, caja, ubicación o detalle..."
                                value="<?= htmlspecialchars($buscar) ?>">
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label fw-semibold">Tipo de movimiento</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tiposMovimiento as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $tipo === $item ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-2">
                        <label class="form-label fw-semibold">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="bi bi-person"></i>
                            </span>

                            <select name="usuario" class="form-select">
                                <option value="">Todos</option>

                                <?php foreach ($usuarios as $u): ?>
                                    <option 
                                        value="<?= htmlspecialchars($u['id']) ?>"
                                        <?= (string)$usuario === (string)$u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-6 col-lg-1">
                        <label class="form-label fw-semibold">Desde</label>
                        <input
                            type="date"
                            name="fecha_desde"
                            class="form-control"
                            value="<?= htmlspecialchars($fechaDesde) ?>">
                    </div>

                    <div class="col-6 col-lg-1">
                        <label class="form-label fw-semibold">Hasta</label>
                        <input
                            type="date"
                            name="fecha_hasta"
                            class="form-control"
                            value="<?= htmlspecialchars($fechaHasta) ?>">
                    </div>

                    <div class="col-12 col-lg-1 d-grid">
                        <label class="form-label d-none d-lg-block">&nbsp;</label>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>

                    <div class="col-12 d-flex flex-column flex-md-row gap-2 justify-content-end">
                        <a href="movimientos.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>
                            Limpiar filtros
                        </a>

                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-funnel me-1"></i>
                            Aplicar filtros
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
                            Últimos movimientos
                        </h5>
                        <p class="text-muted-small mb-0">
                            Se muestran como máximo los últimos 300 movimientos según los filtros aplicados.
                        </p>
                    </div>

                    <div class="col-md-4 text-md-end">
                        <?php if ($filtrosActivos > 0): ?>
                            <span class="badge rounded-pill bg-primary px-3 py-2">
                                <?= htmlspecialchars($filtrosActivos) ?> filtro(s) activo(s)
                            </span>
                        <?php else: ?>
                            <span class="badge rounded-pill bg-secondary px-3 py-2">
                                Sin filtros
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (count($movimientos) > 0): ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th>Tipo</th>
                                <th>Producto</th>
                                <th>Caja origen</th>
                                <th>Caja destino</th>
                                <th>Ubicación origen</th>
                                <th>Ubicación destino</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($movimientos as $m): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars(date('d/m/Y', strtotime($m['created_at']))) ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars(date('H:i', strtotime($m['created_at']))) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="bi bi-person-circle text-muted fs-5"></i>
                                            <div>
                                                <strong><?= htmlspecialchars($m['usuario_nombre']) ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($m['usuario_login']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge rounded-pill <?= badgeMovimiento($m['tipo_movimiento']) ?> px-3 py-2">
                                            <i class="bi <?= iconoMovimiento($m['tipo_movimiento']) ?> me-1"></i>
                                            <?= htmlspecialchars($m['tipo_movimiento']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (!empty($m['codigo_producto'])): ?>
                                            <span class="code-pill">
                                                <i class="bi bi-box-seam"></i>
                                                <?= htmlspecialchars($m['codigo_producto']) ?>
                                            </span>

                                            <?php if (!empty($m['codigo_alterno'])): ?>
                                                <br>
                                                <small class="text-muted ms-1">
                                                    Alt: <?= htmlspecialchars($m['codigo_alterno']) ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($m['caja_origen'])): ?>
                                            <span class="code-pill">
                                                <i class="bi bi-archive"></i>
                                                <?= htmlspecialchars($m['caja_origen']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($m['caja_destino'])): ?>
                                            <span class="code-pill">
                                                <i class="bi bi-archive-fill"></i>
                                                <?= htmlspecialchars($m['caja_destino']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($m['ubicacion_origen'])): ?>
                                            <span class="code-pill">
                                                <i class="bi bi-geo-alt"></i>
                                                <?= htmlspecialchars($m['ubicacion_origen']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($m['ubicacion_destino'])): ?>
                                            <span class="code-pill">
                                                <i class="bi bi-geo-alt-fill"></i>
                                                <?= htmlspecialchars($m['ubicacion_destino']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="detail-cell">
                                        <?= htmlspecialchars($m['descripcion'] ?? '-') ?>

                                        <?php if (!empty($m['tipo_alerta'])): ?>
                                            <div class="mt-2">
                                                <span class="badge rounded-pill bg-light text-dark border">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    Alerta: <?= htmlspecialchars($m['tipo_alerta']) ?>
                                                    —
                                                    <?= htmlspecialchars($m['estado_alerta']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
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
                    <h5 class="fw-bold mb-2">No hay movimientos registrados</h5>
                    <p class="mb-0">
                        No se encontraron movimientos con los filtros seleccionados.
                    </p>
                </div>

            <?php endif; ?>

        </section>

    </main>

    <!-- Bootstrap JS necesario para navbar responsive y dropdowns -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php include __DIR__ . '/../includes/pwa-script.php'; ?>
    
</body>

</html>