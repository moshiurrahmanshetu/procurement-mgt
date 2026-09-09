<?php
/**
 * Master Footer Component
 * Procurement Management CMS
 */
?>
        </main>

        <!-- System Footer -->
        <footer class="app-footer">
            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
                <div>
                    &copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong>. All rights reserved.
                </div>
                <div class="text-muted small">
                    Version <?= e(APP_VERSION) ?> &bull; Enterprise Edition
                </div>
            </div>
        </footer>
    </div>
</div>

<!-- Vendor Scripts -->
<script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>

<!-- App Scripts -->
<script src="<?= asset('js/sidebar.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>

<?php if (!empty($extraJs)): ?>
    <?php if (is_array($extraJs)): ?>
        <?php foreach ($extraJs as $jsUrl): ?>
            <script src="<?= e($jsUrl) ?>"></script>
        <?php endforeach; ?>
    <?php else: ?>
        <script src="<?= e($extraJs) ?>"></script>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
