<?php
// app/Views/partials/footer.php

require_once __DIR__ . '/../../Models/SystemSetting.php';
require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../Models/Plan.php';

// Fetch custom footer scripts and ads
$customFooterScripts = SystemSetting::get('custom_footer_script') ?? '';
$customAdsScript     = SystemSetting::get('custom_ads_script') ?? '';
$adsFreeOnly         = SystemSetting::get('ads_free_only') ?? '0';

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