<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';

if (!puedeModificar()) {
    header('Location: ../dashboard.php');
    exit;
}

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Importar CSV - Almacén</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php require_once __DIR__ . '/../config/app.php'; ?>
    <?php include __DIR__ . '/../includes/pwa-head.php'; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Importar productos desde CSV</h3>
            <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>

        <?php if ($msg === 'ok'): ?>
            <div class="alert alert-success">
                Archivo importado correctamente.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                Ocurrió un error al importar el archivo.
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">

                <p>El archivo CSV debe tener estas columnas:</p>

                <pre class="bg-light p-3 border">codigo_producto,codigo_alterno,numero_caja,ubicacion,packing_list,tipo,descripcion</pre>

                <form action="../actions/importar_csv.php" method="POST" enctype="multipart/form-data">

                    <div class="mb-3">
                        <label class="form-label">Archivo CSV</label>
                        <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Importar archivo
                    </button>

                </form>

            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <h5>Ejemplo de archivo</h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th>codigo_producto</th>
                                <th>codigo_alterno</th>
                                <th>numero_caja</th>
                                <th>ubicacion</th>
                                <th>packing_list</th>
                                <th>tipo</th>
                                <th>descripcion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>575.1012</td>
                                <td></td>
                                <td></td>
                                <td>A1</td>
                                <td></td>
                                <td>Suelto</td>
                                <td>Producto suelto</td>
                            </tr>
                            <tr>
                                <td>40036</td>
                                <td>CUM3917728</td>
                                <td>7312</td>
                                <td>A2</td>
                                <td>PL-7312</td>
                                <td>En caja</td>
                                <td>Producto en caja</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-muted mb-0">
                    En Excel usa “Guardar como” &gt; CSV UTF-8.
                </p>
            </div>
        </div>

    </div>

    <?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</body>

</html>