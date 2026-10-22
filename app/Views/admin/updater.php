ù<!-- app/Views/admin/updater.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Updates - My Social Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">System Updater</h2>
            <p class="text-muted">Manage automatic updates via GitHub Releases</p>
        </div>
        <a href="/admin" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Admin</a>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> System updated successfully and database migrations applied!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 fw-bold">Version Information</div>
                <div class="card-body">
                    <p class="mb-2"><strong>Installed Version:</strong> <span class="badge bg-secondary">v<?= htmlspecialchars($currentVersion) ?></span></p>

                    <?php if ($updateInfo === null): ?>
                        <div class="alert alert-warning small mb-0">Unable to fetch release information from GitHub.</div>
                    <?php elseif ($updateInfo['has_update']): ?>
                        <div class="alert alert-info">
                            <h5 class="fw-bold mb-1">New version v<?= htmlspecialchars($updateInfo['version']) ?> available!</h5>
                            <p class="small text-muted mb-2">Released on: <?= htmlspecialchars($updateInfo['published_at'] ?? 'N/A') ?></p>
                            <div class="p-2 bg-light rounded small mb-3" style="max-height: 150px; overflow-y: auto;">
                                <?= nl2br(htmlspecialchars($updateInfo['release_notes'])) ?>
                            </div>

                            <?php if ($permCheck['is_ready']): ?>
                                <form method="POST" action="/admin/updater/apply" onsubmit="return confirm('Do you want to proceed with downloading and installing this update?');">
                                    <input type="hidden" name="zip_url" value="<?= htmlspecialchars($updateInfo['zip_url']) ?>">
                                    <button type="submit" class="btn btn-success fw-bold w-100">
                                        <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download & Install v<?= htmlspecialchars($updateInfo['version']) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100" disabled>Permissions Error - Fix Permissions First</button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-1"></i> You are running the latest version!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 fw-bold">Write Permissions Check</div>
                <div class="card-body">
                    <?php if ($permCheck['is_ready']): ?>
                        <div class="alert alert-success d-flex align-items-center mb-0">
                            <i class="bi bi-shield-check fs-3 me-3"></i>
                            <div>All critical directories are writable by the webserver user.</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-3">
                            <strong>The following paths are not writable:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($permCheck['failed_paths'] as $path): ?>
                                    <li><?= htmlspecialchars($path) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="bg-dark text-white p-2 rounded small">
                            <code>sudo chown -R www-data:www-data <?= htmlspecialchars(dirname(__DIR__, 2)) ?></code>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>