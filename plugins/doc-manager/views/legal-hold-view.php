<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/../models/doc-models.php';

if (!doc_can_access_lawful_requests()) {
    echo '<div class="container py-5"><div class="alert alert-danger shadow-sm border-start border-5 border-danger">'
       . '<h4 class="fw-bold"><i class="fa-solid fa-lock me-2"></i> RESTRICTED ACCESS MODULE</h4>'
       . '<p class="mb-0">You do not possess the required explicit <code>legal_request.access</code> permission to manage Legal Holds.</p>'
       . '</div></div>';
    return;
}

$all_users = function_exists('get_all_users') ? get_all_users() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_legal_hold') {
            $lh_id = doc_create_legal_hold($_POST);
            if (!empty($_POST['document_number'])) {
                $doc = doc_get_document_by_number(trim($_POST['document_number']));
                if ($doc) {
                    doc_apply_legal_hold_to_document($lh_id, $doc['id']);
                }
            }
            set_flash_message('success', 'Legal Hold directive created successfully.');
            redirect(url_for('doc_manager_legal_hold'));
        } elseif ($action === 'apply_to_doc') {
            $lh_id = (int)$_POST['legal_hold_id'];
            $doc_num = trim($_POST['document_number']);
            $doc = doc_get_document_by_number($doc_num);
            if ($doc) {
                doc_apply_legal_hold_to_document($lh_id, $doc['id']);
                set_flash_message('success', "Legal hold applied to document {$doc_num}.");
            } else {
                set_flash_message('danger', "Document number {$doc_num} not found.");
            }
            redirect(url_for('doc_manager_legal_hold'));
        } elseif ($action === 'release_hold') {
            $lh_id = (int)$_POST['legal_hold_id'];
            doc_release_legal_hold($lh_id, $_POST['release_authorization'] ?? '');
            set_flash_message('warning', 'Legal hold directive released.');
            redirect(url_for('doc_manager_legal_hold'));
        }
    } catch (Exception $e) {
        set_flash_message('danger', 'Error: ' . $e->getMessage());
    }
}

$holds = doc_get_all_legal_holds();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-lock me-2"></i>Legal Holds Directive Module</h2>
            <p class="text-muted small mb-0">Freeze document deletion, suspend retention expiry, and enforce litigation preservation.</p>
        </div>
        <div>
            <button type="button" class="btn btn-danger text-white fw-bold" data-bs-toggle="modal" data-bs-target="#createHoldModal">
                <i class="fa-solid fa-plus me-1"></i> Issue Legal Hold
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-danger">
        <div class="card-header bg-danger text-white py-3">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-shield-cat me-2"></i>Active Legal Hold Directives</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Hold #</th>
                            <th>Directive Name</th>
                            <th>Authority</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>Custodians</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($holds)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No legal hold directives issued.</td></tr>
                        <?php else: ?>
                            <?php foreach ($holds as $h): ?>
                                <tr>
                                    <td><code class="fw-bold text-danger"><?= htmlspecialchars($h['legal_hold_number']) ?></code></td>
                                    <td class="fw-bold"><?= htmlspecialchars($h['name']) ?></td>
                                    <td><?= htmlspecialchars($h['authority']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $h['status'] === 'Active' ? 'danger' : 'secondary' ?>">
                                            <?= htmlspecialchars($h['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($h['start_date']) ?></td>
                                    <td><small><?= htmlspecialchars($h['custodians'] ?: 'All Custodians') ?></small></td>
                                    <td>
                                        <?php if ($h['status'] === 'Active'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger me-1" data-bs-toggle="modal" data-bs-target="#linkDocModal<?= $h['id'] ?>">
                                                Link Doc
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#releaseModal<?= $h['id'] ?>">
                                                Release
                                            </button>

                                            <!-- Link Doc Modal -->
                                            <div class="modal fade" id="linkDocModal<?= $h['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content text-start">
                                                        <form method="POST" action="<?= url_for('doc_manager_legal_hold') ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="apply_to_doc">
                                                            <input type="hidden" name="legal_hold_id" value="<?= $h['id'] ?>">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title fw-bold">Link Document to Hold</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <label class="form-label fw-semibold">Enter Document Number</label>
                                                                <input type="text" name="document_number" class="form-control" placeholder="e.g. RFO-2026-000001" required>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Apply Hold</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Release Hold Modal -->
                                            <div class="modal fade" id="releaseModal<?= $h['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content text-start">
                                                        <form method="POST" action="<?= url_for('doc_manager_legal_hold') ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="release_hold">
                                                            <input type="hidden" name="legal_hold_id" value="<?= $h['id'] ?>">
                                                            <div class="modal-header bg-secondary text-white">
                                                                <h5 class="modal-title fw-bold">Release Legal Hold</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <label class="form-label fw-semibold">Release Authorization Details</label>
                                                                <textarea name="release_authorization" class="form-control" rows="3" required placeholder="Specify court order release or legal counsel approval..."></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Confirm Release</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Released <?= htmlspecialchars($h['release_date']) ?></span>
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

<!-- Create Legal Hold Modal -->
<div class="modal fade" id="createHoldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_legal_hold') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_legal_hold">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-lock me-2"></i>Issue Legal Hold Directive</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Directive Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Litigation Preservation Order #2026-A">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Authority</label>
                            <input type="text" name="authority" class="form-control" placeholder="e.g. General Counsel / Superior Court">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Target Custodian Employee</label>
                            <select name="custodians" class="form-select">
                                <option value="">All Custodians / Broad Scope</option>
                                <?php foreach ($all_users as $u): ?>
                                    <option value="<?= htmlspecialchars(!empty($u['display_name']) ? $u['display_name'] : $u['username']) ?>">
                                        <?= htmlspecialchars(!empty($u['display_name']) ? $u['display_name'] : $u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Related Case / Request #</label>
                            <input type="text" name="related_case_request" class="form-control" placeholder="e.g. CASE-2026-88">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Scope & Rationale</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Define preservation parameters and legal rationale..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Issue Directive</button>
                </div>
            </form>
        </div>
    </div>
</div>
