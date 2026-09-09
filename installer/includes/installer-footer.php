<?php
/**
 * Installer Footer Layout
 * Procurement Management CMS — Installer Phase 01
 */

require_once __DIR__ . '/installer-functions.php';
?>
            </div>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="installer-footer">
    <div class="container">
        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
            <div>
                &copy; <?= date('Y') ?> <strong>Procurement Management CMS</strong>. All rights reserved.
            </div>
            <div class="text-sm-end">
                <span class="text-muted">Enterprise Marketplace Edition &bull; Version 1.0.0</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="<?= installer_asset_url('js/bootstrap.bundle.min.js') ?>"></script>

<!-- Installer Custom JS -->
<script src="<?= installer_asset_url('js/installer.js', true) ?>"></script>

</body>
</html>
