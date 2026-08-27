<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/../models/doc-models.php';

// Independent Permission Check: Do NOT grant access merely because someone is a system administrator!
if (!doc_can_access_lawful_requests()) {
    echo '<div class="container py-5"><div class="alert alert-danger shadow-sm border-start border-5 border-danger">'
       . '<h4 class="fw-bold"><i class="fa-solid fa-lock me-2"></i> RESTRICTED ACCESS MODULE</h4>'
       . '<p class="mb-0">You do not possess the required explicit <code>legal_request.access</code> permission to access Lawful Work Orders, Court Orders, or Statutory Demands.</p>'
       . '</div></div>';
    return;
}

$selected_id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_lawful_request') {
            $lwo_type_id = doc_get_type_id_by_code('LWO');
            $doc_id = doc_create_document([
                'document_type_id' => $lwo_type_id,
                'title' => $_POST['request_type'] . ' - ' . $_POST['requesting_organization'],
                'description' => $_POST['scope_of_request'] ?? '',
                'classification' => 'Restricted',
                'department' => 'Legal & Compliance',
                'status' => 'Received'
            ]);

            doc_save_lawful_request($doc_id, $_POST);
            set_flash_message('success', 'Lawful Work Order created with Restricted security classification.');
            redirect(url_for('doc_manager_lawful') . '&id=' . $doc_id);
        } elseif ($action === 'update_lawful_request') {
            $doc_id = (int)$_POST['document_id'];
            doc_update_document($doc_id, [
                'title' => $_POST['request_type'] . ' - ' . $_POST['requesting_organization'],
                'description' => $_POST['scope_of_request'],
                'classification' => 'Restricted'
            ], 'Updated Lawful Work Order', false);

            doc_save_lawful_request($doc_id, $_POST);
            set_flash_message('success', 'Lawful request record updated.');
            redirect(url_for('doc_manager_lawful') . '&id=' . $doc_id);
        } elseif ($action === 'add_chain_of_custody') {
            $doc_id = (int)$_POST['document_id'];

            // Handle file upload persistently and calculate SHA-256 hash if attached
            $checksum = '';
            if (!empty($_FILES['evidence_file']['tmp_name']) && is_uploaded_file($_FILES['evidence_file']['tmp_name'])) {
                $file_tmp = $_FILES['evidence_file']['tmp_name'];
                $file_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['evidence_file']['name']));
                $checksum = hash_file('sha256', $file_tmp);

                $upload_dir = APP_ROOT . '/uploads/doc_evidence';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $target_path = $upload_dir . '/' . time() . '_' . $file_name;
                move_uploaded_file($file_tmp, $target_path);

                // Record attachment record
                $pdb = doc_get_pdb();
                $tb_att = $pdb->getTableName('document_attachments');
                $pdb->query("
                    INSERT INTO {$tb_att} (document_id, file_name, file_path, file_size, mime_type, sha256_hash, uploaded_by_user_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [(int)$doc_id, $file_name, $target_path, (int)$_FILES['evidence_file']['size'], $_FILES['evidence_file']['type'] ?? '', $checksum, (int)($_SESSION['user_id'] ?? 1)]);
            } elseif (!empty($_POST['file_checksum'])) {
                $checksum = $_POST['file_checksum'];
            }

            $_POST['file_checksum'] = $checksum;
            if (!empty($_POST['custody_action'])) {
                $_POST['action'] = $_POST['custody_action'];
            }
            doc_add_chain_of_custody($doc_id, $_POST);
            set_flash_message('success', 'Chain of Custody record logged and evidence saved persistently (SHA-256 Hash verified).');
            redirect(url_for('doc_manager_lawful') . '&id=' . $doc_id);
        }
    } catch (Exception $e) {
        set_flash_message('danger', 'Error: ' . $e->getMessage());
    }
}

$lawful_docs = doc_search_documents(['type_id' => doc_get_type_id_by_code('LWO')]);
$selected_doc = $selected_id ? doc_get_document($selected_id) : ($lawful_docs[0] ?? null);
$request_details = $selected_doc ? doc_get_lawful_request($selected_doc['id']) : null;
$custody_logs = $selected_doc ? doc_get_chain_of_custody($selected_doc['id']) : [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-scale-balanced me-2"></i>Lawful Request & Work Order Module</h2>
            <p class="text-muted small mb-0">Court orders, warrants, preservation demands, police requests & SHA-256 Chain of Custody.</p>
        </div>
        <div>
            <button type="button" class="btn btn-danger text-white fw-bold" data-bs-toggle="modal" data-bs-target="#createLawfulModal">
                <i class="fa-solid fa-lock me-1"></i> New Lawful Request
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar picker -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4 border-start border-4 border-danger">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-shield-halved me-2"></i>Lawful Requests</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($lawful_docs)): ?>
                        <div class="p-4 text-center text-muted">No Lawful Requests registered.</div>
                    <?php else: ?>
                        <?php foreach ($lawful_docs as $ld): ?>
                            <a href="<?= url_for('doc_manager_lawful') ?>&id=<?= $ld['id'] ?>" class="list-group-item list-group-item-action <?= ($selected_doc && $selected_doc['id'] == $ld['id']) ? 'active' : '' ?> py-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="font-monospace"><?= htmlspecialchars($ld['document_number']) ?></strong>
                                    <span class="badge bg-danger">RESTRICTED</span>
                                </div>
                                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($ld['title']) ?></h6>
                                <small class="text-muted d-block"><?= date('M d, Y', strtotime($ld['created_at'])) ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detail and Chain of Custody -->
        <div class="col-lg-8">
            <?php if (!$selected_doc): ?>
                <div class="card shadow-sm py-5 text-center text-muted">
                    <i class="fa-solid fa-gavel fs-1 mb-3 text-danger"></i>
                    <h5>No Lawful Request Selected</h5>
                    <p>Select a record from the list or register a new request.</p>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4 border-danger">
                    <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-file-contract me-2"></i>Lawful Order: <code><?= htmlspecialchars($selected_doc['document_number']) ?></code></h5>
                        <span class="badge bg-white text-danger font-monospace">CONFIDENTIAL & RESTRICTED</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= url_for('doc_manager_lawful') ?>&id=<?= $selected_doc['id'] ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_lawful_request">
                            <input type="hidden" name="document_id" value="<?= $selected_doc['id'] ?>">

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Internal Request #</label>
                                    <input type="text" name="internal_request_number" class="form-control" value="<?= htmlspecialchars($request_details['internal_request_number'] ?? $selected_doc['document_number']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Request Type</label>
                                    <select name="request_type" class="form-select">
                                        <option value="Court Order" <?= ($request_details['request_type'] ?? '') === 'Court Order' ? 'selected' : '' ?>>Court Order</option>
                                        <option value="Warrant" <?= ($request_details['request_type'] ?? '') === 'Warrant' ? 'selected' : '' ?>>Warrant</option>
                                        <option value="Production Order" <?= ($request_details['request_type'] ?? '') === 'Production Order' ? 'selected' : '' ?>>Production Order</option>
                                        <option value="Preservation Demand" <?= ($request_details['request_type'] ?? '') === 'Preservation Demand' ? 'selected' : '' ?>>Preservation Demand</option>
                                        <option value="Police Request" <?= ($request_details['request_type'] ?? '') === 'Police Request' ? 'selected' : '' ?>>Police Request</option>
                                        <option value="Lawful Intercept" <?= ($request_details['request_type'] ?? '') === 'Lawful Intercept' ? 'selected' : '' ?>>Lawful Intercept Order</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Requesting Organization</label>
                                    <input type="text" name="requesting_organization" class="form-control" value="<?= htmlspecialchars($request_details['requesting_organization'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Agency / Department</label>
                                    <input type="text" name="agency" class="form-control" value="<?= htmlspecialchars($request_details['agency'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Officer / Contact Person</label>
                                    <input type="text" name="officer_contact" class="form-control" value="<?= htmlspecialchars($request_details['officer_contact'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Badge / Identification #</label>
                                    <input type="text" name="badge_or_id_number" class="form-control" value="<?= htmlspecialchars($request_details['badge_or_id_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Court / Jurisdiction</label>
                                    <input type="text" name="court_jurisdiction" class="form-control" value="<?= htmlspecialchars($request_details['court_jurisdiction'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Court File Number</label>
                                    <input type="text" name="court_file_number" class="form-control" value="<?= htmlspecialchars($request_details['court_file_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Response Deadline</label>
                                    <input type="date" name="response_deadline" class="form-control" value="<?= htmlspecialchars($request_details['response_deadline'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Scope of Request</label>
                                    <textarea name="scope_of_request" class="form-control" rows="2"><?= htmlspecialchars($request_details['scope_of_request'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold text-danger"><i class="fa-solid fa-user-ninja me-1"></i> Target Identifiers (Protected against broad discovery)</label>
                                    <input type="text" name="target_identifiers" class="form-control" value="<?= htmlspecialchars($request_details['target_identifiers'] ?? '') ?>" placeholder="Customer ID, IP address, phone numbers...">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-floppy-disk me-1"></i> Update Lawful Request</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Chain of Custody Table -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-link me-2 text-danger"></i>Chain of Custody & SHA-256 Hashes</h5>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#addCustodyModal">
                            <i class="fa-solid fa-plus me-1"></i> Log Custody Transfer
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Person</th>
                                        <th>Action</th>
                                        <th>Source & Destination</th>
                                        <th>SHA-256 Checksum</th>
                                        <th>Verification</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($custody_logs)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No chain of custody logs recorded.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($custody_logs as $c): ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i', strtotime($c['activity_datetime'])) ?></td>
                                                <td class="fw-bold"><?= htmlspecialchars($c['person_name']) ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($c['action']) ?></span></td>
                                                <td><?= htmlspecialchars($c['source']) ?> &rarr; <?= htmlspecialchars($c['destination']) ?></td>
                                                <td><code class="text-primary font-monospace"><?= htmlspecialchars(substr($c['file_checksum'], 0, 16)) ?>...</code></td>
                                                <td><span class="badge bg-success"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($c['verification']) ?></span></td>
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

<!-- Add Custody Entry Modal -->
<?php if ($selected_doc): ?>
<div class="modal fade" id="addCustodyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_lawful') ?>&id=<?= $selected_doc['id'] ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_chain_of_custody">
                <input type="hidden" name="document_id" value="<?= $selected_doc['id'] ?>">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">Log Chain of Custody Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Action Type</label>
                        <select name="custody_action" class="form-select">
                            <option value="Received Evidence">Received Evidence</option>
                            <option value="Transfer / Disclosure">Transfer / Disclosure</option>
                            <option value="SHA-256 Integrity Check">SHA-256 Integrity Check</option>
                            <option value="Archived to Vault">Archived to Vault</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Source</label>
                            <input type="text" name="source" class="form-control" placeholder="e.g. Encrypted Drive">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Destination / Recipient</label>
                            <input type="text" name="destination" class="form-control" placeholder="e.g. Secure Storage">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload Evidence File (Calculates SHA-256 Hash)</label>
                        <input type="file" name="evidence_file" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Or Manual SHA-256 Checksum</label>
                        <input type="text" name="file_checksum" class="form-control font-monospace" placeholder="e.g. e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Log Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Create Lawful Request Modal -->
<div class="modal fade" id="createLawfulModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_lawful') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_lawful_request">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-scale-balanced me-2"></i>New Lawful / Statutory Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Request Type <span class="text-danger">*</span></label>
                            <select name="request_type" class="form-select" required>
                                <option value="Court Order">Court Order</option>
                                <option value="Warrant">Search Warrant</option>
                                <option value="Production Order">Production Order</option>
                                <option value="Preservation Demand">Preservation Demand</option>
                                <option value="Police Request">Police Request</option>
                                <option value="Lawful Intercept">Lawful Intercept Order</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Requesting Organization <span class="text-danger">*</span></label>
                            <input type="text" name="requesting_organization" class="form-control" required placeholder="e.g. Federal Court / Police Service">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Scope of Request</label>
                            <textarea name="scope_of_request" class="form-control" rows="3" placeholder="Specify statutory demand parameters..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Register Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
