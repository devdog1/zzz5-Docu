<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/../models/doc-models.php';

if (!has_permission('doc_manager_manage_types') && !has_permission('manage_settings')) {
    die("Access Denied: Administration rights required.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_type') {
            $pdb = doc_get_pdb();
            $tb = $pdb->getTableName('document_types');
            $pdb->query("
                INSERT INTO {$tb} (name, code, description, default_classification, numbering_format, retention_period_years)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [
                $_POST['name'],
                strtoupper($_POST['code']),
                $_POST['description'],
                $_POST['default_classification'],
                $_POST['numbering_format'],
                (int)$_POST['retention_period_years']
            ]);
            set_flash_message('success', 'Document type added.');
            redirect(url_for('doc_manager_admin'));
        } elseif ($action === 'seed_demo') {
            doc_seed_demo_data();
            set_flash_message('success', 'Sample demo incident report generated for testing.');
            redirect(url_for('doc_manager_admin'));
        }
    } catch (Exception $e) {
        set_flash_message('danger', 'Error: ' . $e->getMessage());
    }
}

$types = doc_get_all_types();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-gears me-2"></i>Document Administration Interface</h2>
            <p class="text-muted small mb-0">Configure document types, auto-numbering formats, default classifications & retention rules.</p>
        </div>
        <div>
            <form method="POST" action="<?= url_for('doc_manager_admin') ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="seed_demo">
                <button type="submit" class="btn btn-outline-info me-2"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Seed Sample Demo Record</button>
            </form>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTypeModal">
                <i class="fa-solid fa-plus me-1"></i> Add Document Type
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders me-2 text-primary"></i>Configured Document Types</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Type Name</th>
                            <th>Default Classification</th>
                            <th>Numbering Format</th>
                            <th>Retention Period</th>
                            <th>Counter</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $t): ?>
                            <tr>
                                <td><code class="fw-bold bg-light px-2 py-1 rounded border"><?= htmlspecialchars($t['code']) ?></code></td>
                                <td class="fw-bold"><?= htmlspecialchars($t['name']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($t['default_classification']) ?></span></td>
                                <td><code><?= htmlspecialchars($t['numbering_format']) ?></code></td>
                                <td><?= (int)$t['retention_period_years'] ?> Years</td>
                                <td><code><?= (int)$t['current_counter'] ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Type Modal -->
<div class="modal fade" id="createTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_admin') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_type">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Configure New Document Type</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Type Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Audit Finding Report">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" required placeholder="e.g. AUD">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Default Classification</label>
                            <select name="default_classification" class="form-select">
                                <option value="Public">Public</option>
                                <option value="Internal" selected>Internal</option>
                                <option value="Confidential">Confidential</option>
                                <option value="Restricted">Restricted</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Numbering Format</label>
                            <input type="text" name="numbering_format" class="form-control" value="{CODE}-{YYYY}-{NUMBER:6}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Retention Period (Years)</label>
                            <input type="number" name="retention_period_years" class="form-control" value="7">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Document Type</button>
                </div>
            </form>
        </div>
    </div>
</div>
