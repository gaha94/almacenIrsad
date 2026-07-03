<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= BASE_URL ?>/service-worker.js')
            .then(function () {
                console.log('Service Worker registrado');
            })
            .catch(function (error) {
                console.log('Error registrando Service Worker:', error);
            });
    });
}
</script>