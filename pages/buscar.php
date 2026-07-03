<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

$q = trim($_GET['q'] ?? '');
$resultados = [];
$cajasEncontradas = [];

if ($q !== '') {
    // Buscar productos
    $sql = "
        SELECT 
            p.id AS producto_id,
            p.codigo_producto,
            p.codigo_alterno,
            p.descripcion,
            p.tipo,
            p.estado AS estado_producto,
            p.observacion AS observacion_producto,

            c.id AS caja_id,
            c.numero_caja,
            c.estado AS estado_caja,

            u.id AS ubicacion_id,
            u.codigo AS ubicacion_codigo,

            pl.id AS packing_list_id,
            pl.codigo AS packing_codigo,
            pl.nombre_archivo,
            pl.ruta_archivo
        FROM productos p
        LEFT JOIN cajas c ON c.id = p.caja_id
        LEFT JOIN ubicaciones u ON u.id = p.ubicacion_id
        LEFT JOIN packing_lists pl ON pl.id = p.packing_list_id
        WHERE 
            p.codigo_producto LIKE :q1
            OR p.codigo_alterno LIKE :q2
            OR c.numero_caja LIKE :q3
        ORDER BY 
            FIELD(p.estado, 'Disponible', 'Alerta pendiente', 'Revisar', 'No encontrado', 'Retirado', 'Inactivo'),
            p.codigo_producto ASC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':q1' => '%' . $q . '%',
        ':q2' => '%' . $q . '%',
        ':q3' => '%' . $q . '%'
    ]);

    $resultados = $stmt->fetchAll();

    // Buscar cajas aunque todavía no tengan productos asociados
    $sqlCajas = "
        SELECT 
            c.id AS caja_id,
            c.numero_caja,
            c.estado AS estado_caja,
            c.observacion,
            u.id AS ubicacion_id,
            u.codigo AS ubicacion_codigo,
            pl.codigo AS packing_codigo,
            pl.nombre_archivo,
            pl.ruta_archivo,
            COUNT(p.id) AS total_productos
        FROM cajas c
        LEFT JOIN ubicaciones u ON u.id = c.ubicacion_id
        LEFT JOIN packing_lists pl ON pl.id = c.packing_list_id
        LEFT JOIN productos p ON p.caja_id = c.id
        WHERE c.numero_caja LIKE :cq1
        GROUP BY 
            c.id,
            c.numero_caja,
            c.estado,
            c.observacion,
            u.id,
            u.codigo,
            pl.codigo,
            pl.nombre_archivo,
            pl.ruta_archivo
        ORDER BY c.numero_caja ASC
        LIMIT 50
    ";

    $stmtCajas = $pdo->prepare($sqlCajas);
    $stmtCajas->execute([
        ':cq1' => '%' . $q . '%'
    ]);

    $cajasEncontradas = $stmtCajas->fetchAll();
}

$totalProductosEncontrados = count($resultados);
$totalCajasEncontradas = count($cajasEncontradas);

function badgeEstadoCaja($estadoCaja)
{
    return match ($estadoCaja) {
        'Activa' => 'bg-success',
        'Consolidada' => 'bg-warning text-dark',
        'Desechada' => 'bg-danger',
        'Vacia' => 'bg-info text-dark',
        'Revisar' => 'bg-dark',
        default => 'bg-secondary',
    };
}

function badgeEstadoProducto($estado)
{
    return match ($estado) {
        'Disponible' => 'bg-success',
        'Alerta pendiente' => 'bg-warning text-dark',
        'Retirado' => 'bg-danger',
        'Revisar' => 'bg-info text-dark',
        'No encontrado' => 'bg-dark',
        'Inactivo' => 'bg-secondary',
        default => 'bg-secondary',
    };
}

function iconoEstadoProducto($estado)
{
    return match ($estado) {
        'Disponible' => 'bi-check-circle',
        'Alerta pendiente' => 'bi-exclamation-triangle',
        'Retirado' => 'bi-box-arrow-right',
        'Revisar' => 'bi-search',
        'No encontrado' => 'bi-x-circle',
        'Inactivo' => 'bi-dash-circle',
        default => 'bi-info-circle',
    };
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Buscar producto - Almacén</title>
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
        .main-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.06);
            overflow: hidden;
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
            justify-content: center;
            align-items: center;
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
            padding: 4px 10px;
            font-size: 0.84rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .location-badge {
            font-size: 0.9rem;
            border-radius: 999px;
            padding: 7px 11px;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
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
            gap: 7px;
            flex-wrap: wrap;
        }

        .modal-content {
            border: none;
            border-radius: 18px;
            overflow: hidden;
        }

        .modal-header {
            background-color: #212529;
            color: white;
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
                        <i class="bi bi-search me-2"></i>
                        <?= esOperario() ? 'Buscar producto o caja' : 'Buscar producto' ?>
                    </h2>
                    <p class="mb-0 text-white-50">
                        Consulta productos, cajas, ubicaciones y packing list de forma rápida.
                    </p>
                </div>

                <?php if (!esOperario()): ?>
                    <div class="col-md-4 text-md-end">
                        <a href="../dashboard.php" class="btn btn-light btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i>
                            Volver al panel
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card search-card mb-4">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="buscar.php" class="row g-3 align-items-center">
                    <div class="col-12 col-lg-10">
                        <label for="q" class="form-label fw-semibold">
                            Código de producto, código alterno o N° de caja
                        </label>

                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light">
                                <i class="bi bi-upc-scan"></i>
                            </span>
                            <input
                                type="text"
                                id="q"
                                name="q"
                                class="form-control"
                                placeholder="Ejemplo: PROD-001, ALT-001 o CAJA-01"
                                value="<?= htmlspecialchars($q) ?>"
                                autofocus>
                        </div>

                        <div class="form-text">
                            Puedes buscar por coincidencia parcial.
                        </div>
                    </div>

                    <div class="col-12 col-lg-2">
                        <label class="form-label d-none d-lg-block">&nbsp;</label>
                        <button class="btn btn-primary btn-lg w-100" type="submit">
                            <i class="bi bi-search me-1"></i>
                            Buscar
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <?php if ($q !== ''): ?>

            <section class="mb-4">
                <div class="row g-3">

                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card summary-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="summary-icon bg-primary-subtle text-primary">
                                    <i class="bi bi-search"></i>
                                </div>
                                <div>
                                    <p class="text-muted-small mb-1">Búsqueda realizada</p>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($q) ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card summary-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="summary-icon bg-success-subtle text-success">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                                <div>
                                    <p class="text-muted-small mb-1">Productos encontrados</p>
                                    <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalProductosEncontrados) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card summary-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="summary-icon bg-warning-subtle text-warning">
                                    <i class="bi bi-archive"></i>
                                </div>
                                <div>
                                    <p class="text-muted-small mb-1">Cajas encontradas</p>
                                    <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalCajasEncontradas) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <?php if ($totalProductosEncontrados === 0 && $totalCajasEncontradas === 0): ?>

                <section class="card main-card">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="bi bi-search"></i>
                        </div>
                        <h5 class="fw-bold mb-2">No se encontraron resultados</h5>
                        <p class="text-muted mb-3">
                            No existe coincidencia para el código o caja ingresada.
                        </p>
                        <button
                            type="button"
                            class="btn btn-warning rounded-pill px-4"
                            data-bs-toggle="modal"
                            data-bs-target="#alertaModal"
                            data-producto-id=""
                            data-caja-id=""
                            data-ubicacion-id=""
                            data-codigo="<?= htmlspecialchars($q) ?>">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Reportar alerta
                        </button>
                    </div>
                </section>

            <?php else: ?>

                <?php if ($totalCajasEncontradas > 0): ?>

                    <section class="card main-card mb-4">
                        <div class="card-header bg-white border-0 p-3 p-md-4">
                            <h5 class="section-title mb-1">
                                <i class="bi bi-archive me-1"></i>
                                Cajas encontradas
                            </h5>
                            <p class="text-muted-small mb-0">
                                Resultados relacionados al número de caja buscado.
                            </p>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>N° Caja</th>
                                        <th>Ubicación</th>
                                        <th>Estado</th>
                                        <th>Productos</th>
                                        <th>Packing List</th>
                                        <th>Observación</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($cajasEncontradas as $caja): ?>
                                        <tr>
                                            <td>
                                                <span class="code-pill">
                                                    <i class="bi bi-archive"></i>
                                                    <?= htmlspecialchars($caja['numero_caja']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="badge bg-dark location-badge">
                                                    <i class="bi bi-geo-alt me-1"></i>
                                                    <?= htmlspecialchars($caja['ubicacion_codigo'] ?? 'Sin ubicación') ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="badge rounded-pill <?= badgeEstadoCaja($caja['estado_caja']) ?> px-3 py-2">
                                                    <?= htmlspecialchars($caja['estado_caja']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="fw-bold">
                                                    <?= htmlspecialchars($caja['total_productos']) ?>
                                                </span>
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
                                                    <span class="text-muted">Sin packing list</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($caja['observacion'] ?? '-') ?>
                                            </td>

                                            <td>
                                                <div class="action-group">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-warning"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#alertaModal"
                                                        data-producto-id=""
                                                        data-caja-id="<?= htmlspecialchars($caja['caja_id']) ?>"
                                                        data-ubicacion-id="<?= htmlspecialchars($caja['ubicacion_id'] ?? '') ?>"
                                                        data-codigo="Caja <?= htmlspecialchars($caja['numero_caja']) ?>">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        Alerta
                                                    </button>

                                                    <?php if (puedeModificar()): ?>
                                                        <a href="cajas.php?id=<?= urlencode($caja['caja_id']) ?>" class="btn btn-sm btn-outline-dark">
                                                            <i class="bi bi-pencil-square me-1"></i>
                                                            Editar
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                <?php endif; ?>

                <?php if ($totalProductosEncontrados > 0): ?>

                    <section class="card main-card">
                        <div class="card-header bg-white border-0 p-3 p-md-4">
                            <h5 class="section-title mb-1">
                                <i class="bi bi-box-seam me-1"></i>
                                Productos encontrados
                            </h5>
                            <p class="text-muted-small mb-0">
                                Resultados relacionados al producto, código alterno o caja.
                            </p>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Código producto</th>
                                        <th>Código alterno</th>
                                        <th>Tipo</th>
                                        <th>Caja</th>
                                        <th>Ubicación</th>
                                        <th>Estado</th>
                                        <th>Packing List</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($resultados as $row): ?>
                                        <tr>
                                            <td>
                                                <span class="code-pill">
                                                    <i class="bi bi-box-seam"></i>
                                                    <?= htmlspecialchars($row['codigo_producto']) ?>
                                                </span>

                                                <?php if (!empty($row['descripcion'])): ?>
                                                    <br>
                                                    <small class="text-muted ms-1">
                                                        <?= htmlspecialchars($row['descripcion']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($row['codigo_alterno'])): ?>
                                                    <span class="code-pill">
                                                        <i class="bi bi-upc"></i>
                                                        <?= htmlspecialchars($row['codigo_alterno']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if ($row['tipo'] === 'Suelto'): ?>
                                                    <span class="badge rounded-pill bg-info text-dark px-3 py-2">
                                                        <i class="bi bi-box me-1"></i>
                                                        Suelto
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-primary px-3 py-2">
                                                        <i class="bi bi-archive me-1"></i>
                                                        En caja
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($row['numero_caja'])): ?>
                                                    <span class="code-pill">
                                                        <i class="bi bi-archive"></i>
                                                        <?= htmlspecialchars($row['numero_caja']) ?>
                                                    </span>

                                                    <?php if (!empty($row['estado_caja'])): ?>
                                                        <br>
                                                        <small class="text-muted ms-1">
                                                            <?= htmlspecialchars($row['estado_caja']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <span class="badge bg-dark location-badge">
                                                    <i class="bi bi-geo-alt me-1"></i>
                                                    <?= htmlspecialchars($row['ubicacion_codigo'] ?? 'Sin ubicación') ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="badge rounded-pill <?= badgeEstadoProducto($row['estado_producto']) ?> px-3 py-2">
                                                    <i class="bi <?= iconoEstadoProducto($row['estado_producto']) ?> me-1"></i>
                                                    <?= htmlspecialchars($row['estado_producto']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php if (!empty($row['ruta_archivo'])): ?>
                                                    <a href="../<?= htmlspecialchars($row['ruta_archivo']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-file-earmark-pdf me-1"></i>
                                                        Ver PDF
                                                    </a>
                                                <?php elseif (!empty($row['packing_codigo'])): ?>
                                                    <span class="code-pill">
                                                        <i class="bi bi-file-earmark-text"></i>
                                                        <?= htmlspecialchars($row['packing_codigo']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <div class="action-group">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-warning"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#alertaModal"
                                                        data-producto-id="<?= htmlspecialchars($row['producto_id']) ?>"
                                                        data-caja-id="<?= htmlspecialchars($row['caja_id'] ?? '') ?>"
                                                        data-ubicacion-id="<?= htmlspecialchars($row['ubicacion_id'] ?? '') ?>"
                                                        data-codigo="<?= htmlspecialchars($row['codigo_producto']) ?>">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        Alerta
                                                    </button>

                                                    <?php if (puedeModificar()): ?>
                                                        <a href="productos.php?id=<?= urlencode($row['producto_id']) ?>" class="btn btn-sm btn-outline-dark">
                                                            <i class="bi bi-pencil-square me-1"></i>
                                                            Editar
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                <?php else: ?>

                    <?php if ($totalCajasEncontradas > 0): ?>
                        <div class="alert alert-info border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill fs-5"></i>
                            <div>
                                Esta caja existe, pero todavía no tiene productos cargados.
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            <?php endif; ?>

        <?php else: ?>

            <section class="card main-card">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-upc-scan"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Realiza una búsqueda</h5>
                    <p class="text-muted mb-0">
                        Ingresa un código de producto, código alterno o número de caja para consultar su ubicación.
                    </p>
                </div>
            </section>

        <?php endif; ?>

    </main>

    <!-- Modal Crear Alerta -->
    <div class="modal fade" id="alertaModal" tabindex="-1" aria-labelledby="alertaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="../actions/crear_alerta.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="alertaModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Reportar alerta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <input type="hidden" name="producto_id" id="modal_producto_id">
                    <input type="hidden" name="caja_id" id="modal_caja_id">
                    <input type="hidden" name="ubicacion_id" id="modal_ubicacion_id">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Producto / Caja</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-box-seam"></i>
                            </span>
                            <input type="text" id="modal_codigo" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de alerta</label>
                        <select name="tipo_alerta" class="form-select" required>
                            <option value="Producto no encontrado">Producto no encontrado</option>
                            <option value="Caja no encontrada">Caja no encontrada</option>
                            <option value="Ubicacion incorrecta">Ubicación incorrecta</option>
                            <option value="Producto en otra caja">Producto en otra caja</option>
                            <option value="Caja danada">Caja dañada</option>
                            <option value="Packing list incorrecto">Packing list incorrecto</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comentario</label>
                        <textarea 
                            name="descripcion" 
                            class="form-control" 
                            rows="4" 
                            placeholder="Describe qué pasó. Ejemplo: el producto no se encontró en la ubicación indicada."
                            required></textarea>
                    </div>

                    <div class="alert alert-info border-0 rounded-4 mb-0 d-flex gap-2">
                        <i class="bi bi-info-circle-fill fs-5"></i>
                        <div>
                            Esta alerta será enviada para revisión de un supervisor o administrador.
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save me-1"></i>
                        Guardar alerta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS necesario para navbar, dropdowns y modal -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const alertaModal = document.getElementById('alertaModal');

        if (alertaModal) {
            alertaModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;

                document.getElementById('modal_producto_id').value = button.getAttribute('data-producto-id') || '';
                document.getElementById('modal_caja_id').value = button.getAttribute('data-caja-id') || '';
                document.getElementById('modal_ubicacion_id').value = button.getAttribute('data-ubicacion-id') || '';
                document.getElementById('modal_codigo').value = button.getAttribute('data-codigo') || '';
            });
        }
    </script>
    
    <?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</body>

</html>