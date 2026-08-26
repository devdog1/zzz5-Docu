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
        if ($action === 'create_rfo') {
            $rfo_type_id = doc_get_type_id_by_code('RFO');
            $doc_id = doc_create_document([
                'document_type_id' => $rfo_type_id,
                'title' => $_POST['title'],
                'description' => $_POST['impact_description'] ?? '',
                'classification' => $_POST['classification'] ?? 'Internal',
                'department' => $_POST['service_affected'] ?? 'Operations',
                'status' => 'Draft'
            ]);

            doc_save_rfo_details($doc_id, $_POST);
            set_flash_message('success', 'RFO Incident Report created.');
            redirect(url_for('doc_manager_rfo') . '&id=' . $doc_id);
        } elseif ($action === 'update_rfo') {
            $doc_id = (int)$_POST['document_id'];
            doc_update_document($doc_id, [
                'title' => $_POST['title'],
                'description' => $_POST['impact_description'],
                'classification' => $_POST['classification'],
                'status' => $_POST['status']
            ], 'Updated RFO Details', false);

            doc_save_rfo_details($doc_id, $_POST);
            set_flash_message('success', 'RFO Incident Report details saved.');
            redirect(url_for('doc_manager_rfo') . '&id=' . $doc_id);
        } elseif ($action === 'add_timeline') {
            $doc_id = (int)$_POST['document_id'];
            doc_add_rfo_timeline_entry($doc_id, $_POST['timestamp'], $_POST['event'], $_POST['person'] ?? '', $_POST['source'] ?? '', $_POST['notes'] ?? '');
            set_flash_message('success', 'Timeline entry added.');
            redirect(url_for('doc_manager_rfo') . '&id=' . $doc_id);
        }
    } catch (Exception $e) {
        set_flash_message('danger', 'Error: ' . $e->getMessage());
    }
}

$rfo_docs = doc_search_documents(['type_id' => doc_get_type_id_by_code('RFO')]);
$selected_doc = $selected_id ? doc_get_document($selected_id) : ($rfo_docs[0] ?? null);
$rfo_details = $selected_doc ? doc_get_rfo_details($selected_doc['id']) : null;
$timelines = $selected_doc ? doc_get_rfo_timelines($selected_doc['id']) : [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>RFO / Incident Report Module</h2>
            <p class="text-muted small mb-0">Root Cause Analysis, Outage Timelines, Impact Assessment & Remediation.</p>
        </div>
        <div>
            <button type="button" class="btn btn-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#createRfoModal">
                <i class="fa-solid fa-plus me-1"></i> New Incident Report (RFO)
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar RFO Picker -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-list me-2"></i>Incident Reports</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($rfo_docs)): ?>
                        <div class="p-4 text-center text-muted">No RFO reports generated yet.</div>
                    <?php else: ?>
                        <?php foreach ($rfo_docs as $r): ?>
                            <a href="<?= url_for('doc_manager_rfo') ?>&id=<?= $r['id'] ?>" class="list-group-item list-group-item-action <?= ($selected_doc && $selected_doc['id'] == $r['id']) ? 'active' : '' ?> py-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="font-monospace"><?= htmlspecialchars($r['document_number']) ?></strong>
                                    <span class="badge bg-<?= $r['status'] === 'Approved' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($r['status']) ?></span>
                                </div>
                                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($r['title']) ?></h6>
                                <small class="text-muted d-block"><?= date('M d, Y H:i', strtotime($r['created_at'])) ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main RFO Form & Timeline Panel -->
        <div class="col-lg-8">
            <?php if (!$selected_doc): ?>
                <div class="card shadow-sm py-5 text-center text-muted">
                    <i class="fa-solid fa-file-circle-exclamation fs-1 mb-3"></i>
                    <h5>No Incident Report Selected</h5>
                    <p>Select an RFO from the list on the left or create a new report.</p>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-primary">RFO Details: <code><?= htmlspecialchars($selected_doc['document_number']) ?></code></h5>
                        <a href="<?= url_for('doc_manager_document_detail') ?>&id=<?= $selected_doc['id'] ?>" class="btn btn-sm btn-outline-secondary">View Core Doc</a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= url_for('doc_manager_rfo') ?>&id=<?= $selected_doc['id'] ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_rfo">
                            <input type="hidden" name="document_id" value="<?= $selected_doc['id'] ?>">

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Report Title</label>
                                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($selected_doc['title']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Incident Number</label>
                                    <input type="text" name="incident_number" class="form-control" value="<?= htmlspecialchars($rfo_details['incident_number'] ?? '') ?>" placeholder="INC-99182">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Service Affected</label>
                                    <input type="text" name="service_affected" class="form-control" value="<?= htmlspecialchars($rfo_details['service_affected'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Incident Severity</label>
                                    <select name="incident_severity" class="form-select">
                                        <option value="SEV-1" <?= ($rfo_details['incident_severity'] ?? '') === 'SEV-1' ? 'selected' : '' ?>>SEV-1 (Critical / Outage)</option>
                                        <option value="SEV-2" <?= ($rfo_details['incident_severity'] ?? '') === 'SEV-2' ? 'selected' : '' ?>>SEV-2 (Major Impact)</option>
                                        <option value="SEV-3" <?= ($rfo_details['incident_severity'] ?? '') === 'SEV-3' ? 'selected' : '' ?>>SEV-3 (Minor Degradation)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Classification</label>
                                    <select name="classification" class="form-select">
                                        <option value="Internal" <?= $selected_doc['classification'] === 'Internal' ? 'selected' : '' ?>>Internal</option>
                                        <option value="Confidential" <?= $selected_doc['classification'] === 'Confidential' ? 'selected' : '' ?>>Confidential</option>
                                        <option value="Public" <?= $selected_doc['classification'] === 'Public' ? 'selected' : '' ?>>Public</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Start Datetime</label>
                                    <input type="datetime-local" name="start_datetime" class="form-control" value="<?= $rfo_details['start_datetime'] ? date('Y-m-d\TH:i', strtotime($rfo_details['start_datetime'])) : '' ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Detection Datetime</label>
                                    <input type="datetime-local" name="detection_datetime" class="form-control" value="<?= $rfo_details['detection_datetime'] ? date('Y-m-d\TH:i', strtotime($rfo_details['detection_datetime'])) : '' ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Restoration Datetime</label>
                                    <input type="datetime-local" name="service_restoration_datetime" class="form-control" value="<?= $rfo_details['service_restoration_datetime'] ? date('Y-m-d\TH:i', strtotime($rfo_details['service_restoration_datetime'])) : '' ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Total Duration</label>
                                    <input type="text" name="total_duration" class="form-control" value="<?= htmlspecialchars($rfo_details['total_duration'] ?? '') ?>" placeholder="e.g. 1h 45m">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Root Cause Analysis</label>
                                    <textarea name="root_cause" class="form-control" rows="3"><?= htmlspecialchars($rfo_details['root_cause'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Resolution & Recovery Actions</label>
                                    <textarea name="resolution" class="form-control" rows="3"><?= htmlspecialchars($rfo_details['resolution'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Incident Details</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Event Timelines -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-timeline me-2 text-warning"></i>Incident Event Timeline</h5>
                        <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#addTimelineModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Timeline Entry
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($timelines)): ?>
                            <p class="text-muted mb-0">No timeline entries recorded for this incident yet.</p>
                        <?php else: ?>
                            <div class="timeline position-relative ps-4 border-start border-3 border-warning">
                                <?php foreach ($timelines as $tl): ?>
                                    <div class="mb-3 position-relative">
                                        <div class="fw-bold text-dark">
                                            <span class="badge bg-warning text-dark me-2"><?= date('H:i:s', strtotime($tl['timestamp'])) ?></span>
                                            <?= htmlspecialchars($tl['event']) ?>
                                        </div>
                                        <small class="text-muted d-block"><?= date('M d, Y', strtotime($tl['timestamp'])) ?> | Person: <?= htmlspecialchars($tl['person'] ?: 'N/A') ?> | Source: <?= htmlspecialchars($tl['source'] ?: 'N/A') ?></small>
                                        <?php if (!empty($tl['notes'])): ?>
                                            <p class="small text-secondary mb-0 mt-1"><?= nl2br(htmlspecialchars($tl['notes'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Timeline Modal -->
<?php if ($selected_doc): ?>
<div class="modal fade" id="addTimelineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_rfo') ?>&id=<?= $selected_doc['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_timeline">
                <input type="hidden" name="document_id" value="<?= $selected_doc['id'] ?>">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold">Add Timeline Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Timestamp</label>
                        <input type="datetime-local" name="timestamp" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Description</label>
                        <input type="text" name="event" class="form-control" placeholder="e.g. Core router interface flapping detected" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Person / Handler</label>
                            <input type="text" name="person" class="form-control" placeholder="Engineer name">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Source</label>
                            <input type="text" name="source" class="form-control" placeholder="Zabbix, Syslog, Customer">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Add Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Create RFO Modal -->
<div class="modal fade" id="createRfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_rfo') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_rfo">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>New RFO / Incident Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Incident Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Core Switch Stack Power Failure">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Incident Number</label>
                            <input type="text" name="incident_number" class="form-control" placeholder="INC-99012">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Service Affected</label>
                            <input type="text" name="service_affected" class="form-control" placeholder="e.g. Fiber Backhaul / VoIP Portal">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Incident Severity</label>
                            <select name="incident_severity" class="form-select">
                                <option value="SEV-1">SEV-1 (Critical)</option>
                                <option value="SEV-2">SEV-2 (Major)</option>
                                <option value="SEV-3">SEV-3 (Minor)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Impact Description</label>
                            <textarea name="impact_description" class="form-control" rows="3" placeholder="Describe customer and operational impact..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Create Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
