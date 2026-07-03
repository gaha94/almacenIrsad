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
            pl.codigo LIKE :buscar1
            OR pl.nombre_archivo LIKE :buscar2
            OR pl.estado LIKE :buscar3
            OR pl.descripcion LIKE :buscar4
    ";

    $params[':buscar1'] = '%' . $buscar . '%';
    $params[':buscar2'] = '%' . $buscar . '%';
    $params[':buscar3'] = '%' . $buscar . '%';
    $params[':buscar4'] = '%' . $buscar . '%';
}

$sql = "
    SELECT 
        pl.id,
        pl.codigo,
        pl.nombre_archivo,
        pl.ruta_archivo,
        pl.descripcion,
        pl.estado,
        pl.created_at,
        pl.updated_at,
        u.nombre AS creado_por_nombre,
        COUNT(DISTINCT c.id) AS total_cajas,
        COUNT(DISTINCT p.id) AS total_productos
    FROM packing_lists pl
    LEFT JOIN usuarios u ON u.id = pl.creado_por
    LEFT JOIN cajas c ON c.packing_list_id = pl.id
    LEFT JOIN productos p ON p.packing_list_id = pl.id
    $where
    GROUP BY 
        pl.id,
        pl.codigo,
        pl.nombre_archivo,
        pl.ruta_archivo,
        pl.descripcion,
        pl.estado,
        pl.created_at,
        pl.updated_at,
        u.nombre
    ORDER BY pl.created_at DESC
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$packingLists = $stmt->fetchAll();

$estados = ['Cargado', 'Pendiente', 'Revisar', 'Inactivo'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Packing Lists - Almacén</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php require_once __DIR__ . '/../config/app.php'; ?>
    <?php include __DIR__ . '/../includes/pwa-head.php'; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Packing Lists</h3>
        <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

    <?php if ($msg === 'creado'): ?>
        <div class="alert alert-success">Packing list creado correctamente.</div>
    <?php elseif ($msg === 'actualizado'): ?>
        <div class="alert alert-success">Packing list actualizado correctamente.</div>
    <?php endif; ?>

    <?php if ($error === 'duplicado'): ?>
        <div class="alert alert-danger">Ya existe un packing list con ese código.</div>
    <?php elseif ($error === 'faltan_datos'): ?>
        <div class="alert alert-danger">Debes ingresar el código del packing list.</div>
    <?php elseif ($error === 'archivo_invalido'): ?>
        <div class="alert alert-danger">Solo se permite subir archivos PDF.</div>
    <?php elseif ($error === 'archivo_pesado'): ?>
        <div class="alert alert-danger">El archivo es demasiado pesado. Máximo permitido: 20 MB.</div>
    <?php elseif ($error === 'upload_error'): ?>
        <div class="alert alert-danger">No se pudo subir el archivo.</div>
    <?php elseif ($error === 'general'): ?>
        <div class="alert alert-danger">Ocurrió un error al guardar el packing list.</div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            Crear nuevo packing list
        </div>

        <div class="card-body">
            <form action="../actions/subir_packinglist.php" method="POST" enctype="multipart/form-data" class="row g-3">

                <div class="col-md-3">
                    <label class="form-label">Código Packing List</label>
                    <input 
                        type="text" 
                        name="codigo" 
                        class="form-control" 
                        placeholder="Ejemplo: PL-7312"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= htmlspecialchars($estado) ?>" <?= $estado === 'Cargado' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Archivo PDF</label>
                    <input 
                        type="file" 
                        name="archivo_pdf" 
                        class="form-control" 
                        accept="application/pdf,.pdf"
                    >
                    <small class="text-muted">
                        Puedes crearlo sin PDF y subirlo después.
                    </small>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Descripción</label>
                    <textarea 
                        name="descripcion" 
                        class="form-control" 
                        rows="2"
                        placeholder="Opcional"
                    ></textarea>
                </div>

                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        Guardar packing list
                    </button>
                </div>

            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Buscar packing list
        </div>

        <div class="card-body">
            <form method="GET" action="packing-lists.php" class="row g-2">
                <div class="col-md-10">
                    <input 
                        type="text" 
                        name="buscar" 
                        class="form-control"
                        placeholder="Buscar por código, archivo, estado o descripción"
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
            Lista de packing lists
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Código</th>
                        <th>Archivo</th>
                        <th>Estado</th>
                        <th>Cajas asociadas</th>
                        <th>Productos asociados</th>
                        <th>Creado por</th>
                        <th>Descripción</th>
                        <th>Actualizado</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($packingLists as $pl): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($pl['codigo']) ?></strong>
                            </td>

                            <td>
                                <?php if (!empty($pl['ruta_archivo'])): ?>
                                    <a 
                                        href="../<?= htmlspecialchars($pl['ruta_archivo']) ?>" 
                                        target="_blank" 
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Ver PDF
                                    </a>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($pl['nombre_archivo']) ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">Sin archivo</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php
                                $classEstado = 'secondary';

                                if ($pl['estado'] === 'Cargado') $classEstado = 'success';
                                if ($pl['estado'] === 'Pendiente') $classEstado = 'warning text-dark';
                                if ($pl['estado'] === 'Revisar') $classEstado = 'info text-dark';
                                if ($pl['estado'] === 'Inactivo') $classEstado = 'danger';
                                ?>

                                <span class="badge bg-<?= $classEstado ?>">
                                    <?= htmlspecialchars($pl['estado']) ?>
                                </span>
                            </td>

                            <td><?= htmlspecialchars($pl['total_cajas']) ?></td>
                            <td><?= htmlspecialchars($pl['total_productos']) ?></td>

                            <td><?= htmlspecialchars($pl['creado_por_nombre'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($pl['descripcion'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($pl['updated_at']) ?></td>

                            <td>
                                <button 
                                    type="button"
                                    class="btn btn-sm btn-outline-dark"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editarPackingModal"
                                    data-id="<?= htmlspecialchars($pl['id']) ?>"
                                    data-codigo="<?= htmlspecialchars($pl['codigo']) ?>"
                                    data-estado="<?= htmlspecialchars($pl['estado']) ?>"
                                    data-descripcion="<?= htmlspecialchars($pl['descripcion'] ?? '') ?>"
                                >
                                    Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($packingLists) === 0): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                No hay packing lists registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="text-muted mb-0">
                Se muestran como máximo 500 packing lists.
            </p>
        </div>
    </div>

</div>

<!-- Modal Editar Packing List -->
<div class="modal fade" id="editarPackingModal" tabindex="-1" aria-labelledby="editarPackingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="../actions/subir_packinglist.php" enctype="multipart/form-data" class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="editarPackingModalLabel">Editar packing list</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" name="id" id="edit_id">

                <div class="mb-3">
                    <label class="form-label">Código Packing List</label>
                    <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" id="edit_estado" class="form-select">
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= htmlspecialchars($estado) ?>">
                                <?= htmlspecialchars($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nuevo archivo PDF</label>
                    <input 
                        type="file" 
                        name="archivo_pdf" 
                        class="form-control" 
                        accept="application/pdf,.pdf"
                    >
                    <small class="text-muted">
                        Deja este campo vacío si no deseas cambiar el PDF actual.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3"></textarea>
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
const editarPackingModal = document.getElementById('editarPackingModal');

editarPackingModal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;

    document.getElementById('edit_id').value = button.getAttribute('data-id');
    document.getElementById('edit_codigo').value = button.getAttribute('data-codigo');
    document.getElementById('edit_estado').value = button.getAttribute('data-estado');
    document.getElementById('edit_descripcion').value = button.getAttribute('data-descripcion');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</body>

</html>