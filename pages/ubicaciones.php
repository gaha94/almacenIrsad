<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

$codigo = trim($_GET['codigo'] ?? '');

$ubicacion = null;
$cajas = [];
$productosSueltos = [];

if ($codigo !== '') {
    $stmtUbicacion = $pdo->prepare("
        SELECT *
        FROM ubicaciones
        WHERE codigo = :codigo
        LIMIT 1
    ");
    $stmtUbicacion->execute([
        ':codigo' => $codigo
    ]);
    $ubicacion = $stmtUbicacion->fetch();

    if ($ubicacion) {
        $stmtCajas = $pdo->prepare("
            SELECT 
                c.id,
                c.numero_caja,
                c.estado,
                c.observacion,
                pl.codigo AS packing_codigo,
                pl.ruta_archivo,
                COUNT(p.id) AS total_productos
            FROM cajas c
            LEFT JOIN packing_lists pl ON pl.id = c.packing_list_id
            LEFT JOIN productos p 
                ON p.caja_id = c.id 
                AND p.estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
            WHERE c.ubicacion_id = :ubicacion_id
              AND c.estado IN ('Activa', 'Revisar')
            GROUP BY 
                c.id,
                c.numero_caja,
                c.estado,
                c.observacion,
                pl.codigo,
                pl.ruta_archivo
            ORDER BY c.numero_caja ASC
        ");
        $stmtCajas->execute([
            ':ubicacion_id' => $ubicacion['id']
        ]);
        $cajas = $stmtCajas->fetchAll();

        $stmtProductos = $pdo->prepare("
            SELECT 
                p.id,
                p.codigo_producto,
                p.codigo_alterno,
                p.descripcion,
                p.estado,
                p.observacion
            FROM productos p
            WHERE p.ubicacion_id = :ubicacion_id
              AND p.caja_id IS NULL
              AND p.estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
            ORDER BY p.codigo_producto ASC
        ");
        $stmtProductos->execute([
            ':ubicacion_id' => $ubicacion['id']
        ]);
        $productosSueltos = $stmtProductos->fetchAll();
    }
}

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

function badgeEstadoCaja($estado): string
{
    return match ($estado) {
        'Activa' => 'bg-success',
        'Revisar' => 'bg-info text-dark',
        'Consolidada' => 'bg-warning text-dark',
        'Desechada' => 'bg-danger',
        'Vacia' => 'bg-secondary',
        default => 'bg-secondary',
    };
}

function badgeEstadoProducto($estado): string
{
    return match ($estado) {
        'Disponible' => 'bg-success',
        'Alerta pendiente' => 'bg-warning text-dark',
        'Revisar' => 'bg-info text-dark',
        'No encontrado' => 'bg-dark',
        'Retirado' => 'bg-danger',
        'Inactivo' => 'bg-secondary',
        default => 'bg-secondary',
    };
}

$totalCajas = count($cajas);
$totalProductosSueltos = count($productosSueltos);
$totalProductosEnCajas = array_sum(array_map(fn($caja) => (int)$caja['total_productos'], $cajas));
$totalItems = $totalProductosEnCajas + $totalProductosSueltos;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Ubicación - Almacén</title>
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

        .search-card,
        .summary-card,
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

        .location-card {
            border: none;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.06);
        }

        .location-code {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #212529;
            color: #fff;
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 700;
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

        .table thead th {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .empty-state {
            padding: 42px 20px;
            text-align: center;
            color: #6c757d;
        }

        .empty-state-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: #f1f3f5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #6c757d;
            margin-bottom: 14px;
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
            gap: 7px;
            flex-wrap: wrap;
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
    <?php include __DIR__ . '/../includes/scanner-modal.php'; ?>

    <main class="container py-4">

        <section class="page-header mb-4">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-2">
                        <i class="bi bi-geo-alt me-2"></i>
                        Consultar ubicación
                    </h2>
                    <p class="mb-0 text-white-50">
                        Escanea o escribe el código de una ubicación para ver sus cajas y productos.
                    </p>
                </div>

                <div class="col-md-4 text-md-end">
                    <?php if (!esOperario()): ?>
                        <a href="../dashboard.php" class="btn btn-light btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i>
                            Volver al panel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="card search-card mb-4">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="ubicaciones.php" class="row g-3 align-items-end">
                    <div class="col-12 col-lg-8">
                        <label for="input_ubicacion" class="form-label fw-semibold">
                            Código de ubicación
                        </label>

                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light">
                                <i class="bi bi-upc-scan"></i>
                            </span>
                            <input
                                type="text"
                                name="codigo"
                                id="input_ubicacion"
                                class="form-control"
                                placeholder="Ejemplo: A1, A2, A10..."
                                value="<?= htmlspecialchars($codigo) ?>"
                                data-autosubmit="1"
                                autofocus>
                        </div>

                        <div class="form-text">
                            Puedes escribir el código o escanear el QR de la ubicación.
                        </div>
                    </div>

                    <div class="col-6 col-lg-2">
                        <button
                            type="button"
                            class="btn btn-outline-dark btn-lg w-100"
                            onclick="abrirScanner('input_ubicacion')">
                            <i class="bi bi-qr-code-scan me-1"></i>
                            Escanear
                        </button>
                    </div>

                    <div class="col-6 col-lg-2">
                        <button class="btn btn-primary btn-lg w-100" type="submit">
                            <i class="bi bi-search me-1"></i>
                            Ver
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <?php if ($codigo !== ''): ?>

            <?php if (!$ubicacion): ?>

                <section class="card main-card">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Ubicación no encontrada</h5>
                        <p class="mb-0">
                            No existe la ubicación <strong><?= htmlspecialchars($codigo) ?></strong>.
                        </p>
                    </div>
                </section>

            <?php else: ?>

                <section class="card location-card mb-4">
                    <div class="card-body p-3 p-md-4">
                        <div class="row align-items-center g-3">
                            <div class="col-md-7">
                                <p class="text-muted-small mb-2">Ubicación consultada</p>

                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="location-code">
                                        <i class="bi bi-geo-alt-fill"></i>
                                        <?= htmlspecialchars($ubicacion['codigo']) ?>
                                    </span>

                                    <span class="badge rounded-pill <?= badgeEstadoUbicacion($ubicacion['estado']) ?> px-3 py-2">
                                        <?= htmlspecialchars($ubicacion['estado']) ?>
                                    </span>
                                </div>

                                <?php if (!empty($ubicacion['observacion'])): ?>
                                    <div class="alert alert-light border rounded-4 mt-3 mb-0">
                                        <strong>Observación:</strong>
                                        <?= htmlspecialchars($ubicacion['observacion']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-5">
                                <div class="row g-2">
                                    <div class="col-4">
                                        <div class="border rounded-4 p-3 text-center bg-light">
                                            <div class="fw-bold fs-4"><?= htmlspecialchars($totalCajas) ?></div>
                                            <div class="text-muted-small">Cajas</div>
                                        </div>
                                    </div>

                                    <div class="col-4">
                                        <div class="border rounded-4 p-3 text-center bg-light">
                                            <div class="fw-bold fs-4"><?= htmlspecialchars($totalProductosSueltos) ?></div>
                                            <div class="text-muted-small">Sueltos</div>
                                        </div>
                                    </div>

                                    <div class="col-4">
                                        <div class="border rounded-4 p-3 text-center bg-light">
                                            <div class="fw-bold fs-4"><?= htmlspecialchars($totalItems) ?></div>
                                            <div class="text-muted-small">Items</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row g-4">

                    <div class="col-lg-6">
                        <div class="card main-card h-100">
                            <div class="card-header bg-white border-0 p-3 p-md-4">
                                <h5 class="section-title mb-1">
                                    <i class="bi bi-archive me-1"></i>
                                    Cajas en esta ubicación
                                </h5>
                                <p class="text-muted-small mb-0">
                                    Cajas activas o en revisión dentro de esta ubicación.
                                </p>
                            </div>

                            <?php if (count($cajas) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th>Caja</th>
                                                <th>Estado</th>
                                                <th>Productos</th>
                                                <th>Packing List</th>
                                                <?php if (puedeModificar()): ?>
                                                    <th class="text-end">Acción</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($cajas as $caja): ?>
                                                <tr>
                                                    <td>
                                                        <span class="code-pill">
                                                            <i class="bi bi-archive"></i>
                                                            <?= htmlspecialchars($caja['numero_caja']) ?>
                                                        </span>

                                                        <?php if (!empty($caja['observacion'])): ?>
                                                            <br>
                                                            <small class="text-muted ms-1">
                                                                <?= htmlspecialchars($caja['observacion']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td>
                                                        <span class="badge rounded-pill <?= badgeEstadoCaja($caja['estado']) ?> px-3 py-2">
                                                            <?= htmlspecialchars($caja['estado']) ?>
                                                        </span>
                                                    </td>

                                                    <td>
                                                        <strong><?= htmlspecialchars($caja['total_productos']) ?></strong>
                                                    </td>

                                                    <td>
                                                        <?php if (!empty($caja['ruta_archivo'])): ?>
                                                            <a href="../<?= htmlspecialchars($caja['ruta_archivo']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                                                Ver PDF
                                                            </a>
                                                        <?php elseif (!empty($caja['packing_codigo'])): ?>
                                                            <span class="code-pill">
                                                                <i class="bi bi-file-earmark-text"></i>
                                                                <?= htmlspecialchars($caja['packing_codigo']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sin PL</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <?php if (puedeModificar()): ?>
                                                        <td class="text-end">
                                                            <div class="action-group justify-content-end">
                                                                <a
                                                                    href="consolidar-cajas.php?origen=<?= urlencode($caja['numero_caja']) ?>"
                                                                    class="btn btn-sm btn-warning">
                                                                    <i class="bi bi-box-arrow-in-down me-1"></i>
                                                                    Consolidar
                                                                </a>
                                                            </div>
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
                                    <h6 class="fw-bold mb-2">No hay cajas activas</h6>
                                    <p class="mb-0">
                                        Esta ubicación no tiene cajas activas o en revisión.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card main-card h-100">
                            <div class="card-header bg-white border-0 p-3 p-md-4">
                                <h5 class="section-title mb-1">
                                    <i class="bi bi-box-seam me-1"></i>
                                    Productos sueltos
                                </h5>
                                <p class="text-muted-small mb-0">
                                    Productos que están en la ubicación sin estar asignados a una caja.
                                </p>
                            </div>

                            <?php if (count($productosSueltos) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th>Código</th>
                                                <th>Código alterno</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($productosSueltos as $producto): ?>
                                                <tr>
                                                    <td>
                                                        <span class="code-pill">
                                                            <i class="bi bi-box-seam"></i>
                                                            <?= htmlspecialchars($producto['codigo_producto']) ?>
                                                        </span>

                                                        <?php if (!empty($producto['descripcion'])): ?>
                                                            <br>
                                                            <small class="text-muted ms-1">
                                                                <?= htmlspecialchars($producto['descripcion']) ?>
                                                            </small>
                                                        <?php endif; ?>

                                                        <?php if (!empty($producto['observacion'])): ?>
                                                            <br>
                                                            <small class="text-muted ms-1">
                                                                Obs: <?= htmlspecialchars($producto['observacion']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td>
                                                        <?php if (!empty($producto['codigo_alterno'])): ?>
                                                            <span class="code-pill">
                                                                <i class="bi bi-upc"></i>
                                                                <?= htmlspecialchars($producto['codigo_alterno']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td>
                                                        <span class="badge rounded-pill <?= badgeEstadoProducto($producto['estado']) ?> px-3 py-2">
                                                            <?= htmlspecialchars($producto['estado']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-box"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">No hay productos sueltos</h6>
                                    <p class="mb-0">
                                        Esta ubicación no tiene productos sueltos activos.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </section>

            <?php endif; ?>

        <?php else: ?>

            <section class="card main-card">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-qr-code-scan"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Consulta una ubicación</h5>
                    <p class="mb-0">
                        Escribe una ubicación o escanea el QR para ver su contenido.
                    </p>
                </div>
            </section>

        <?php endif; ?>

    </main>

    <!-- Bootstrap JS necesario para navbar, dropdowns y scanner/modal -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php include __DIR__ . '/../includes/pwa-script.php'; ?>
    
</body>

</html>