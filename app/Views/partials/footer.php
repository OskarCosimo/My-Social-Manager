<?php
// app/Views/partials/footer.php

require_once __DIR__ . '/../../Models/SystemSetting.php';
require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../Models/Plan.php';

// Fetch custom footer scripts and ads
$customFooterScripts = SystemSetting::get('custom_footer_script') ?? '';
$customAdsScript     = SystemSetting::get('custom_ads_script') ?? '';
$adsFreeOnly         = SystemSetting::get('ads_free_only') ?? '0';

// Fetch legal links configuration (Privacy Policy & Terms and Conditions)
$privacyMode = SystemSetting::get('privacy_mode', 'disabled');
$privacyUrl  = trim(SystemSetting::get('privacy_url', ''));
$termsMode   = SystemSetting::get('terms_mode', 'disabled');
$termsUrl    = trim(SystemSetting::get('terms_url', ''));

$privacyHref     = null;
$privacyExternal = false;
if ($privacyMode === 'internal') {
    $privacyHref = '/privacy-policy';
} elseif ($privacyMode === 'external' && filter_var($privacyUrl, FILTER_VALIDATE_URL)) {
    $scheme = strtolower((string)parse_url($privacyUrl, PHP_URL_SCHEME));
    if (in_array($scheme, ['http', 'https'], true)) {
        $privacyHref     = $privacyUrl;
        $privacyExternal = true;
    }
}

$termsHref     = null;
$termsExternal = false;
if ($termsMode === 'internal') {
    $termsHref = '/terms-and-conditions';
} elseif ($termsMode === 'external' && filter_var($termsUrl, FILTER_VALIDATE_URL)) {
    $scheme = strtolower((string)parse_url($termsUrl, PHP_URL_SCHEME));
    if (in_array($scheme, ['http', 'https'], true)) {
        $termsHref     = $termsUrl;
        $termsExternal = true;
    }
}

$showAds = true;

if ($adsFreeOnly === '1') {
    if (isset($_SESSION['user_id'])) {
        $userPlan = Plan::getByUserId((int)$_SESSION['user_id']);
        
        if ($userPlan && isset($userPlan['price']) && (float)$userPlan['price'] > 0) {
            $showAds = false;
        }
    }
}
?>

    <footer class="border-top py-3 mt-5 bg-body-tertiary text-body-secondary small">
        <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div>
                &copy; <?= date('Y') ?> <?= htmlspecialchars(__('app_title') ?: 'My Social Manager') ?>
            </div>
            <?php if ($privacyHref !== null || $termsHref !== null): ?>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <?php if ($privacyHref !== null): ?>
                        <a href="<?= htmlspecialchars($privacyHref) ?>"<?= $privacyExternal ? ' target="_blank" rel="noopener noreferrer"' : '' ?> class="text-decoration-none text-body-secondary">
                            <?= htmlspecialchars(__('privacy_policy') ?: 'Privacy Policy') ?>
                            <?php if ($privacyExternal): ?>
                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.75rem;"></i>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($termsHref !== null): ?>
                        <a href="<?= htmlspecialchars($termsHref) ?>"<?= $termsExternal ? ' target="_blank" rel="noopener noreferrer"' : '' ?> class="text-decoration-none text-body-secondary">
                            <?= htmlspecialchars(__('terms_and_conditions') ?: 'Terms and Conditions') ?>
                            <?php if ($termsExternal): ?>
                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.75rem;"></i>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery dependency for DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- DataTables Core and Bootstrap 5 Integration JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize all tables sharing the 'dt-table' class globally with multi-language support
        $(document).ready(function() {
            if ($('.dt-table').length && !$.fn.DataTable.isDataTable('.dt-table')) {
                $('.dt-table').DataTable({
                    pageLength: 50,
                    responsive: true,
                    stateSave: true,
                    language: {
                        search: "<?= addslashes(__('dt_search') ?: 'Search:') ?>",
                        searchPlaceholder: "<?= addslashes(__('dt_search_placeholder') ?: 'Search elements...') ?>",
                        lengthMenu: "<?= addslashes(__('dt_length_menu') ?: 'Show _MENU_ entries') ?>",
                        info: "<?= addslashes(__('dt_info') ?: 'Showing _START_ to _END_ of _TOTAL_ entries') ?>",
                        infoEmpty: "<?= addslashes(__('dt_info_empty') ?: 'Showing 0 to 0 of 0 entries') ?>",
                        infoFiltered: "<?= addslashes(__('dt_info_filtered') ?: '(filtered from _MAX_ total entries)') ?>",
                        zeroRecords: "<?= addslashes(__('dt_zero_records') ?: 'No matching records found') ?>",
                        emptyTable: "<?= addslashes(__('dt_empty_table') ?: 'No data available in table') ?>",
                        paginate: {
                            first: "<?= addslashes(__('dt_first') ?: 'First') ?>",
                            previous: "<?= addslashes(__('dt_previous') ?: 'Previous') ?>",
                            next: "<?= addslashes(__('dt_next') ?: 'Next') ?>",
                            last: "<?= addslashes(__('dt_last') ?: 'Last') ?>"
                        }
                    }
                });
            }
        });
    </script>

    <?= $customFooterScripts ?>

    <?php if ($showAds && !empty($customAdsScript)): ?>
        <!-- Custom Advertisement Code -->
        <?= $customAdsScript ?>
    <?php endif; ?>
</body>
</html>
