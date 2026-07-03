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
            c.numero_caja LIKE :buscar1
            OR u.codigo LIKE :buscar2
            OR c.estado LIKE :buscar3
            OR pl.codigo LIKE :buscar4
    ";

    $params[':buscar1'] = '%' . $buscar . '%';
    $params[':buscar2'] = '%' . $buscar . '%';
    $params[':buscar3'] = '%' . $buscar . '%';
    $params[':buscar4'] = '%' . $buscar . '%';
}

$stmtUbicaciones = $pdo->query("
    SELECT id, codigo, estado
    FROM ubicaciones
    WHERE estado <> 'Inactivo'
    ORDER BY estante ASC, CAST(posicion AS UNSIGNED) ASC, codigo ASC
");
$ubicaciones = $stmtUbicaciones->fetchAll();

$stmtPackingLists = $pdo->query("
    SELECT id, codigo, estado
    FROM packing_lists
    WHERE estado <> 'Inactivo'
    ORDER BY codigo ASC
");
$packingLists = $stmtPackingLists->fetchAll();

$stmtCajasDestino = $pdo->query("
    SELECT id, numero_caja
    FROM cajas
    WHERE estado IN ('Activa', 'Revisar')
    ORDER BY numero_caja ASC
");
$cajasDestino = $stmtCajasDestino->fetchAll();

$sql = "
    SELECT 
        c.id,
        c.numero_caja,
        c.ubicacion_id,
        c.packing_list_id,
        c.estado,
        c.caja_destino_id,
        c.observacion,
        c.created_at,
        c.updated_at,

        u.codigo AS ubicacion_codigo,
        pl.codigo AS packing_codigo,
        cd.numero_caja AS caja_destino_numero,

        COUNT(p.id) AS total_productos
    FROM cajas c
    LEFT JOIN ubicaciones u ON u.id = c.ubicacion_id
    LEFT JOIN packing_lists pl ON pl.id = c.packing_list_id
    LEFT JOIN cajas cd ON cd.id = c.caja_destino_id
    LEFT JOIN productos p 
        ON p.caja_id = c.id 
        AND p.estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
    $where
    GROUP BY
        c.id,
        c.numero_caja,
        c.ubicacion_id,
        c.packing_list_id,
        c.estado,
        c.caja_destino_id,
        c.observacion,
        c.created_at,
        c.updated_at,
        u.codigo,
        pl.codigo,
        cd.numero_caja
    ORDER BY 
        FIELD(c.estado, 'Activa', 'Revisar', 'Vacia', 'Consolidada', 'Desechada', 'Inactiva'),
        c.numero_caja ASC
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cajas = $stmt->fetchAll();

$estadosCaja = ['Activa', 'Consolidada', 'Desechada', 'Vacia', 'Revisar', 'Inactiva'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cajas - Almacén</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php require_once __DIR__ . '/../config/app.php'; ?>
    <?php include __DIR__ . '/../includes/pwa-head.php'; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Cajas</h3>
        <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

    <?php if ($msg === 'creada'): ?>
        <div class="alert alert-success">Caja creada correctamente.</div>
    <?php elseif ($msg === 'actualizada'): ?>
        <div class="alert alert-success">Caja actualizada correctamente.</div>
    <?php endif; ?>

    <?php if ($error === 'duplicado'): ?>
        <div class="alert alert-danger">Ya existe una caja con ese número.</div>
    <?php elseif ($error === 'faltan_datos'): ?>
        <div class="alert alert-danger">Debes ingresar el número de caja.</div>
    <?php elseif ($error === 'misma_caja'): ?>
        <div class="alert alert-danger">La caja destino no puede ser la misma caja.</div>
    <?php elseif ($error === 'general'): ?>
        <div class="alert alert-danger">Ocurrió un error al guardar la caja.</div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            Crear nueva caja
        </div>

        <div class="card-body">
            <form action="../actions/guardar_caja.php" method="POST" class="row g-3">

                <div class="col-md-3">
                    <label class="form-label">N° Caja</label>
                    <input 
                        type="text" 
                        name="numero_caja" 
                        class="form-control" 
                        placeholder="Ejemplo: 7312"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">Ubicación</label>
                    <select name="ubicacion_id" class="form-select">
                        <option value="">Sin ubicación</option>
                        <?php foreach ($ubicaciones as $u): ?>
                            <option value="<?= htmlspecialchars($u['id']) ?>">
                                <?= htmlspecialchars($u['codigo']) ?> - <?= htmlspecialchars($u['estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
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

                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach ($estadosCaja as $estado): ?>
                            <option value="<?= htmlspecialchars($estado) ?>" <?= $estado === 'Activa' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                        Crear caja
                    </button>
                </div>

            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Buscar caja
        </div>

        <div class="card-body">
            <form method="GET" action="cajas.php" class="row g-2">
                <div class="col-md-10">
                    <input 
                        type="text" 
                        name="buscar" 
                        class="form-control"
                        placeholder="Buscar por número de caja, ubicación, estado o packing list"
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
            Lista de cajas
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>N° Caja</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                        <th>Productos</th>
                        <th>Packing List</th>
                        <th>Caja destino</th>
                        <th>Observación</th>
                        <th>Actualizado</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($cajas as $caja): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($caja['numero_caja']) ?></strong>
                            </td>

                            <td>
                                <?php if (!empty($caja['ubicacion_codigo'])): ?>
                                    <span class="badge bg-dark">
                                        <?= htmlspecialchars($caja['ubicacion_codigo']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Sin ubicación</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php
                                $classEstado = 'secondary';

                                if ($caja['estado'] === 'Activa') $classEstado = 'success';
                                if ($caja['estado'] === 'Consolidada') $classEstado = 'warning text-dark';
                                if ($caja['estado'] === 'Desechada') $classEstado = 'danger';
                                if ($caja['estado'] === 'Vacia') $classEstado = 'info text-dark';
                                if ($caja['estado'] === 'Revisar') $classEstado = 'dark';
                                if ($caja['estado'] === 'Inactiva') $classEstado = 'secondary';
                                ?>

                                <span class="badge bg-<?= $classEstado ?>">
                                    <?= htmlspecialchars($caja['estado']) ?>
                                </span>
                            </td>

                            <td><?= htmlspecialchars($caja['total_productos']) ?></td>

                            <td>
                                <?= htmlspecialchars($caja['packing_codigo'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($caja['caja_destino_numero'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($caja['observacion'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($caja['updated_at']) ?>
                            </td>

                            <td>
                                <a 
                                    href="buscar.php?q=<?= urlencode($caja['numero_caja']) ?>" 
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Ver
                                </a>

                                <button 
                                    type="button"
                                    class="btn btn-sm btn-outline-dark"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editarCajaModal"
                                    data-id="<?= htmlspecialchars($caja['id']) ?>"
                                    data-numero-caja="<?= htmlspecialchars($caja['numero_caja']) ?>"
                                    data-ubicacion-id="<?= htmlspecialchars($caja['ubicacion_id'] ?? '') ?>"
                                    data-packing-list-id="<?= htmlspecialchars($caja['packing_list_id'] ?? '') ?>"
                                    data-estado="<?= htmlspecialchars($caja['estado']) ?>"
                                    data-caja-destino-id="<?= htmlspecialchars($caja['caja_destino_id'] ?? '') ?>"
                                    data-observacion="<?= htmlspecialchars($caja['observacion'] ?? '') ?>"
                                >
                                    Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($cajas) === 0): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                No hay cajas registradas.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="text-muted mb-0">
                Se muestran como máximo 500 cajas.
            </p>
        </div>
    </div>

</div>

<!-- Modal Editar Caja -->
<div class="modal fade" id="editarCajaModal" tabindex="-1" aria-labelledby="editarCajaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="../actions/guardar_caja.php" class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="editarCajaModalLabel">Editar caja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" name="id" id="edit_id">

                <div class="mb-3">
                    <label class="form-label">N° Caja</label>
                    <input type="text" name="numero_caja" id="edit_numero_caja" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ubicación</label>
                    <select name="ubicacion_id" id="edit_ubicacion_id" class="form-select">
                        <option value="">Sin ubicación</option>
                        <?php foreach ($ubicaciones as $u): ?>
                            <option value="<?= htmlspecialchars($u['id']) ?>">
                                <?= htmlspecialchars($u['codigo']) ?> - <?= htmlspecialchars($u['estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        Si cambias la ubicación de la caja, los productos dentro de la caja también se moverán a esa ubicación.
                    </small>
                </div>

                <div class="mb-3">
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

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" id="edit_estado" class="form-select">
                        <?php foreach ($estadosCaja as $estado): ?>
                            <option value="<?= htmlspecialchars($estado) ?>">
                                <?= htmlspecialchars($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Caja destino</label>
                    <select name="caja_destino_id" id="edit_caja_destino_id" class="form-select">
                        <option value="">Sin caja destino</option>
                        <?php foreach ($cajasDestino as $cd): ?>
                            <option value="<?= htmlspecialchars($cd['id']) ?>">
                                <?= htmlspecialchars($cd['numero_caja']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        Úsalo si esta caja fue consolidada en otra.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Observación</label>
                    <textarea name="observacion" id="edit_observacion" class="form-control" rows="3"></textarea>
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
const editarCajaModal = document.getElementById('editarCajaModal');

editarCajaModal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;

    document.getElementById('edit_id').value = button.getAttribute('data-id');
    document.getElementById('edit_numero_caja').value = button.getAttribute('data-numero-caja');
    document.getElementById('edit_ubicacion_id').value = button.getAttribute('data-ubicacion-id');
    document.getElementById('edit_packing_list_id').value = button.getAttribute('data-packing-list-id');
    document.getElementById('edit_estado').value = button.getAttribute('data-estado');
    document.getElementById('edit_caja_destino_id').value = button.getAttribute('data-caja-destino-id');
    document.getElementById('edit_observacion').value = button.getAttribute('data-observacion');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</body>

</html>