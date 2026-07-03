<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

if (!puedeModificar()) {
    header('Location: ../dashboard.php');
    exit;
}

$stmt = $pdo->query("
    SELECT 
        id,
        codigo,
        estado,
        zona,
        estante,
        posicion
    FROM ubicaciones
    WHERE estado <> 'Inactivo'
    ORDER BY estante ASC, CAST(posicion AS UNSIGNED) ASC, codigo ASC
");

$ubicaciones = $stmt->fetchAll();

$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Detecta automáticamente la ruta base del proyecto.
// Si estás en localhost/almacen-ubicaciones/pages/qrs-ubicaciones.php,
// esto genera /almacen-ubicaciones
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = str_replace('/pages/qrs-ubicaciones.php', '', $scriptName);

$baseUrl = $protocolo . '://' . $host . $basePath;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>QR de ubicaciones - Almacén</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php require_once __DIR__ . '/../config/app.php'; ?>
    <?php include __DIR__ . '/../includes/pwa-head.php'; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .qr-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

        .qr-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            margin-bottom: 20px;
            background: #fff;
        }

        .qr-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .qr-box {
            display: flex;
            justify-content: center;
            margin: 14px 0;
        }

        .qr-url {
            font-size: 11px;
            word-break: break-all;
            color: #666;
        }
    </style>
</head>

<body class="bg-light">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h3>QR de ubicaciones</h3>

            <div>
                <button onclick="window.print()" class="btn btn-primary">
                    Imprimir QR
                </button>

                <a href="../dashboard.php" class="btn btn-outline-secondary">
                    Volver
                </a>
            </div>
        </div>

        <div class="alert alert-info no-print">
            Estos QR abren directamente la página de cada ubicación.
            Puedes imprimirlos y pegarlos físicamente en cada división del almacén.
        </div>

        <div class="row">
            <?php foreach ($ubicaciones as $ubicacion): ?>
                <?php
                $codigo = $ubicacion['codigo'];
                $urlUbicacion = $baseUrl . '/pages/ubicacion.php?codigo=' . urlencode($codigo);
                $qrId = 'qr_' . preg_replace('/[^A-Za-z0-9]/', '_', $codigo);
                ?>

                <div class="col-md-3 col-sm-6 qr-card-container">
                    <div class="qr-card">
                        <div class="qr-title">
                            <?= htmlspecialchars($codigo) ?>
                        </div>

                        <div
                            class="qr-box"
                            id="<?= htmlspecialchars($qrId) ?>"
                            data-url="<?= htmlspecialchars($urlUbicacion) ?>">
                        </div>

                        <div class="mb-2">
                            <span class="badge bg-dark">
                                Ubicación <?= htmlspecialchars($codigo) ?>
                            </span>
                        </div>

                        <div class="qr-url">
                            <?= htmlspecialchars($urlUbicacion) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.qr-box').forEach(function(element) {
                const url = element.dataset.url;

                new QRCode(element, {
                    text: url,
                    width: 220,
                    height: 220,
                    correctLevel: QRCode.CorrectLevel.M
                });
            });
        });
    </script>

<?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</body>

</html>