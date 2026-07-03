<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

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
    <title>Consolidar cajas - Almacén</title>
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

        .step-card,
        .confirm-card {
            border: none;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.06);
            height: 100%;
        }

        .step-header {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f2;
            background-color: #fff;
        }

        .step-number {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 8px;
        }

        .selected-box {
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #e9ecef;
            background-color: #fff;
        }

        .selected-box-warning {
            border-left: 5px solid #ffc107;
        }

        .selected-box-success {
            border-left: 5px solid #198754;
        }

        .selected-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
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

        .empty-result {
            border: 1px dashed #ced4da;
            border-radius: 16px;
            padding: 22px;
            text-align: center;
            color: #6c757d;
            background-color: #fff;
        }

        .compare-box {
            border: 1px solid #e9ecef;
            border-radius: 16px;
            padding: 18px;
            background-color: #fff;
            height: 100%;
        }

        .compare-arrow {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background-color: #212529;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
            font-size: 22px;
        }

        .text-muted-small {
            font-size: 0.9rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 22px;
            }

            .compare-arrow {
                transform: rotate(90deg);
                margin: 8px auto;
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
                        <i class="bi bi-box-arrow-in-down me-2"></i>
                        Consolidar cajas
                    </h2>
                    <p class="mb-0 text-white-50">
                        Traslada los productos de una caja origen hacia una caja destino sin recargar la página.
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

        <?php if ($msg === 'ok'): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>Caja consolidada correctamente.</div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center gap-2">
                <i class="bi bi-x-circle-fill fs-5"></i>
                <div>No se pudo realizar la consolidación. Verifica las cajas seleccionadas.</div>
            </div>
        <?php endif; ?>

        <div class="alert alert-info border-0 shadow-sm rounded-4 d-flex gap-2 mb-4">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>
                Puedes buscar por <strong>número de caja</strong> o por <strong>ubicación</strong>.
                La búsqueda se hará sin recargar toda la página.
            </div>
        </div>

        <div class="row g-4 mb-4">

            <!-- ORIGEN -->
            <div class="col-lg-6">
                <div class="card step-card">
                    <div class="step-header">
                        <h5 class="fw-bold mb-1">
                            <span class="step-number bg-warning text-dark">1</span>
                            Seleccionar caja origen
                        </h5>
                        <p class="text-muted-small mb-0">
                            Caja desde donde saldrán los productos.
                        </p>
                    </div>

                    <div class="card-body p-3 p-md-4">

                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-7">
                                <label for="input_origen" class="form-label fw-semibold">Buscar origen</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-upc-scan"></i>
                                    </span>
                                    <input
                                        type="text"
                                        id="input_origen"
                                        class="form-control"
                                        placeholder="Ejemplo: A10 o 1493">
                                </div>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <button
                                    type="button"
                                    class="btn btn-outline-dark w-100"
                                    onclick="abrirScanner('input_origen')">
                                    <i class="bi bi-qr-code-scan"></i>
                                </button>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <button
                                    type="button"
                                    class="btn btn-primary w-100"
                                    onclick="buscarCajasAjax('origen')">
                                    <i class="bi bi-search me-1"></i>
                                    Buscar
                                </button>
                            </div>
                        </div>

                        <div id="seleccion_origen"></div>
                        <div id="resultado_origen"></div>

                    </div>
                </div>
            </div>

            <!-- DESTINO -->
            <div class="col-lg-6">
                <div class="card step-card">
                    <div class="step-header">
                        <h5 class="fw-bold mb-1">
                            <span class="step-number bg-success text-white">2</span>
                            Seleccionar caja destino
                        </h5>
                        <p class="text-muted-small mb-0">
                            Caja que recibirá los productos.
                        </p>
                    </div>

                    <div class="card-body p-3 p-md-4">

                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-7">
                                <label for="input_destino" class="form-label fw-semibold">Buscar destino</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-upc-scan"></i>
                                    </span>
                                    <input
                                        type="text"
                                        id="input_destino"
                                        class="form-control"
                                        placeholder="Ejemplo: A9 o 1533">
                                </div>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <button
                                    type="button"
                                    class="btn btn-outline-dark w-100"
                                    onclick="abrirScanner('input_destino')">
                                    <i class="bi bi-qr-code-scan"></i>
                                </button>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <button
                                    type="button"
                                    class="btn btn-primary w-100"
                                    onclick="buscarCajasAjax('destino')">
                                    <i class="bi bi-search me-1"></i>
                                    Buscar
                                </button>
                            </div>
                        </div>

                        <div id="seleccion_destino"></div>
                        <div id="resultado_destino"></div>

                    </div>
                </div>
            </div>

        </div>

        <!-- CONFIRMAR -->
        <section class="card confirm-card mb-4">
            <div class="step-header">
                <h5 class="fw-bold mb-1">
                    <span class="step-number bg-dark text-white">3</span>
                    Confirmar consolidación
                </h5>
                <p class="text-muted-small mb-0">
                    Verifica que la caja origen y destino sean correctas antes de continuar.
                </p>
            </div>

            <div class="card-body p-3 p-md-4">

                <form
                    method="POST"
                    action="../actions/consolidar_caja.php"
                    onsubmit="return validarConsolidacion()">

                    <input type="hidden" name="caja_origen_id" id="caja_origen_id">
                    <input type="hidden" name="caja_destino_id" id="caja_destino_id">

                    <div id="resumen_consolidacion" class="empty-result">
                        <i class="bi bi-ui-checks-grid fs-3 d-block mb-2"></i>
                        Primero selecciona una caja origen y una caja destino.
                    </div>

                    <div class="mb-3 mt-4">
                        <label class="form-label fw-semibold">Observación</label>
                        <textarea
                            name="observacion"
                            class="form-control"
                            rows="3"
                            placeholder="Ejemplo: Se consolida porque la caja origen tiene pocos productos."></textarea>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            value="1"
                            id="liberar_ubicacion"
                            name="liberar_ubicacion"
                            checked>
                        <label class="form-check-label" for="liberar_ubicacion">
                            Liberar ubicación de la caja origen si corresponde
                        </label>
                    </div>

                    <div class="d-flex flex-column flex-md-row gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarSeleccion()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            Limpiar selección
                        </button>

                        <button type="submit" class="btn btn-warning px-4">
                            <i class="bi bi-box-arrow-in-down me-1"></i>
                            Consolidar cajas
                        </button>
                    </div>

                </form>

            </div>
        </section>

    </main>

    <!-- Bootstrap JS necesario para navbar, dropdowns, scanner y modal -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let cajaOrigenSeleccionada = null;
        let cajaDestinoSeleccionada = null;

        document.addEventListener('DOMContentLoaded', function () {
            const inputOrigen = document.getElementById('input_origen');
            const inputDestino = document.getElementById('input_destino');

            if (inputOrigen) {
                inputOrigen.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        buscarCajasAjax('origen');
                    }
                });
            }

            if (inputDestino) {
                inputDestino.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        buscarCajasAjax('destino');
                    }
                });
            }
        });

        async function buscarCajasAjax(tipo) {
            const inputId = tipo === 'origen' ? 'input_origen' : 'input_destino';
            const resultadoId = tipo === 'origen' ? 'resultado_origen' : 'resultado_destino';

            const input = document.getElementById(inputId);
            const contenedor = document.getElementById(resultadoId);
            const q = input.value.trim();

            if (!q) {
                contenedor.innerHTML = `
                    <div class="empty-result">
                        <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                        Ingresa una caja o ubicación para buscar.
                    </div>
                `;
                return;
            }

            contenedor.innerHTML = `
                <div class="empty-result">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Buscando cajas...
                </div>
            `;

            try {
                const url = `../actions/buscar_cajas_consolidar.php?q=${encodeURIComponent(q)}`;

                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const texto = await response.text();
                let result;

                try {
                    result = JSON.parse(texto);
                } catch (errorJson) {
                    contenedor.innerHTML = `
                        <div class="alert alert-danger border-0 rounded-4 mb-0">
                            <strong>La respuesta no es JSON.</strong><br>
                            El archivo PHP puede estar mostrando un warning/error.
                            <br>
                            <small>Respuesta recibida: ${escapeHtml(texto.substring(0, 300))}</small>
                        </div>
                    `;
                    return;
                }

                if (!response.ok || !result.ok) {
                    contenedor.innerHTML = `
                        <div class="alert alert-danger border-0 rounded-4 mb-0">
                            <strong>Error al buscar cajas.</strong><br>
                            ${escapeHtml(result.message || 'Error desconocido.')}
                            ${result.error ? `<br><small>${escapeHtml(result.error)}</small>` : ''}
                        </div>
                    `;
                    return;
                }

                renderResultados(tipo, result.data);

            } catch (error) {
                contenedor.innerHTML = `
                    <div class="alert alert-danger border-0 rounded-4 mb-0">
                        No se pudo conectar con el archivo de búsqueda.
                    </div>
                `;
            }
        }

        function renderResultados(tipo, cajas) {
            const resultadoId = tipo === 'origen' ? 'resultado_origen' : 'resultado_destino';
            const contenedor = document.getElementById(resultadoId);

            if (!cajas.length) {
                contenedor.innerHTML = `
                    <div class="empty-result">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No se encontraron cajas activas.
                    </div>
                `;
                return;
            }

            const filas = cajas.map(caja => `
                <tr>
                    <td>
                        <span class="code-pill">
                            <i class="bi bi-archive"></i>
                            ${escapeHtml(caja.numero_caja)}
                        </span>
                    </td>

                    <td>
                        <span class="code-pill">
                            <i class="bi bi-geo-alt"></i>
                            ${escapeHtml(caja.ubicacion_codigo || 'Sin ubicación')}
                        </span>
                    </td>

                    <td>
                        <span class="badge rounded-pill ${claseEstadoCaja(caja.estado)} px-3 py-2">
                            ${escapeHtml(caja.estado)}
                        </span>
                    </td>

                    <td>
                        <strong>${escapeHtml(caja.total_productos)}</strong>
                    </td>

                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-sm ${tipo === 'origen' ? 'btn-warning' : 'btn-success'}"
                            onclick='seleccionarCaja(${JSON.stringify(tipo)}, ${JSON.stringify(caja)})'>
                            <i class="bi bi-check-circle me-1"></i>
                            Seleccionar
                        </button>
                    </td>
                </tr>
            `).join('');

            contenedor.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-secondary">
                            <tr>
                                <th>Caja</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>Productos</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${filas}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function seleccionarCaja(tipo, caja) {
            if (tipo === 'origen') {
                cajaOrigenSeleccionada = caja;
                document.getElementById('caja_origen_id').value = caja.id;
                document.getElementById('resultado_origen').innerHTML = '';
                renderCajaSeleccionada('seleccion_origen', caja, 'origen');
            } else {
                cajaDestinoSeleccionada = caja;
                document.getElementById('caja_destino_id').value = caja.id;
                document.getElementById('resultado_destino').innerHTML = '';
                renderCajaSeleccionada('seleccion_destino', caja, 'destino');
            }

            actualizarResumenConsolidacion();
        }

        function renderCajaSeleccionada(contenedorId, caja, tipo) {
            const contenedor = document.getElementById(contenedorId);
            const color = tipo === 'origen' ? 'warning' : 'success';
            const icono = tipo === 'origen' ? 'bi-box-arrow-up-right' : 'bi-box-arrow-in-down';
            const titulo = tipo === 'origen' ? 'Caja origen seleccionada' : 'Caja destino seleccionada';

            contenedor.innerHTML = `
                <div class="selected-box selected-box-${color} mb-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="selected-icon bg-${color}-subtle text-${color}">
                            <i class="bi ${icono}"></i>
                        </div>

                        <div class="flex-grow-1">
                            <p class="text-muted-small mb-1">${titulo}</p>

                            <h5 class="fw-bold mb-2">
                                Caja ${escapeHtml(caja.numero_caja)}
                            </h5>

                            <div class="d-flex flex-wrap gap-2">
                                <span class="code-pill">
                                    <i class="bi bi-geo-alt"></i>
                                    ${escapeHtml(caja.ubicacion_codigo || 'Sin ubicación')}
                                </span>

                                <span class="code-pill">
                                    <i class="bi bi-box-seam"></i>
                                    ${escapeHtml(caja.total_productos)} productos
                                </span>

                                <span class="badge rounded-pill ${claseEstadoCaja(caja.estado)} px-3 py-2">
                                    ${escapeHtml(caja.estado)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function actualizarResumenConsolidacion() {
            const resumen = document.getElementById('resumen_consolidacion');

            if (!cajaOrigenSeleccionada || !cajaDestinoSeleccionada) {
                resumen.className = 'empty-result';
                resumen.innerHTML = `
                    <i class="bi bi-ui-checks-grid fs-3 d-block mb-2"></i>
                    Primero selecciona una caja origen y una caja destino.
                `;
                return;
            }

            if (String(cajaOrigenSeleccionada.id) === String(cajaDestinoSeleccionada.id)) {
                resumen.className = 'alert alert-danger border-0 rounded-4 d-flex gap-2';
                resumen.innerHTML = `
                    <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                    <div>La caja origen y la caja destino no pueden ser la misma.</div>
                `;
                return;
            }

            resumen.className = '';
            resumen.innerHTML = `
                <div class="row align-items-stretch g-3 mb-4">
                    <div class="col-md-5">
                        <div class="compare-box">
                            <p class="text-muted-small mb-1">Caja origen</p>
                            <h4 class="fw-bold mb-3">${escapeHtml(cajaOrigenSeleccionada.numero_caja)}</h4>

                            <div class="d-flex flex-wrap gap-2">
                                <span class="code-pill">
                                    <i class="bi bi-geo-alt"></i>
                                    ${escapeHtml(cajaOrigenSeleccionada.ubicacion_codigo || 'Sin ubicación')}
                                </span>

                                <span class="code-pill">
                                    <i class="bi bi-box-seam"></i>
                                    ${escapeHtml(cajaOrigenSeleccionada.total_productos)} productos
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2 d-flex align-items-center justify-content-center">
                        <div class="compare-arrow">
                            <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="compare-box">
                            <p class="text-muted-small mb-1">Caja destino</p>
                            <h4 class="fw-bold mb-3">${escapeHtml(cajaDestinoSeleccionada.numero_caja)}</h4>

                            <div class="d-flex flex-wrap gap-2">
                                <span class="code-pill">
                                    <i class="bi bi-geo-alt"></i>
                                    ${escapeHtml(cajaDestinoSeleccionada.ubicacion_codigo || 'Sin ubicación')}
                                </span>

                                <span class="code-pill">
                                    <i class="bi bi-box-seam"></i>
                                    ${escapeHtml(cajaDestinoSeleccionada.total_productos)} productos
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function validarConsolidacion() {
            if (!cajaOrigenSeleccionada || !cajaDestinoSeleccionada) {
                alert('Primero selecciona una caja origen y una caja destino.');
                return false;
            }

            if (String(cajaOrigenSeleccionada.id) === String(cajaDestinoSeleccionada.id)) {
                alert('La caja origen y la caja destino no pueden ser la misma.');
                return false;
            }

            return confirm('¿Seguro que deseas consolidar estas cajas? Los productos pasarán de la caja origen a la caja destino.');
        }

        function limpiarSeleccion() {
            cajaOrigenSeleccionada = null;
            cajaDestinoSeleccionada = null;

            document.getElementById('caja_origen_id').value = '';
            document.getElementById('caja_destino_id').value = '';

            document.getElementById('input_origen').value = '';
            document.getElementById('input_destino').value = '';

            document.getElementById('seleccion_origen').innerHTML = '';
            document.getElementById('seleccion_destino').innerHTML = '';
            document.getElementById('resultado_origen').innerHTML = '';
            document.getElementById('resultado_destino').innerHTML = '';

            actualizarResumenConsolidacion();
        }

        function claseEstadoCaja(estado) {
            switch (estado) {
                case 'Activa':
                    return 'bg-success';
                case 'Revisar':
                    return 'bg-info text-dark';
                case 'Consolidada':
                    return 'bg-warning text-dark';
                case 'Desechada':
                    return 'bg-danger';
                case 'Vacia':
                    return 'bg-secondary';
                default:
                    return 'bg-secondary';
            }
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }
    </script>

</body>

<?php include __DIR__ . '/../includes/pwa-script.php'; ?>

</html>