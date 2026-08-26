<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/../models/doc-models.php';

$doc_id = (int)($_GET['id'] ?? 0);
$doc = doc_get_document($doc_id);

if (!$doc) {
    die("Document not found.");
}

if (!doc_can_user_view_document($doc)) {
    die("Access Denied: You do not have permission to view this document.");
}

// Record document access in audit log
doc_audit_log('DOCUMENT_ACCESS', 'document', $doc_id, 'SUCCESS', ['document_number' => $doc['document_number']]);

// POST Action handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_document') {
            doc_update_document($doc_id, [
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'classification' => $_POST['classification'],
                'department' => $_POST['department'],
                'content' => $_POST['content'],
                'status' => $_POST['status']
            ], $_POST['change_reason'] ?? 'Updated content', isset($_POST['bump_major']));

            set_flash_message('success', 'Document updated and new version recorded.');
            redirect(url_for('doc_manager_document_detail') . '&id=' . $doc_id);
        } elseif ($action === 'initiate_workflow') {
            $type = doc_get_type($doc['document_type_id']);
            $steps = json_decode($type['workflow_steps'] ?? '[]', true);
            if (empty($steps)) {
                $steps = ['Author', 'Reviewer', 'Approver'];
            }
            doc_initiate_approval_workflow($doc_id, $steps);
            set_flash_message('success', 'Approval workflow initiated.');
            redirect(url_for('doc_manager_document_detail') . '&id=' . $doc_id);
        } elseif ($action === 'approval_decision') {
            doc_record_approval_decision($doc_id, $_POST['step_id'], $_POST['decision'], $_POST['comments'] ?? '');
            set_flash_message('success', 'Approval decision recorded.');
            redirect(url_for('doc_manager_document_detail') . '&id=' . $doc_id);
        } elseif ($action === 'add_comment') {
            doc_add_comment($doc_id, $_POST['comment_text'], $_POST['comment_type'] ?? 'General');
            set_flash_message('success', 'Comment added.');
            redirect(url_for('doc_manager_document_detail') . '&id=' . $doc_id);
        } elseif ($action === 'add_relationship') {
            $target_num = trim($_POST['target_document_number']);
            $target_doc = doc_get_document_by_number($target_num);
            if ($target_doc) {
                doc_add_relationship($doc_id, $target_doc['id'], $_POST['relationship_type']);
                set_flash_message('success', 'Document relationship added.');
            } else {
                set_flash_message('danger', 'Target document number not found.');
            }
            redirect(url_for('doc_manager_document_detail') . '&id=' . $doc_id);
        } elseif ($action === 'attempt_delete') {
            doc_attempt_delete_document($doc_id);
            set_flash_message('warning', 'Document marked for pending disposition (No hard delete allowed).');
            redirect(url_for('doc_manager_documents'));
        }
    } catch (Exception $e) {
        set_flash_message('danger', 'Error: ' . $e->getMessage());
    }
}

$versions = doc_get_versions($doc_id);
$approvals = doc_get_approvals($doc_id);
$comments = doc_get_comments($doc_id);
$relationships = doc_get_relationships($doc_id);
?>

<div class="container-fluid py-4">
    <!-- Header Banner -->
    <div class="card shadow-sm mb-4 border-start border-5 border-<?= $doc['classification'] === 'Restricted' ? 'danger' : ($doc['classification'] === 'Confidential' ? 'warning' : 'primary') ?>">
        <div class="card-body py-3 d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-dark mb-1">Doc # <?= htmlspecialchars($doc['document_number']) ?></span>
                <span class="badge bg-secondary mb-1">Type: <?= htmlspecialchars($doc['document_type_name']) ?></span>
                <span class="badge bg-<?= $doc['classification'] === 'Restricted' ? 'danger' : 'info text-dark' ?> mb-1">
                    <?= htmlspecialchars($doc['classification']) ?>
                </span>
                <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($doc['title']) ?></h3>
                <small class="text-muted">Current Version: <code>v<?= htmlspecialchars($doc['current_version']) ?></code> | Status: <strong><?= htmlspecialchars($doc['status']) ?></strong></small>
            </div>
            <div>
                <a href="<?= url_for('doc_manager_pdf') ?>&id=<?= $doc['id'] ?>" target="_blank" class="btn btn-outline-danger me-2">
                    <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
                </a>
                <a href="<?= url_for('doc_manager_documents') ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
    </div>

    <?php if ($doc['is_under_legal_hold']): ?>
        <div class="alert alert-danger shadow-sm mb-4">
            <i class="fa-solid fa-lock me-2 fs-5"></i> <strong>NOTICE: THIS RECORD IS UNDER ACTIVE LEGAL HOLD.</strong>
            Deletion is blocked, retention expiry is suspended, and all alterations are strictly versioned & logged.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Document Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Document Content & Metadata</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_document">

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Title</label>
                                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($doc['title']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Classification</label>
                                <select name="classification" class="form-select">
                                    <option value="Public" <?= $doc['classification'] === 'Public' ? 'selected' : '' ?>>Public</option>
                                    <option value="Internal" <?= $doc['classification'] === 'Internal' ? 'selected' : '' ?>>Internal</option>
                                    <option value="Confidential" <?= $doc['classification'] === 'Confidential' ? 'selected' : '' ?>>Confidential</option>
                                    <option value="Restricted" <?= $doc['classification'] === 'Restricted' ? 'selected' : '' ?>>Restricted</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="Draft" <?= $doc['status'] === 'Draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="In Progress" <?= $doc['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Submitted" <?= $doc['status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                    <option value="Under Review" <?= $doc['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                                    <option value="Approved" <?= $doc['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="Published" <?= $doc['status'] === 'Published' ? 'selected' : '' ?>>Published</option>
                                    <option value="Archived" <?= $doc['status'] === 'Archived' ? 'selected' : '' ?>>Archived</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Department</label>
                                <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($doc['department'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Summary / Description</label>
                                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($doc['description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Body Content</label>
                                <textarea name="content" class="form-control" rows="10"><?= htmlspecialchars($doc['content'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Reason for Modification</label>
                                <input type="text" name="change_reason" class="form-control" placeholder="Describe changes made..." required>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="bump_major" id="bumpMajor">
                                    <label class="form-check-label fw-semibold" for="bumpMajor">Bump Major Version (e.g. 1.0)</label>
                                </div>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save New Version</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Approval Workflow Panel -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-check me-2 text-success"></i>Approval Workflow</h5>
                    <?php if (empty($approvals)): ?>
                        <form method="POST" action="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="initiate_workflow">
                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-play me-1"></i> Start Workflow</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($approvals)): ?>
                        <p class="text-muted mb-0">No active approval workflow steps initialized yet.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($approvals as $app): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div>
                                        <span class="badge bg-secondary me-2">Step <?= $app['step_number'] ?></span>
                                        <strong class="text-dark"><?= htmlspecialchars($app['step_name']) ?></strong>
                                        <?php if (!empty($app['approver_name'])): ?>
                                            <span class="small text-muted ms-2">(Decided by <?= htmlspecialchars($app['approver_name']) ?>)</span>
                                        <?php endif; ?>
                                        <?php if (!empty($app['comments'])): ?>
                                            <p class="small text-muted mb-0 mt-1"><em>"<?= htmlspecialchars($app['comments']) ?>"</em></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-<?= $app['status'] === 'Approved' ? 'success' : ($app['status'] === 'Pending' ? 'warning text-dark' : 'secondary') ?> me-3">
                                            <?= htmlspecialchars($app['status']) ?>
                                        </span>

                                        <?php if ($app['status'] === 'Pending'): ?>
                                            <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#approveModal<?= $app['id'] ?>">
                                                Approve / Reject
                                            </button>

                                            <!-- Approval Decision Modal -->
                                            <div class="modal fade" id="approveModal<?= $app['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content text-start">
                                                        <form method="POST" action="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="approval_decision">
                                                            <input type="hidden" name="step_id" value="<?= $app['id'] ?>">
                                                            <div class="modal-header bg-success text-white">
                                                                <h5 class="modal-title fw-bold">Approval Step: <?= htmlspecialchars($app['step_name']) ?></h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <label class="form-label fw-semibold">Decision</label>
                                                                <select name="decision" class="form-select mb-3" required>
                                                                    <option value="Approve">Approve</option>
                                                                    <option value="Request Changes">Request Changes</option>
                                                                    <option value="Reject">Reject</option>
                                                                </select>
                                                                <label class="form-label fw-semibold">Comments / Reason</label>
                                                                <textarea name="comments" class="form-control" rows="3" placeholder="Enter notes..."></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success">Submit Decision</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comments & Discussion -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-comments me-2 text-info"></i>Comments & Collaboration</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted">No comments posted yet.</p>
                        <?php else: ?>
                            <?php foreach ($comments as $c): ?>
                                <div class="border-start border-3 border-info ps-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong class="text-dark"><?= htmlspecialchars($c['author_name']) ?></strong>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($c['comment_type']) ?></span>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($c['timestamp']) ?></small>
                                    <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($c['comment_text'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_comment">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <select name="comment_type" class="form-select">
                                    <option value="General">General</option>
                                    <option value="Reviewer note">Reviewer note</option>
                                    <option value="Approval note">Approval note</option>
                                    <option value="Internal restricted note">Internal restricted note</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <input type="text" name="comment_text" class="form-control" placeholder="Write a comment..." required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-info text-white w-100"><i class="fa-solid fa-paper-plane me-1"></i> Post</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Version History & Actions -->
        <div class="col-lg-4">
            <!-- Delete / Disposal Policy Card -->
            <div class="card shadow-sm mb-4 border-danger">
                <div class="card-body text-center">
                    <h6 class="fw-bold text-danger"><i class="fa-solid fa-shield-cat me-1"></i> Retention & Hard Delete Policy</h6>
                    <p class="small text-muted mb-3">Direct hard deletion of records is blocked across the framework. Attempts trigger disposition audit logs.</p>
                    <form method="POST" action="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>" onsubmit="return confirm('Attempt document deletion? (Will mark for disposition log)');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="attempt_delete">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="fa-solid fa-trash me-1"></i> Request Delete / Disposition</button>
                    </form>
                </div>
            </div>

            <!-- Version History Audit List -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Immutable Version History</h5>
                </div>
                <div class="list-group list-group-flush small">
                    <?php foreach ($versions as $v): ?>
                        <div class="list-group-item py-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-primary">v<?= htmlspecialchars($v['version']) ?></strong>
                                <span class="text-muted"><?= date('M d, Y H:i', strtotime($v['timestamp'])) ?></span>
                            </div>
                            <div><strong>User:</strong> <?= htmlspecialchars($v['user_name'] ?: 'System') ?></div>
                            <div><strong>Reason:</strong> <?= htmlspecialchars($v['change_reason'] ?: 'N/A') ?></div>
                            <?php if (!empty($v['fields_changed'])): ?>
                                <div class="text-muted mt-1"><em>Changed: <?= htmlspecialchars($v['fields_changed']) ?></em></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Related Documents -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-link me-2 text-secondary"></i>Related Records</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addRelModal">
                        <i class="fa-solid fa-plus"></i> Link
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($relationships)): ?>
                        <p class="small text-muted mb-0">No document relationships linked.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach ($relationships as $rel): ?>
                                <li class="mb-2">
                                    <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($rel['relationship_type']) ?></span>
                                    <a href="<?= url_for('doc_manager_document_detail') ?>&id=<?= $rel['target_document_id'] ?>">
                                        <?= htmlspecialchars($rel['document_number']) ?> - <?= htmlspecialchars($rel['title']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Relationship Modal -->
<div class="modal fade" id="addRelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-start">
            <form method="POST" action="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_relationship">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Link Related Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Document Number</label>
                        <input type="text" name="target_document_number" class="form-control" placeholder="e.g. RFO-2026-000001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Relationship Type</label>
                        <select name="relationship_type" class="form-select">
                            <option value="Related to">Related to</option>
                            <option value="Supersedes">Supersedes</option>
                            <option value="Superseded by">Superseded by</option>
                            <option value="Caused by">Caused by</option>
                            <option value="Resulted in">Resulted in</option>
                            <option value="Follow-up to">Follow-up to</option>
                            <option value="Parent">Parent</option>
                            <option value="Child">Child</option>
                            <option value="Associated legal request">Associated legal request</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Link Record</button>
                </div>
            </form>
        </div>
    </div>
</div>
