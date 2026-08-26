<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/../models/doc-models.php';

$selected_id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_post_mortem') {
            $pm_type_id = doc_get_type_id_by_code('PM');
            $doc_id = doc_create_document([
                'document_type_id' => $pm_type_id,
                'title' => $_POST['title'],
                'description' => $_POST['executive_summary'] ?? '',
                'classification' => $_POST['classification'] ?? 'Internal',
                'department' => 'Engineering',
                'status' => 'Draft'
            ]);

            doc_save_post_mortem_details($doc_id, $_POST);
            set_flash_message('success', 'Post-Mortem report created.');
            redirect(url_for('doc_manager_post_mortem') . '&id=' . $doc_id);
        } elseif ($action === 'update_post_mortem') {
            $doc_id = (int)$_POST['document_id'];
            doc_update_document($doc_id, [
                'title' => $_POST['title'],
                'description' => $_POST['executive_summary'],
                'classification' => $_POST['classification']
            ], 'Updated Post-Mortem Details', false);

            doc_save_post_mortem_details($doc_id, $_POST);
            set_flash_message('success', 'Post-Mortem details saved.');
            redirect(url_for('doc_manager_post_mortem') . '&id=' . $doc_id);
        } elseif ($action === 'add_action') {
            $doc_id = (int)$_POST['document_id'];
            doc_add_post_mortem_action($doc_id, $_POST);
            set_flash_message('success', 'Action item assigned.');
            redirect(url_for('doc_manager_post_mortem') . '&id=' . $doc_id);
        } elseif ($action === 'update_action_status') {
            doc_update_post_mortem_action($_POST['action_id'], $_POST['status'], $_POST['status'] === 'Completed' ? date('Y-m-d') : null, $_POST['evidence'] ?? null, $_POST['verification_status'] ?? null);
            set_flash_message('success', 'Action status updated.');
            redirect(url_for('doc_manager_post_mortem') . '&id=' . ($_GET['id'] ?? 0));
        }
    } catch (Exception $e) {
        set_flash_message('danger', 'Error: ' . $e->getMessage());
    }
}

$pm_docs = doc_search_documents(['type_id' => doc_get_type_id_by_code('PM')]);
$selected_doc = $selected_id ? doc_get_document($selected_id) : ($pm_docs[0] ?? null);
$pm_details = $selected_doc ? doc_get_post_mortem_details($selected_doc['id']) : null;
$pm_actions = $selected_doc ? doc_get_post_mortem_actions($selected_doc['id']) : [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-microscope me-2 text-info"></i>Structured Post-Mortem Reports</h2>
            <p class="text-muted small mb-0">Technical analysis, response evaluation, lessons learned & independent action tracking.</p>
        </div>
        <div>
            <button type="button" class="btn btn-info text-white fw-bold" data-bs-toggle="modal" data-bs-target="#createPmModal">
                <i class="fa-solid fa-plus me-1"></i> New Post-Mortem Report
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar PM Picker -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-list me-2"></i>Post-Mortem Directory</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($pm_docs)): ?>
                        <div class="p-4 text-center text-muted">No Post-Mortems recorded yet.</div>
                    <?php else: ?>
                        <?php foreach ($pm_docs as $p): ?>
                            <a href="<?= url_for('doc_manager_post_mortem') ?>&id=<?= $p['id'] ?>" class="list-group-item list-group-item-action <?= ($selected_doc && $selected_doc['id'] == $p['id']) ? 'active' : '' ?> py-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="font-monospace"><?= htmlspecialchars($p['document_number']) ?></strong>
                                    <span class="badge bg-<?= $p['status'] === 'Approved' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($p['status']) ?></span>
                                </div>
                                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($p['title']) ?></h6>
                                <small class="text-muted d-block"><?= date('M d, Y', strtotime($p['created_at'])) ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main PM Form & Action Tracker -->
        <div class="col-lg-8">
            <?php if (!$selected_doc): ?>
                <div class="card shadow-sm py-5 text-center text-muted">
                    <i class="fa-solid fa-clipboard-question fs-1 mb-3"></i>
                    <h5>No Post-Mortem Report Selected</h5>
                    <p>Select a Post-Mortem from the list or create a new one.</p>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-primary">Post-Mortem: <code><?= htmlspecialchars($selected_doc['document_number']) ?></code></h5>
                        <a href="<?= url_for('doc_manager_document_detail') ?>&id=<?= $selected_doc['id'] ?>" class="btn btn-sm btn-outline-secondary">View Core Doc</a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= url_for('doc_manager_post_mortem') ?>&id=<?= $selected_doc['id'] ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_post_mortem">
                            <input type="hidden" name="document_id" value="<?= $selected_doc['id'] ?>">

                            <div class="row g-3">
                                <div class="col-md-9">
                                    <label class="form-label fw-semibold">Report Title</label>
                                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($selected_doc['title']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Classification</label>
                                    <select name="classification" class="form-select">
                                        <option value="Internal" <?= $selected_doc['classification'] === 'Internal' ? 'selected' : '' ?>>Internal</option>
                                        <option value="Confidential" <?= $selected_doc['classification'] === 'Confidential' ? 'selected' : '' ?>>Confidential</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Executive Summary</label>
                                    <textarea name="executive_summary" class="form-control" rows="2"><?= htmlspecialchars($pm_details['executive_summary'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Business Impact</label>
                                    <textarea name="business_impact" class="form-control" rows="3"><?= htmlspecialchars($pm_details['business_impact'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Technical Impact</label>
                                    <textarea name="technical_impact" class="form-control" rows="3"><?= htmlspecialchars($pm_details['technical_impact'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Detection Analysis</label>
                                    <textarea name="detection_analysis" class="form-control" rows="3"><?= htmlspecialchars($pm_details['detection_analysis'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Response Analysis</label>
                                    <textarea name="response_analysis" class="form-control" rows="3"><?= htmlspecialchars($pm_details['response_analysis'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Recovery Analysis</label>
                                    <textarea name="recovery_analysis" class="form-control" rows="3"><?= htmlspecialchars($pm_details['recovery_analysis'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">What Went Well</label>
                                    <textarea name="what_went_well" class="form-control" rows="3"><?= htmlspecialchars($pm_details['what_went_well'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">What Did Not Go Well</label>
                                    <textarea name="what_did_not_go_well" class="form-control" rows="3"><?= htmlspecialchars($pm_details['what_did_not_go_well'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-info text-white fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Post-Mortem Report</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Independent Trackable Action Items -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check me-2 text-info"></i>Trackable Corrective & Preventative Actions</h5>
                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#addActionModal">
                            <i class="fa-solid fa-plus me-1"></i> Assign Action
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Action ID</th>
                                        <th>Description</th>
                                        <th>Assigned User/Team</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pm_actions)): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-muted">No corrective actions assigned for this post-mortem.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pm_actions as $act): ?>
                                            <tr>
                                                <td class="fw-bold"><code><?= htmlspecialchars($act['action_identifier']) ?></code></td>
                                                <td><?= htmlspecialchars($act['description']) ?></td>
                                                <td><?= htmlspecialchars($act['assigned_to']) ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($act['priority']) ?></span></td>
                                                <td>
                                                    <span class="badge bg-<?= $act['status'] === 'Completed' ? 'success' : 'warning text-dark' ?>">
                                                        <?= htmlspecialchars($act['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($act['due_date'] ?: 'N/A') ?></td>
                                                <td>
                                                    <form method="POST" action="<?= url_for('doc_manager_post_mortem') ?>&id=<?= $selected_doc['id'] ?>" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="update_action_status">
                                                        <input type="hidden" name="action_id" value="<?= $act['id'] ?>">
                                                        <?php if ($act['status'] !== 'Completed'): ?>
                                                            <input type="hidden" name="status" value="Completed">
                                                            <button type="submit" class="btn btn-sm btn-success py-0 px-2" title="Mark Completed"><i class="fa-solid fa-check"></i> Complete</button>
                                                        <?php else: ?>
                                                            <span class="text-success small fw-bold"><i class="fa-solid fa-circle-check"></i> Done</span>
                                                        <?php endif; ?>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Action Modal -->
<?php if ($selected_doc): ?>
<div class="modal fade" id="addActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_post_mortem') ?>&id=<?= $selected_doc['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_action">
                <input type="hidden" name="document_id" value="<?= $selected_doc['id'] ?>">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold">Assign Corrective Action Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" required placeholder="Detailed action description..."></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Assigned User/Team <span class="text-danger">*</span></label>
                            <input type="text" name="assigned_to" class="form-control" required placeholder="e.g. SysAdmin / NOC Team">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Completion Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white fw-bold">Assign Action</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Create Post-Mortem Modal -->
<div class="modal fade" id="createPmModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_post_mortem') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_post_mortem">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-microscope me-2"></i>Create Post-Mortem Report</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Post-Mortem Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Post-Mortem: Core BGP Peering Outage">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Executive Summary</label>
                            <textarea name="executive_summary" class="form-control" rows="3" placeholder="High-level overview of incident and outcomes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white fw-bold">Create Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
