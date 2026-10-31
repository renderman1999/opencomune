<div id="ocDashboardQrModal" class="oc-dashboard-qr-modal" hidden>
    <div class="oc-dashboard-qr-modal__backdrop" data-close-qr-modal></div>
    <div class="oc-dashboard-qr-modal__panel" role="dialog" aria-modal="true" aria-labelledby="ocDashboardQrTitle">
        <div class="oc-dashboard-qr-modal__header">
            <h3 id="ocDashboardQrTitle"><?php esc_html_e('QR Code esperienza', 'opencomune'); ?></h3>
            <button type="button" class="oc-dashboard-qr-modal__close" data-close-qr-modal aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <p class="oc-dashboard-qr-modal__hint"><?php esc_html_e('Scansiona per aprire la pagina pubblica', 'opencomune'); ?></p>
        <div id="ocDashboardQrCanvasWrap" class="oc-dashboard-qr-modal__canvas-wrap"></div>
        <p id="ocDashboardQrUrl" class="oc-dashboard-qr-modal__url"></p>
        <div class="oc-dashboard-qr-modal__footer">
            <button type="button" id="ocDashboardQrCopyBtn" class="oc-dashboard-btn oc-dashboard-btn--secondary">
                <?php esc_html_e('Copia link', 'opencomune'); ?>
            </button>
            <button type="button" class="oc-dashboard-btn oc-dashboard-btn--primary" data-close-qr-modal>
                <?php esc_html_e('Chiudi', 'opencomune'); ?>
            </button>
        </div>
    </div>
</div>
