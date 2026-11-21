        </main>
        <footer class="app-footer">
            &copy; <?= date('Y') ?> <?= e(company_name()) ?> · Map data &copy; OpenStreetMap contributors
        </footer>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastStack" style="z-index:2000"></div>

<script>
    window.APP = {
        base: <?= json_encode(BASE_URL) ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        currency: <?= json_encode(setting('currency_symbol', '₹')) ?>,
        mapDefault: {
            lat: <?= (float)setting('map_default_lat', '20.5937') ?>,
            lng: <?= (float)setting('map_default_lng', '78.9629') ?>,
            zoom: <?= (int)setting('map_default_zoom', '5') ?>
        }
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($useMap)): ?>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="<?= asset('js/maps.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($useCharts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/password-toggle.js') ?>"></script>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $__script): ?>
<script src="<?= asset($__script) ?>"></script>
<?php endforeach; ?>
<?= $inlineScript ?? '' ?>
</body>
</html>
