</main>

<!-- ========================= PIE ========================= -->
<footer class="atilio-footer">
    <div class="container-fluid">
        <span>
            <i class="bi bi-cup-straw"></i>
            <?= e(APP_NOMBRE) ?> &middot; Sistema de bar
        </span>
        <span class="footer-hora">
            <?= e(date('d/m/Y H:i')) ?> &middot; <?= e(date_default_timezone_get()) ?>
        </span>
    </div>
</footer>

<!-- Bootstrap 5 por CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- JavaScript propio de la aplicación -->
<script src="<?= e(url_archivo('assets/js/app.js')) ?>"></script>

<?php if (!empty($scriptsExtra)): ?>
    <?= $scriptsExtra ?>
<?php endif; ?>

</body>
</html>