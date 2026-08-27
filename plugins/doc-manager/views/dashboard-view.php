<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/../models/doc-models.php';

$user_id = (int)($_SESSION['user_id'] ?? 1);
$user_name = current_user()['name'] ?? 'User';

// Search / Filter documents
$my_drafts = doc_search_documents(['status' => 'Draft']);
$my_drafts = array_filter($my_drafts, fn($d) => (int)$d['owner_user_id'] === $user_id);

$all_rfos = doc_search_documents(['type_id' => doc_get_type_id_by_code('RFO')]);
$open_rfos = array_filter($all_rfos, fn($d) => !in_array($d['status'], ['Approved', 'Closed', 'Archived']));

$all_pms = doc_search_documents(['type_id' => doc_get_type_id_by_code('PM')]);
$outstanding_pms = array_filter($all_pms, fn($d) => !in_array($d['status'], ['Approved', 'Closed', 'Archived']));

$all_actions = doc_get_post_mortem_actions();
$overdue_actions = array_filter($all_actions, function($a) {
    return $a['status'] !== 'Completed' && !empty($a['due_date']) && strtotime($a['due_date']) < time();
});

$can_legal = doc_can_access_lawful_requests();
$lawful_requests = [];
$legal_holds = [];

if ($can_legal) {
    $all_lwos = doc_search_documents(['type_id' => doc_get_type_id_by_code('LWO')]);
    $lawful_requests = array_filter($all_lwos, fn($d) => !in_array($d['status'], ['Completed', 'Closed']));
    $legal_holds = doc_get_all_legal_holds();
}

?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-folder-tree me-2"></i>Document Management Dashboard</h2>
            <p class="text-muted small mb-0">Central governance, incident reports, post-mortems, lawful requests & compliance tracking.</p>
        </div>
        <div>
            <a href="<?= url_for('doc_manager_documents') ?>" class="btn btn-outline-primary"><i class="fa-solid fa-file-lines me-1"></i> View All Documents</a>
            <?php if (has_permission('doc_manager_manage_types') || has_permission('manage_settings')): ?>
                <a href="<?= url_for('doc_manager_admin') ?>" class="btn btn-secondary ms-2"><i class="fa-solid fa-gears me-1"></i> Admin Settings</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary text-white rounded-circle p-3 me-3">
                            <i class="fa-solid fa-file-pen fs-4"></i>
                        </div>
                        <div>
                            <span class="text-muted small d-block">My Draft Documents</span>
                            <h3 class="fw-bold mb-0"><?= count($my_drafts) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card shadow-sm border-start border-4 border-warning h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-warning text-dark rounded-circle p-3 me-3">
                            <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                        </div>
                        <div>
                            <span class="text-muted small d-block">Open RFO Reports</span>
                            <h3 class="fw-bold mb-0"><?= count($open_rfos) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card shadow-sm border-start border-4 border-info h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-info text-white rounded-circle p-3 me-3">
                            <i class="fa-solid fa-clipboard-list fs-4"></i>
                        </div>
                        <div>
                            <span class="text-muted small d-block">Outstanding Post-Mortems</span>
                            <h3 class="fw-bold mb-0"><?= count($outstanding_pms) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card shadow-sm border-start border-4 border-danger h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-danger text-white rounded-circle p-3 me-3">
                            <i class="fa-solid fa-clock-rotate-left fs-4"></i>
                        </div>
                        <div>
                            <span class="text-muted small d-block">Overdue Corrective Actions</span>
                            <h3 class="fw-bold mb-0"><?= count($overdue_actions) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Restrict Lawful Section to Authorized Users -->
    <?php if ($can_legal): ?>
        <div class="alert alert-dark border-start border-5 border-danger shadow-sm mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-danger"><i class="fa-solid fa-shield-halved me-2"></i>Lawful & Regulatory Request Portal (Restricted Access)</h5>
                    <p class="small mb-0">You have active <code>legal_request.access</code> authorization. Sensitive target information is restricted from unauthorized search discovery.</p>
                </div>
                <div>
                    <span class="badge bg-danger fs-6 px-3 py-2 me-2">Active Lawful Requests: <?= count($lawful_requests) ?></span>
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">Active Legal Holds: <?= count($legal_holds) ?></span>
                    <a href="<?= url_for('doc_manager_lawful') ?>" class="btn btn-sm btn-outline-light ms-3"><i class="fa-solid fa-scale-balanced me-1"></i> Open Lawful Module</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: My Work & Actions -->
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-check me-2 text-primary"></i>My Work & Pending Items</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Doc #</th>
                                    <th>Title</th>
                                    <th>Classification</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($my_drafts)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No pending draft documents.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($my_drafts as $doc): ?>
                                        <tr>
                                            <td class="fw-bold"><code><?= htmlspecialchars($doc['document_number']) ?></code></td>
                                            <td><?= htmlspecialchars($doc['title']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($doc['classification']) ?></span></td>
                                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($doc['status']) ?></span></td>
                                            <td><a href="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>" class="btn btn-sm btn-primary">Open</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Overdue Actions -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-list-check me-2"></i>Post-Mortem Action Items</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Action ID</th>
                                    <th>Description</th>
                                    <th>Assigned To</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_actions)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No action items recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($all_actions, 0, 5) as $act): ?>
                                        <tr>
                                            <td class="fw-bold"><code><?= htmlspecialchars($act['action_identifier']) ?></code></td>
                                            <td><?= htmlspecialchars($act['description']) ?></td>
                                            <td><?= htmlspecialchars($act['assigned_to']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $act['priority'] === 'High' || $act['priority'] === 'Critical' ? 'danger' : 'secondary' ?>">
                                                    <?= htmlspecialchars($act['priority']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($act['due_date'] ?: 'N/A') ?></td>
                                            <td><span class="badge bg-<?= $act['status'] === 'Completed' ? 'success' : 'warning text-dark' ?>"><?= htmlspecialchars($act['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Operational Modules -->
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-bolt me-2 text-warning"></i>Quick Launch Modules</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="<?= url_for('doc_manager_rfo') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-circle-exclamation me-2 text-warning"></i>RFO / Incident Reports</h6>
                            <small class="text-muted">Root cause, customer impacts, outage timelines & lessons learned.</small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </a>
                    <a href="<?= url_for('doc_manager_post_mortem') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-microscope me-2 text-info"></i>Post-Mortem Module</h6>
                            <small class="text-muted">Structured technical analysis and trackable corrective actions.</small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </a>
                    <?php if ($can_legal): ?>
                        <a href="<?= url_for('doc_manager_lawful') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 bg-light">
                            <div>
                                <h6 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-gavel me-2"></i>Lawful Work Orders & Warrants</h6>
                                <small class="text-muted">Restricted court orders, chain of custody & file SHA-256 integrity.</small>
                            </div>
                            <i class="fa-solid fa-chevron-right text-muted"></i>
                        </a>
                        <a href="<?= url_for('doc_manager_legal_hold') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 bg-light">
                            <div>
                                <h6 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-lock me-2"></i>Legal Holds Management</h6>
                                <small class="text-muted">Litigation preservation directives and retention freeze controls.</small>
                            </div>
                            <i class="fa-solid fa-chevron-right text-muted"></i>
                        </a>
                    <?php endif; ?>
                    <a href="<?= url_for('doc_manager_retention') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-hourglass-half me-2 text-secondary"></i>Retention & Disposition</h6>
                            <small class="text-muted">Manage expired policies and approve formal destruction certificates.</small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </a>
                    <a href="<?= url_for('doc_manager_reports') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-chart-column me-2 text-success"></i>Analytics & Compliance Reports</h6>
                            <small class="text-muted">Incident metrics, mean time to resolution & review approval stats.</small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
