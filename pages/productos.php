<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

if (!puedeModificar()) {
    header('Location: ../pages/buscar.php');
    exit;
}

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');

$where = "";
$params = [];

if ($buscar !== '') {
    $where = "
        WHERE 
            p.codigo_producto LIKE :buscar1
            OR p.codigo_alterno LIKE :buscar2
            OR p.descripcion LIKE :buscar3
            OR c.numero_caja LIKE :buscar4
            OR u.codigo LIKE :buscar5
            OR p.estado LIKE :buscar6
    ";

    $params[':buscar1'] = '%' . $buscar . '%';
    $params[':buscar2'] = '%' . $buscar . '%';
    $params[':buscar3'] = '%' . $buscar . '%';
    $params[':buscar4'] = '%' . $buscar . '%';
    $params[':buscar5'] = '%' . $buscar . '%';
    $params[':buscar6'] = '%' . $buscar . '%';
}

$stmtUbicaciones = $pdo->query("
    SELECT id, codigo, estado
    FROM ubicaciones
    WHERE estado <> 'Inactivo'
    ORDER BY estante ASC, CAST(posicion AS UNSIGNED) ASC, codigo ASC
");
$ubicaciones = $stmtUbicaciones->fetchAll();

$stmtCajas = $pdo->query("
    SELECT 
        c.id,
        c.numero_caja,
        c.estado,
        u.codigo AS ubicacion_codigo
    FROM cajas c
    LEFT JOIN ubicaciones u ON u.id = c.ubicacion_id
    WHERE c.estado IN ('Activa', 'Revisar')
    ORDER BY c.numero_caja ASC
");
$cajas = $stmtCajas->fetchAll();

$stmtPackingLists = $pdo->query("
    SELECT id, codigo, estado
    FROM packing_lists
    WHERE estado <> 'Inactivo'
    ORDER BY codigo ASC
");
$packingLists = $stmtPackingLists->fetchAll();

$sql = "
    SELECT
        p.id,
        p.codigo_producto,
        p.codigo_alterno,
        p.descripcion,
        p.tipo,
        p.caja_id,
        p.ubicacion_id,
        p.packing_list_id,
        p.estado,
        p.observacion,
        p.created_at,
        p.updated_at,

        c.numero_caja,
        c.estado AS estado_caja,

        u.codigo AS ubicacion_codigo,

        pl.codigo AS packing_codigo
    FROM productos p
    LEFT JOIN cajas c ON c.id = p.caja_id
    LEFT JOIN ubicaciones u ON u.id = p.ubicacion_id
    LEFT JOIN packing_lists pl ON pl.id = p.packing_list_id
    $where
    ORDER BY 
        FIELD(p.estado, 'Disponible', 'Alerta pendiente', 'Revisar', 'No encontrado', 'Reubicado', 'Retirado', 'Inactivo'),
        p.codigo_producto ASC
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$estadosProducto = [
    'Disponible',
    'Alerta pendiente',
    'Retirado',
    'Reubicado',
    'No encontrado',
    'Revisar',
    'Inactivo'
];

$tiposProducto = ['Suelto', 'En caja'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Productos - Almacén</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php require_once __DIR__ . '/../config/app.php'; ?>
    <?php include __DIR__ . '/../includes/pwa-head.php'; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Productos</h3>
        <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

    <?php if ($msg === 'creado'): ?>
        <div class="alert alert-success">Producto creado correctamente.</div>
    <?php elseif ($msg === 'actualizado'): ?>
        <div class="alert alert-success">Producto actualizado correctamente.</div>
    <?php endif; ?>

    <?php if ($error === 'faltan_datos'): ?>
        <div class="alert alert-danger">Debes ingresar el código del producto.</div>
    <?php elseif ($error === 'tipo_invalido'): ?>
        <div class="alert alert-danger">Tipo de producto inválido.</div>
    <?php elseif ($error === 'caja_requerida'): ?>
        <div class="alert alert-danger">Para productos en caja debes seleccionar una caja.</div>
    <?php elseif ($error === 'ubicacion_requerida'): ?>
        <div class="alert alert-danger">Para productos sueltos debes seleccionar una ubicación.</div>
    <?php elseif ($error === 'general'): ?>
        <div class="alert alert-danger">Ocurrió un error al guardar el producto.</div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            Crear nuevo producto
        </div>

        <div class="card-body">
            <form action="../actions/guardar_producto.php" method="POST" class="row g-3">

                <div class="col-md-3">
                    <label class="form-label">Código producto</label>
                    <input 
                        type="text" 
                        name="codigo_producto" 
                        class="form-control" 
                        placeholder="Ejemplo: 40036"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">Código alterno</label>
                    <input 
                        type="text" 
                        name="codigo_alterno" 
                        class="form-control" 
                        placeholder="Ejemplo: CUM3917728"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select" id="crear_tipo" onchange="toggleCamposCrear()">
                        <option value="Suelto">Suelto</option>
                        <option value="En caja">En caja</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach ($estadosProducto as $estado): ?>
                            <option value="<?= htmlspecialchars($estado) ?>" <?= $estado === 'Disponible' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4" id="crear_caja_group">
                    <label class="form-label">Caja</label>
                    <select name="caja_id" class="form-select" id="crear_caja_id">
                        <option value="">Sin caja</option>
                        <?php foreach ($cajas as $caja): ?>
                            <option value="<?= htmlspecialchars($caja['id']) ?>">
                                <?= htmlspecialchars($caja['numero_caja']) ?>
                                —
                                <?= htmlspecialchars($caja['ubicacion_codigo'] ?? 'Sin ubicación') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4" id="crear_ubicacion_group">
                    <label class="form-label">Ubicación</label>
                    <select name="ubicacion_id" class="form-select" id="crear_ubicacion_id">
                        <option value="">Sin ubicación</option>
                        <?php foreach ($ubicaciones as $u): ?>
                            <option value="<?= htmlspecialchars($u['id']) ?>">
                                <?= htmlspecialchars($u['codigo']) ?> - <?= htmlspecialchars($u['estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Packing List</label>
                    <select name="packing_list_id" class="form-select">
                        <option value="">Sin packing list</option>
                        <?php foreach ($packingLists as $pl): ?>
                            <option value="<?= htmlspecialchars($pl['id']) ?>">
                                <?= htmlspecialchars($pl['codigo']) ?> - <?= htmlspecialchars($pl['estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Descripción</label>
                    <input 
                        type="text" 
                        name="descripcion" 
                        class="form-control" 
                        placeholder="Opcional"
                    >
                </div>

                <div class="col-md-12">
                    <label class="form-label">Observación</label>
                    <textarea 
                        name="observacion" 
                        class="form-control" 
                        rows="2"
                        placeholder="Opcional"
                    ></textarea>
                </div>

                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        Crear producto
                    </button>
                </div>

            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Buscar producto
        </div>

        <div class="card-body">
            <form method="GET" action="productos.php" class="row g-2">
                <div class="col-md-10">
                    <input 
                        type="text" 
                        name="buscar" 
                        class="form-control"
                        placeholder="Buscar por código, código alterno, caja, ubicación, estado..."
                        value="<?= htmlspecialchars($buscar) ?>"
                    >
                </div>

                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" type="submit">
                        Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Lista de productos
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Código</th>
                        <th>Código alterno</th>
                        <th>Tipo</th>
                        <th>Caja</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                        <th>Packing List</th>
                        <th>Descripción</th>
                        <th>Actualizado</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($productos as $p): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p['codigo_producto']) ?></strong>
                            </td>

                            <td><?= htmlspecialchars($p['codigo_alterno'] ?? '-') ?></td>

                            <td>
                                <?php if ($p['tipo'] === 'Suelto'): ?>
                                    <span class="badge bg-info text-dark">Suelto</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">En caja</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($p['numero_caja'] ?? '-') ?>
                                <?php if (!empty($p['estado_caja'])): ?>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($p['estado_caja']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($p['ubicacion_codigo'])): ?>
                                    <span class="badge bg-dark">
                                        <?= htmlspecialchars($p['ubicacion_codigo']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Sin ubicación</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php
                                $classEstado = 'secondary';

                                if ($p['estado'] === 'Disponible') $classEstado = 'success';
                                if ($p['estado'] === 'Alerta pendiente') $classEstado = 'warning text-dark';
                                if ($p['estado'] === 'Retirado') $classEstado = 'danger';
                                if ($p['estado'] === 'Reubicado') $classEstado = 'primary';
                                if ($p['estado'] === 'No encontrado') $classEstado = 'dark';
                                if ($p['estado'] === 'Revisar') $classEstado = 'info text-dark';
                                if ($p['estado'] === 'Inactivo') $classEstado = 'secondary';
                                ?>

                                <span class="badge bg-<?= $classEstado ?>">
                                    <?= htmlspecialchars($p['estado']) ?>
                                </span>
                            </td>

                            <td><?= htmlspecialchars($p['packing_codigo'] ?? '-') ?></td>

                            <td><?= htmlspecialchars($p['descripcion'] ?? '-') ?></td>

                            <td><?= htmlspecialchars($p['updated_at']) ?></td>

                            <td>
                                <a 
                                    href="buscar.php?q=<?= urlencode($p['codigo_producto']) ?>" 
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Ver
                                </a>

                                <button 
                                    type="button"
                                    class="btn btn-sm btn-outline-dark"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editarProductoModal"
                                    data-id="<?= htmlspecialchars($p['id']) ?>"
                                    data-codigo-producto="<?= htmlspecialchars($p['codigo_producto']) ?>"
                                    data-codigo-alterno="<?= htmlspecialchars($p['codigo_alterno'] ?? '') ?>"
                                    data-descripcion="<?= htmlspecialchars($p['descripcion'] ?? '') ?>"
                                    data-tipo="<?= htmlspecialchars($p['tipo']) ?>"
                                    data-caja-id="<?= htmlspecialchars($p['caja_id'] ?? '') ?>"
                                    data-ubicacion-id="<?= htmlspecialchars($p['ubicacion_id'] ?? '') ?>"
                                    data-packing-list-id="<?= htmlspecialchars($p['packing_list_id'] ?? '') ?>"
                                    data-estado="<?= htmlspecialchars($p['estado']) ?>"
                                    data-observacion="<?= htmlspecialchars($p['observacion'] ?? '') ?>"
                                >
                                    Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($productos) === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                No hay productos registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="text-muted mb-0">
                Se muestran como máximo 500 productos.
            </p>
        </div>
    </div>

</div>

<!-- Modal Editar Producto -->
<div class="modal fade" id="editarProductoModal" tabindex="-1" aria-labelledby="editarProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="../actions/guardar_producto.php" class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="editarProductoModalLabel">Editar producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" name="id" id="edit_id">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Código producto</label>
                        <input type="text" name="codigo_producto" id="edit_codigo_producto" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Código alterno</label>
                        <input type="text" name="codigo_alterno" id="edit_codigo_alterno" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" id="edit_tipo" class="form-select" onchange="toggleCamposEditar()">
                            <?php foreach ($tiposProducto as $tipo): ?>
                                <option value="<?= htmlspecialchars($tipo) ?>">
                                    <?= htmlspecialchars($tipo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4" id="edit_caja_group">
                        <label class="form-label">Caja</label>
                        <select name="caja_id" id="edit_caja_id" class="form-select">
                            <option value="">Sin caja</option>
                            <?php foreach ($cajas as $caja): ?>
                                <option value="<?= htmlspecialchars($caja['id']) ?>">
                                    <?= htmlspecialchars($caja['numero_caja']) ?>
                                    —
                                    <?= htmlspecialchars($caja['ubicacion_codigo'] ?? 'Sin ubicación') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4" id="edit_ubicacion_group">
                        <label class="form-label">Ubicación</label>
                        <select name="ubicacion_id" id="edit_ubicacion_id" class="form-select">
                            <option value="">Sin ubicación</option>
                            <?php foreach ($ubicaciones as $u): ?>
                                <option value="<?= htmlspecialchars($u['id']) ?>">
                                    <?= htmlspecialchars($u['codigo']) ?> - <?= htmlspecialchars($u['estado']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Packing List</label>
                        <select name="packing_list_id" id="edit_packing_list_id" class="form-select">
                            <option value="">Sin packing list</option>
                            <?php foreach ($packingLists as $pl): ?>
                                <option value="<?= htmlspecialchars($pl['id']) ?>">
                                    <?= htmlspecialchars($pl['codigo']) ?> - <?= htmlspecialchars($pl['estado']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="estado" id="edit_estado" class="form-select">
                            <?php foreach ($estadosProducto as $estado): ?>
                                <option value="<?= htmlspecialchars($estado) ?>">
                                    <?= htmlspecialchars($estado) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" id="edit_descripcion" class="form-control">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Observación</label>
                        <textarea name="observacion" id="edit_observacion" class="form-control" rows="3"></textarea>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button type="submit" class="btn btn-primary">
                    Guardar cambios
                </button>
            </div>

        </form>
    </div>
</div>

<script>
function toggleCamposCrear() {
    const tipo = document.getElementById('crear_tipo').value;
    const cajaGroup = document.getElementById('crear_caja_group');
    const ubicacionGroup = document.getElementById('crear_ubicacion_group');

    if (tipo === 'En caja') {
        cajaGroup.style.display = 'block';
        ubicacionGroup.style.display = 'block';
    } else {
        cajaGroup.style.display = 'none';
        ubicacionGroup.style.display = 'block';
    }
}

function toggleCamposEditar() {
    const tipo = document.getElementById('edit_tipo').value;
    const cajaGroup = document.getElementById('edit_caja_group');
    const ubicacionGroup = document.getElementById('edit_ubicacion_group');

    if (tipo === 'En caja') {
        cajaGroup.style.display = 'block';
        ubicacionGroup.style.display = 'block';
    } else {
        cajaGroup.style.display = 'none';
        ubicacionGroup.style.display = 'block';
    }
}

const editarProductoModal = document.getElementById('editarProductoModal');

editarProductoModal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;

    document.getElementById('edit_id').value = button.getAttribute('data-id');
    document.getElementById('edit_codigo_producto').value = button.getAttribute('data-codigo-producto');
    document.getElementById('edit_codigo_alterno').value = button.getAttribute('data-codigo-alterno');
    document.getElementById('edit_descripcion').value = button.getAttribute('data-descripcion');
    document.getElementById('edit_tipo').value = button.getAttribute('data-tipo');
    document.getElementById('edit_caja_id').value = button.getAttribute('data-caja-id');
    document.getElementById('edit_ubicacion_id').value = button.getAttribute('data-ubicacion-id');
    document.getElementById('edit_packing_list_id').value = button.getAttribute('data-packing-list-id');
    document.getElementById('edit_estado').value = button.getAttribute('data-estado');
    document.getElementById('edit_observacion').value = button.getAttribute('data-observacion');

    toggleCamposEditar();
});

document.addEventListener('DOMContentLoaded', function () {
    toggleCamposCrear();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</body>

</html>