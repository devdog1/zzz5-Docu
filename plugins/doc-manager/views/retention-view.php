<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/../models/doc-models.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'approve_destruction') {
            doc_approve_destruction((int)$_POST['document_id'], $_POST['notes'] ?? '');
            set_flash_message('success', 'Destruction certificate issued and record destroyed under retention policy.');
            redirect(url_for('doc_manager_retention'));
        }
    } catch (Exception $e) {
        set_flash_message('danger', 'Error: ' . $e->getMessage());
    }
}

$pending_records = doc_get_pending_disposition_records();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-hourglass-half me-2"></i>Retention & Disposition Management</h2>
            <p class="text-muted small mb-0">Records lifecycle control, pending disposition queues, and permanent destruction certificates.</p>
        </div>
    </div>

    <div class="card shadow-sm border-warning">
        <div class="card-header bg-warning text-dark py-3">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Records Pending Disposition Review</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Doc #</th>
                            <th>Title</th>
                            <th>Classification</th>
                            <th>Retention Expiry</th>
                            <th>Legal Hold Status</th>
                            <th>Disposition Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_records)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No records pending disposition at this time.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_records as $rec): ?>
                                <tr>
                                    <td><code class="fw-bold"><?= htmlspecialchars($rec['document_number']) ?></code></td>
                                    <td><?= htmlspecialchars($rec['title']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($rec['classification']) ?></span></td>
                                    <td><?= htmlspecialchars($rec['retention_expiry_date'] ?: 'N/A') ?></td>
                                    <td>
                                        <?php if ($rec['is_under_legal_hold']): ?>
                                            <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i> Under Legal Hold</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Clear</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($rec['disposition_status']) ?></span></td>
                                    <td>
                                        <?php if ($rec['is_under_legal_hold']): ?>
                                            <button class="btn btn-sm btn-secondary" disabled title="Destruction suspended during Legal Hold">Suspended</button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#destroyModal<?= $rec['id'] ?>">
                                                Approve Destruction
                                            </button>

                                            <!-- Destroy Modal -->
                                            <div class="modal fade" id="destroyModal<?= $rec['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content text-start">
                                                        <form method="POST" action="<?= url_for('doc_manager_retention') ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="approve_destruction">
                                                            <input type="hidden" name="document_id" value="<?= $rec['id'] ?>">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title fw-bold">Approve Records Destruction</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="text-danger fw-bold">WARNING: This action issues a permanent Destruction Certificate for <code><?= htmlspecialchars($rec['document_number']) ?></code>.</p>
                                                                <label class="form-label fw-semibold">Destruction Certificate Notes</label>
                                                                <textarea name="notes" class="form-control" rows="3" required placeholder="Authorized records disposition notes..."></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Issue Certificate & Destroy</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
