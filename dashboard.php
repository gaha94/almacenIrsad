<?php
require_once 'includes/auth.php';
require_once 'includes/permisos.php';
require_once 'config/database.php';

if (esOperario()) {
    header('Location: pages/buscar.php');
    exit;
}

$totalProductos = $pdo->query("SELECT COUNT(*) FROM productos WHERE estado = 'Disponible'")->fetchColumn();
$totalAlertas = $pdo->query("SELECT COUNT(*) FROM alertas WHERE estado = 'Pendiente'")->fetchColumn();
$totalCajas = $pdo->query("SELECT COUNT(*) FROM cajas WHERE estado = 'Activa'")->fetchColumn();
$totalUbicacionesLibres = $pdo->query("SELECT COUNT(*) FROM ubicaciones WHERE estado = 'Libre'")->fetchColumn();

$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';
$rolUsuario = $_SESSION['rol_nombre'] ?? 'Sin rol';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - Sistema de Almacén</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php require_once __DIR__ . '/config/app.php'; ?>
    <?php include __DIR__ . '/includes/pwa-head.php'; ?>

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

        .stat-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease-in-out;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .quick-card {
            text-decoration: none;
            color: #212529;
            background-color: #fff;
            border-radius: 18px;
            border: 1px solid #e9ecef;
            padding: 22px;
            height: 100%;
            display: block;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
        }

        .quick-card:hover {
            color: #212529;
            transform: translateY(-4px);
            border-color: #ced4da;
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.09);
        }

        .quick-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
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
    </style>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container py-4">

        <section class="page-header mb-4">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-2">
                        Panel principal
                    </h2>
                    <p class="mb-0 text-white-50">
                        Bienvenido, <?= htmlspecialchars($nombreUsuario) ?>. Gestiona productos, cajas, ubicaciones y alertas desde un solo lugar.
                    </p>
                </div>

                <div class="col-md-4 text-md-end">
                    <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                        <i class="bi bi-person-badge me-1"></i>
                        <?= htmlspecialchars($rolUsuario) ?>
                    </span>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="section-title mb-1">Resumen del almacén</h5>
                    <p class="text-muted-small mb-0">Indicadores generales del sistema.</p>
                </div>
            </div>

            <div class="row g-3">

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <div>
                                <p class="text-muted-small mb-1">Productos disponibles</p>
                                <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalProductos) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-warning-subtle text-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div>
                                <p class="text-muted-small mb-1">Alertas pendientes</p>
                                <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalAlertas) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="bi bi-archive"></i>
                            </div>
                            <div>
                                <p class="text-muted-small mb-1">Cajas activas</p>
                                <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalCajas) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-secondary-subtle text-secondary">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <div>
                                <p class="text-muted-small mb-1">Ubicaciones libres</p>
                                <h3 class="fw-bold mb-0"><?= htmlspecialchars($totalUbicacionesLibres) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <section>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="section-title mb-1">Accesos rápidos</h5>
                    <p class="text-muted-small mb-0">Selecciona una acción para continuar.</p>
                </div>
            </div>

            <div class="row g-3">

                <div class="col-12 col-sm-6 col-lg-4">
                    <a href="pages/buscar.php" class="quick-card">
                        <div class="quick-icon bg-primary-subtle text-primary">
                            <i class="bi bi-search"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Buscar producto o caja</h6>
                        <p class="text-muted-small mb-0">Consulta rápidamente productos, cajas y su ubicación.</p>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-4">
                    <a href="pages/alertas.php" class="quick-card">
                        <div class="quick-icon bg-warning-subtle text-warning">
                            <i class="bi bi-bell"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Alertas</h6>
                        <p class="text-muted-small mb-0">Revisa alertas pendientes del almacén.</p>
                    </a>
                </div>

                <?php if (puedeModificar()): ?>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/productos.php" class="quick-card">
                            <div class="quick-icon bg-info-subtle text-info">
                                <i class="bi bi-box"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Productos</h6>
                            <p class="text-muted-small mb-0">Administra el catálogo de productos.</p>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/cajas.php" class="quick-card">
                            <div class="quick-icon bg-success-subtle text-success">
                                <i class="bi bi-archive"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Cajas</h6>
                            <p class="text-muted-small mb-0">Gestiona cajas activas y su contenido.</p>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/ubicaciones.php" class="quick-card">
                            <div class="quick-icon bg-secondary-subtle text-secondary">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Buscar ubicaciones</h6>
                            <p class="text-muted-small mb-0">Consulta espacios disponibles y asignados.</p>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/movimientos.php" class="quick-card">
                            <div class="quick-icon bg-dark-subtle text-dark">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Historial</h6>
                            <p class="text-muted-small mb-0">Revisa movimientos realizados en el sistema.</p>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/importar.php" class="quick-card">
                            <div class="quick-icon bg-primary-subtle text-primary">
                                <i class="bi bi-file-earmark-arrow-up"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Importar CSV</h6>
                            <p class="text-muted-small mb-0">Carga información masiva desde archivos CSV.</p>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/consolidar-cajas.php" class="quick-card">
                            <div class="quick-icon bg-warning-subtle text-warning">
                                <i class="bi bi-box-arrow-in-down"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Consolidar cajas</h6>
                            <p class="text-muted-small mb-0">Agrupa o reorganiza cajas según necesidad.</p>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/packing-lists.php" class="quick-card">
                            <div class="quick-icon bg-danger-subtle text-danger">
                                <i class="bi bi-file-earmark-pdf-fill"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Packing Lists</h6>
                            <p class="text-muted-small mb-0">Sube los Packing Lists en formato PDF</p>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/qrs-ubicaciones.php" class="quick-card">
                            <div class="quick-icon bg-dark-subtle text-dark">
                                <i class="bi bi-qr-code"></i>
                            </div>
                            <h6 class="fw-bold mb-1">QR Ubicaciones</h6>
                            <p class="text-muted-small mb-0">Genera o consulta códigos QR de ubicaciones.</p>
                        </a>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/ubicacion.php" class="quick-card">
                            <div class="quick-icon bg-secondary-subtle text-secondary">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Modificar ubicaciones</h6>
                            <p class="text-muted-small mb-0">Actualiza datos relacionados a ubicaciones.</p>
                        </a>
                    </div>

                <?php endif; ?>

                <?php if (esAdmin()): ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="pages/usuarios.php" class="quick-card">
                            <div class="quick-icon bg-danger-subtle text-danger">
                                <i class="bi bi-people"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Usuarios</h6>
                            <p class="text-muted-small mb-0">Administra usuarios, roles y permisos.</p>
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </section>

    </main>

    <!-- Bootstrap JS necesario para dropdowns y navbar responsive -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php include __DIR__ . '/includes/pwa-script.php'; ?>

</body>

</html>