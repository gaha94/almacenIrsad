<!-- Modal Scanner QR -->
<div class="modal fade" id="scannerModal" tabindex="-1" aria-labelledby="scannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="scannerModalLabel">Escanear código</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info py-2">
                    Acerca el QR a la cámara, evita reflejos y mantén el código dentro del recuadro.
                </div>

                <div id="reader" style="width: 100%; min-height: 320px;"></div>

                <div class="mt-3">
                    <small class="text-muted">
                        Si no lee el QR, prueba acercar/alejar lentamente el celular. En producción usa HTTPS.
                    </small>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>

<script>
    let html5QrCode = null;
    let inputDestinoScanner = null;
    let scannerIniciado = false;

    function abrirScanner(inputId) {
        inputDestinoScanner = inputId;

        const modalElement = document.getElementById('scannerModal');
        const modal = new bootstrap.Modal(modalElement);
        modal.show();

        modalElement.addEventListener('shown.bs.modal', iniciarScanner, {
            once: true
        });
    }

    function iniciarScanner() {
        const readerId = "reader";

        if (scannerIniciado) {
            return;
        }

        scannerIniciado = true;

        html5QrCode = new Html5Qrcode(readerId, {
            formatsToSupport: [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8
            ],
            verbose: false
        });

        const config = {
            fps: 15,
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                const qrboxSize = Math.floor(minEdge * 0.85);

                return {
                    width: qrboxSize,
                    height: qrboxSize
                };
            },
            aspectRatio: 1.0,
            disableFlip: false,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }
        };

        html5QrCode.start({
                facingMode: "environment"
            },
            config,
            function(decodedText) {
                procesarLecturaScanner(decodedText);
            },
            function(errorMessage) {
                // No mostrar errores constantes de lectura
            }
        ).catch(function(err) {
            scannerIniciado = false;
            alert("No se pudo abrir la cámara. Revisa permisos del navegador o usa HTTPS.");
            console.error(err);
        });
    }

    function procesarLecturaScanner(decodedText) {
        let valor = decodedText.trim();

        // Si el QR contiene una URL tipo:
        // http://localhost/almacen-ubicaciones/pages/ubicacion.php?codigo=A2
        // extraemos solo A2.
        try {
            const url = new URL(valor);
            const codigo = url.searchParams.get("codigo");

            if (codigo) {
                valor = codigo;
            }
        } catch (e) {
            // Si no es URL, usamos el texto tal cual.
        }

        if (inputDestinoScanner) {
            const input = document.getElementById(inputDestinoScanner);

            if (input) {
                input.value = valor;
            }
        }

        cerrarScanner();

        const input = document.getElementById(inputDestinoScanner);
        if (input && input.dataset.autosubmit === "1") {
            input.form.submit();
        }
    }

    function cerrarScanner() {
        const modalElement = document.getElementById('scannerModal');
        const modal = bootstrap.Modal.getInstance(modalElement);

        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                html5QrCode = null;
                scannerIniciado = false;
            }).catch(() => {
                html5QrCode = null;
                scannerIniciado = false;
            });
        } else {
            scannerIniciado = false;
        }

        if (modal) {
            modal.hide();
        }
    }

    document.addEventListener('hidden.bs.modal', function(event) {
        if (event.target.id === 'scannerModal') {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    html5QrCode = null;
                    scannerIniciado = false;
                }).catch(() => {
                    html5QrCode = null;
                    scannerIniciado = false;
                });
            } else {
                scannerIniciado = false;
            }
        }
    });
</script>