<div class="modal fade" id="blueskyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/plugins/bluesky/index.php?action=connect" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">🦋 Connect Bluesky</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <strong>Note:</strong> Generate an App Password in Bluesky Settings > Advanced > App Passwords.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bluesky Handle</label>
                        <input type="text" name="handle" class="form-control" placeholder="e.g., yourname.bsky.social" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">App Password</label>
                        <input type="password" name="app_password" class="form-control" placeholder="xxxx-xxxx-xxxx-xxxx" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Connect Account</button>
                </div>
            </form>
        </div>
    </div>
</div>