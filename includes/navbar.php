<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/permisos.php';

$baseUrl = BASE_URL;

$paginaActual = basename($_SERVER['PHP_SELF']);

function activeLink($archivo)
{
    global $paginaActual;
    return $paginaActual === $archivo ? 'active' : '';
}

function activeGroup($archivos)
{
    global $paginaActual;
    return in_array($paginaActual, $archivos) ? 'active' : '';
}

function url($ruta)
{
    global $baseUrl;
    return htmlspecialchars($baseUrl . $ruta);
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';
$rolUsuario = $_SESSION['rol_nombre'] ?? '';
?>

<style>
    :root {
        --sidebar-width: 280px;
        --sidebar-bg: #111827;
        --sidebar-bg-soft: #1f2937;
        --sidebar-text: #d1d5db;
        --sidebar-muted: #9ca3af;
        --sidebar-active: #2563eb;
        --sidebar-border: rgba(255, 255, 255, 0.08);
    }

    body {
        background-color: #f4f6f9;
    }

    @media (min-width: 992px) {
        body {
            padding-left: var(--sidebar-width);
        }
    }

    @media (max-width: 991.98px) {
        body {
            padding-top: 64px;
        }
    }

    .app-sidebar {
        width: var(--sidebar-width);
        min-height: 100vh;
        background: linear-gradient(180deg, #111827 0%, #0f172a 100%);
        color: var(--sidebar-text);
        border-right: 1px solid var(--sidebar-border);
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1040;
        display: flex;
        flex-direction: column;
    }

    .app-sidebar-brand {
        height: 72px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #fff;
        text-decoration: none;
        border-bottom: 1px solid var(--sidebar-border);
    }

    .app-sidebar-brand:hover {
        color: #fff;
    }

    .brand-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background-color: var(--sidebar-active);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.35);
    }

    .brand-title {
        font-weight: 800;
        line-height: 1.1;
    }

    .brand-subtitle {
        font-size: 0.78rem;
        color: var(--sidebar-muted);
    }

    .sidebar-section {
        padding: 14px 14px 4px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--sidebar-muted);
        font-weight: 700;
    }

    .sidebar-menu {
        padding: 10px 12px;
        overflow-y: auto;
        flex: 1;
    }

    .sidebar-link {
        color: var(--sidebar-text);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 13px;
        border-radius: 13px;
        margin-bottom: 4px;
        transition: all 0.18s ease-in-out;
        font-weight: 500;
    }

    .sidebar-link:hover {
        color: #fff;
        background-color: rgba(255, 255, 255, 0.08);
    }

    .sidebar-link.active {
        color: #fff;
        background-color: var(--sidebar-active);
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
    }

    .sidebar-link i {
        width: 22px;
        font-size: 1.05rem;
        text-align: center;
    }

    .sidebar-user {
        border-top: 1px solid var(--sidebar-border);
        padding: 16px;
        background-color: rgba(255, 255, 255, 0.03);
    }

    .user-box {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .user-avatar-sidebar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background-color: var(--sidebar-bg-soft);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        flex-shrink: 0;
    }

    .user-name-sidebar {
        color: #fff;
        font-weight: 700;
        font-size: 0.95rem;
        line-height: 1.2;
    }

    .user-role-sidebar {
        color: var(--sidebar-muted);
        font-size: 0.78rem;
    }

    .mobile-topbar {
        height: 64px;
        background-color: #111827;
        color: #fff;
        border-bottom: 1px solid var(--sidebar-border);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1050;
        display: none;
    }

    @media (max-width: 991.98px) {
        .desktop-sidebar {
            display: none;
        }

        .mobile-topbar {
            display: flex;
        }
    }

    .offcanvas-sidebar {
        background: linear-gradient(180deg, #111827 0%, #0f172a 100%);
        color: var(--sidebar-text);
        width: var(--sidebar-width) !important;
    }

    .offcanvas-sidebar .offcanvas-header {
        border-bottom: 1px solid var(--sidebar-border);
    }

    .btn-sidebar-toggle {
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .btn-sidebar-toggle:hover {
        background-color: rgba(255, 255, 255, 0.08);
        color: #fff;
    }
</style>

<!-- Topbar solo para móvil -->
<div class="mobile-topbar align-items-center justify-content-between px-3">
    <?php if (esOperario()): ?>
        <a class="d-flex align-items-center gap-2 text-white text-decoration-none fw-bold" href="<?= url('/pages/buscar.php') ?>">
            <i class="bi bi-box-seam fs-4"></i>
            <span>Almacén</span>
        </a>
    <?php else: ?>
        <a class="d-flex align-items-center gap-2 text-white text-decoration-none fw-bold" href="<?= url('/dashboard.php') ?>">
            <i class="bi bi-box-seam fs-4"></i>
            <span>Almacén</span>
        </a>
    <?php endif; ?>

    <button 
        class="btn btn-sidebar-toggle btn-sm"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#mobileSidebar"
        aria-controls="mobileSidebar"
        aria-label="Abrir menú">
        <i class="bi bi-list fs-4"></i>
    </button>
</div>

<!-- Sidebar escritorio -->
<aside class="app-sidebar desktop-sidebar">

    <?php if (esOperario()): ?>
        <a class="app-sidebar-brand" href="<?= url('/pages/buscar.php') ?>">
            <div class="brand-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <div class="brand-title">Almacén</div>
                <div class="brand-subtitle">Sistema de ubicaciones</div>
            </div>
        </a>
    <?php else: ?>
        <a class="app-sidebar-brand" href="<?= url('/dashboard.php') ?>">
            <div class="brand-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <div class="brand-title">Almacén</div>
                <div class="brand-subtitle">Sistema de ubicaciones</div>
            </div>
        </a>
    <?php endif; ?>

    <div class="sidebar-menu">

        <div class="sidebar-section">Principal</div>

        <?php if (!esOperario()): ?>
            <a class="sidebar-link <?= activeLink('dashboard.php') ?>" href="<?= url('/dashboard.php') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        <?php endif; ?>

        <a class="sidebar-link <?= activeLink('buscar.php') ?>" href="<?= url('/pages/buscar.php') ?>">
            <i class="bi bi-search"></i>
            <span>Buscar</span>
        </a>

        <a class="sidebar-link <?= activeLink('alertas.php') ?>" href="<?= url('/pages/alertas.php') ?>">
            <i class="bi bi-exclamation-triangle"></i>
            <span>Alertas</span>
        </a>

        <?php if (puedeModificar()): ?>

            <div class="sidebar-section">Gestión</div>

            <a class="sidebar-link <?= activeLink('productos.php') ?>" href="<?= url('/pages/productos.php') ?>">
                <i class="bi bi-box"></i>
                <span>Productos</span>
            </a>

            <a class="sidebar-link <?= activeLink('cajas.php') ?>" href="<?= url('/pages/cajas.php') ?>">
                <i class="bi bi-archive"></i>
                <span>Cajas</span>
            </a>

            <a class="sidebar-link <?= activeGroup(['ubicaciones.php', 'ubicacion.php']) ?>" href="<?= url('/pages/ubicaciones.php') ?>">
                <i class="bi bi-geo-alt"></i>
                <span>Ubicaciones</span>
            </a>

            <a class="sidebar-link <?= activeLink('consolidar-cajas.php') ?>" href="<?= url('/pages/consolidar-cajas.php') ?>">
                <i class="bi bi-box-arrow-in-down"></i>
                <span>Consolidar cajas</span>
            </a>

            <div class="sidebar-section">Herramientas</div>

            <a class="sidebar-link <?= activeLink('qrs-ubicaciones.php') ?>" href="<?= url('/pages/qrs-ubicaciones.php') ?>">
                <i class="bi bi-qr-code"></i>
                <span>QR Ubicaciones</span>
            </a>

            <a class="sidebar-link <?= activeLink('importar.php') ?>" href="<?= url('/pages/importar.php') ?>">
                <i class="bi bi-file-earmark-arrow-up"></i>
                <span>Importar CSV</span>
            </a>

            <a class="sidebar-link <?= activeLink('movimientos.php') ?>" href="<?= url('/pages/movimientos.php') ?>">
                <i class="bi bi-clock-history"></i>
                <span>Historial</span>
            </a>

        <?php endif; ?>

        <?php if (esAdmin()): ?>
            <div class="sidebar-section">Administración</div>

            <a class="sidebar-link <?= activeLink('usuarios.php') ?>" href="<?= url('/pages/usuarios.php') ?>">
                <i class="bi bi-people"></i>
                <span>Usuarios</span>
            </a>
        <?php endif; ?>

    </div>

    <div class="sidebar-user">
        <div class="user-box">
            <div class="user-avatar-sidebar">
                <?= htmlspecialchars(strtoupper(mb_substr($nombreUsuario, 0, 1))) ?>
            </div>

            <div class="overflow-hidden">
                <div class="user-name-sidebar text-truncate">
                    <?= htmlspecialchars($nombreUsuario) ?>
                </div>

                <?php if (!empty($rolUsuario)): ?>
                    <div class="user-role-sidebar text-truncate">
                        <?= htmlspecialchars($rolUsuario) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <a href="<?= url('/logout.php') ?>" class="btn btn-outline-light btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-right"></i>
            Salir
        </a>
    </div>

</aside>

<!-- Sidebar móvil offcanvas -->
<div class="offcanvas offcanvas-start offcanvas-sidebar" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <h5 class="offcanvas-title text-white mb-0" id="mobileSidebarLabel">Almacén</h5>
                <small class="text-white-50">Sistema de ubicaciones</small>
            </div>
        </div>

        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <div class="offcanvas-body p-0 d-flex flex-column">

        <div class="sidebar-menu">

            <div class="sidebar-section">Principal</div>

            <?php if (!esOperario()): ?>
                <a class="sidebar-link <?= activeLink('dashboard.php') ?>" href="<?= url('/dashboard.php') ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            <?php endif; ?>

            <a class="sidebar-link <?= activeLink('buscar.php') ?>" href="<?= url('/pages/buscar.php') ?>">
                <i class="bi bi-search"></i>
                <span>Buscar</span>
            </a>

            <a class="sidebar-link <?= activeLink('alertas.php') ?>" href="<?= url('/pages/alertas.php') ?>">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Alertas</span>
            </a>

            <?php if (puedeModificar()): ?>

                <div class="sidebar-section">Gestión</div>

                <a class="sidebar-link <?= activeLink('productos.php') ?>" href="<?= url('/pages/productos.php') ?>">
                    <i class="bi bi-box"></i>
                    <span>Productos</span>
                </a>

                <a class="sidebar-link <?= activeLink('cajas.php') ?>" href="<?= url('/pages/cajas.php') ?>">
                    <i class="bi bi-archive"></i>
                    <span>Cajas</span>
                </a>

                <a class="sidebar-link <?= activeGroup(['ubicaciones.php', 'ubicacion.php']) ?>" href="<?= url('/pages/ubicaciones.php') ?>">
                    <i class="bi bi-geo-alt"></i>
                    <span>Ubicaciones</span>
                </a>

                <a class="sidebar-link <?= activeLink('consolidar-cajas.php') ?>" href="<?= url('/pages/consolidar-cajas.php') ?>">
                    <i class="bi bi-box-arrow-in-down"></i>
                    <span>Consolidar cajas</span>
                
                </a>
                <a class="sidebar-link <?= activeLink('packing-lists.php') ?>" href="<?= url('/pages/packing-lists.php') ?>">
                    <i class="bi bi-card-checklist"></i>
                    <span>Packing Lists</span>
                </a>

                <div class="sidebar-section">Herramientas</div>

                <a class="sidebar-link <?= activeLink('qrs-ubicaciones.php') ?>" href="<?= url('/pages/qrs-ubicaciones.php') ?>">
                    <i class="bi bi-qr-code"></i>
                    <span>QR Ubicaciones</span>
                </a>

                <a class="sidebar-link <?= activeLink('importar.php') ?>" href="<?= url('/pages/importar.php') ?>">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                    <span>Importar CSV</span>
                </a>

                <a class="sidebar-link <?= activeLink('movimientos.php') ?>" href="<?= url('/pages/movimientos.php') ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Historial</span>
                </a>

            <?php endif; ?>

            <?php if (esAdmin()): ?>
                <div class="sidebar-section">Administración</div>

                <a class="sidebar-link <?= activeLink('usuarios.php') ?>" href="<?= url('/pages/usuarios.php') ?>">
                    <i class="bi bi-people"></i>
                    <span>Usuarios</span>
                </a>
            <?php endif; ?>

        </div>

        <div class="sidebar-user mt-auto">
            <div class="user-box">
                <div class="user-avatar-sidebar">
                    <?= htmlspecialchars(strtoupper(mb_substr($nombreUsuario, 0, 1))) ?>
                </div>

                <div class="overflow-hidden">
                    <div class="user-name-sidebar text-truncate">
                        <?= htmlspecialchars($nombreUsuario) ?>
                    </div>

                    <?php if (!empty($rolUsuario)): ?>
                        <div class="user-role-sidebar text-truncate">
                            <?= htmlspecialchars($rolUsuario) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <a href="<?= url('/logout.php') ?>" class="btn btn-outline-light btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-box-arrow-right"></i>
                Salir
            </a>
        </div>

    </div>
</div>