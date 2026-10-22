<?php
// app/Views/filemanager/index.php
if (!isset($_SESSION['user_id'])) exit;
?>
<?php require_once __DIR__ . '/../partials/header.php'; ?>

<!-- Main flex wrapper to align sidebar and content side-by-side -->
<div class="d-flex main-wrapper">
    
    <!-- Include the sidebar and set the active menu item -->
    <?php 
    $activeMenu = 'filemanager'; 
    require __DIR__ . '/../partials/sidebar.php'; 
    ?>

    <!-- Main content area taking up the remaining full width -->
    <!-- Note: p-0 is used here so the iframe seamlessly takes the full available workspace -->
    <main class="content-area w-100 p-0">
        <iframe src="/filemanager/standalone" style="width: 100%; height: 100vh; border: none;" title="<?= __('file_manager') ?? 'File Manager' ?>"></iframe>
    </main>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>