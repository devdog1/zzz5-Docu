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
                INSERT INTO {$tb} (name, code, description, default_classification, numbering_format, retention_period_years, template)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $_POST['name'],
                strtoupper($_POST['code']),
                $_POST['description'],
                $_POST['default_classification'],
                $_POST['numbering_format'],
                (int)$_POST['retention_period_years'],
                $_POST['template'] ?? ''
            ]);
            set_flash_message('success', 'Document type added.');
            redirect(url_for('doc_manager_admin'));
        } elseif ($action === 'update_type_template') {
            doc_update_type_template((int)$_POST['type_id'], $_POST['template'], $_POST['numbering_format'], $_POST['default_classification'], (int)$_POST['retention_period_years']);
            set_flash_message('success', 'Document type template updated successfully.');
            redirect(url_for('doc_manager_admin'));
        } elseif ($action === 'save_global_settings') {
            set_setting('doc_manager_company_logo_url', trim($_POST['company_logo_url'] ?? ''));
            set_setting('doc_manager_pdf_watermark_enabled', !empty($_POST['pdf_watermark_enabled']) ? '1' : '0');
            set_flash_message('success', 'Global branding and PDF watermark settings saved.');
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
$canned = doc_get_canned_paragraphs();
$company_logo_url = get_setting('doc_manager_company_logo_url', '');
$pdf_watermark_enabled = get_setting('doc_manager_pdf_watermark_enabled', '1');
?>

<!-- Quill WYSIWYG Rich Editor Resources -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-gears me-2"></i>Document Administration & Template Editor</h2>
            <p class="text-muted small mb-0">Configure document types, auto-numbering formats, branding logos, watermark options & WYSIWYG templates.</p>
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

    <!-- Global Branding & Watermark Settings -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-image me-2 text-primary"></i>Company Logo & PDF Watermark Settings</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= url_for('doc_manager_admin') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_global_settings">
                <div class="row g-3 align-items-center">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Company Logo Image URL or Base64 Data URI</label>
                        <input type="text" name="company_logo_url" class="form-control" value="<?= htmlspecialchars($company_logo_url) ?>" placeholder="https://domain.com/assets/logo.png or data:image/png;base64,...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold d-block">PDF Watermark</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="pdf_watermark_enabled" value="1" id="watermarkToggle" <?= $pdf_watermark_enabled === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="watermarkToggle">Enable Repeating Classification Watermark on PDF</label>
                        </div>
                    </div>
                    <div class="col-md-2 mt-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-floppy-disk me-1"></i> Save Branding</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Configured Document Types & Template Editors -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders me-2 text-primary"></i>Configured Document Types & WYSIWYG Templates</h5>
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
                            <th class="text-end">Template Editor</th>
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
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTemplateModal<?= $t['id'] ?>">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit WYSIWYG Template
                                    </button>

                                    <!-- Edit Template Modal -->
                                    <div class="modal fade text-start" id="editTemplateModal<?= $t['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-xl">
                                            <div class="modal-content">
                                                <form method="POST" action="<?= url_for('doc_manager_admin') ?>" id="templateForm<?= $t['id'] ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_type_template">
                                                    <input type="hidden" name="type_id" value="<?= $t['id'] ?>">
                                                    <input type="hidden" name="template" id="templateInput<?= $t['id'] ?>">

                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-word me-2"></i>Word-Style WYSIWYG Template Editor: <?= htmlspecialchars($t['name']) ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Numbering Format</label>
                                                                <input type="text" name="numbering_format" class="form-control" value="<?= htmlspecialchars($t['numbering_format']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Default Classification</label>
                                                                <select name="default_classification" class="form-select">
                                                                    <option value="Public" <?= $t['default_classification'] === 'Public' ? 'selected' : '' ?>>Public</option>
                                                                    <option value="Internal" <?= $t['default_classification'] === 'Internal' ? 'selected' : '' ?>>Internal</option>
                                                                    <option value="Confidential" <?= $t['default_classification'] === 'Confidential' ? 'selected' : '' ?>>Confidential</option>
                                                                    <option value="Restricted" <?= $t['default_classification'] === 'Restricted' ? 'selected' : '' ?>>Restricted</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Retention Period (Years)</label>
                                                                <input type="number" name="retention_period_years" class="form-control" value="<?= (int)$t['retention_period_years'] ?>" required>
                                                            </div>
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold d-block">Quick Insert Placeholders</label>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" onclick="insertTag<?= $t['id'] ?>('{ORGANIZATION_LOGO}')">+ Logo</button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" onclick="insertTag<?= $t['id'] ?>('{DOCUMENT_NUMBER}')">+ Doc #</button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" onclick="insertTag<?= $t['id'] ?>('{TITLE}')">+ Title</button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" onclick="insertTag<?= $t['id'] ?>('{CLASSIFICATION}')">+ Classification</button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" onclick="insertTag<?= $t['id'] ?>('{DEPARTMENT}')">+ Department</button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" onclick="insertTag<?= $t['id'] ?>('{DATE}')">+ Date</button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" onclick="insertTag<?= $t['id'] ?>('{OWNER}')">+ Owner</button>
                                                        </div>

                                                        <label class="form-label fw-semibold">Rich Text Document Body Template</label>
                                                        <div id="quillEditor<?= $t['id'] ?>" style="min-height: 250px; background:#fff;">
                                                            <?= $t['template'] ?? '' ?>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary" onclick="syncQuill<?= $t['id'] ?>()"><i class="fa-solid fa-floppy-disk me-1"></i> Save Template</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <script>
                                        let quill<?= $t['id'] ?>;
                                        document.addEventListener('DOMContentLoaded', function() {
                                            quill<?= $t['id'] ?> = new Quill('#quillEditor<?= $t['id'] ?>', {
                                                theme: 'snow',
                                                modules: {
                                                    toolbar: [
                                                        [{ 'header': [1, 2, 3, 4, false] }],
                                                        ['bold', 'italic', 'underline', 'strike'],
                                                        [{ 'color': [] }, { 'background': [] }],
                                                        [{ 'align': [] }],
                                                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                                        ['blockquote', 'code-block'],
                                                        ['link', 'image'],
                                                        ['clean']
                                                    ]
                                                }
                                            });
                                        });

                                        function syncQuill<?= $t['id'] ?>() {
                                            document.getElementById('templateInput<?= $t['id'] ?>').value = quill<?= $t['id'] ?>.root.innerHTML;
                                        }

                                        function insertTag<?= $t['id'] ?>(tag) {
                                            let range = quill<?= $t['id'] ?>.getSelection(true);
                                            quill<?= $t['id'] ?>.insertText(range.index, tag);
                                        }
                                    </script>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Canned Paragraph Reference Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-quote-left me-2 text-info"></i>Available Canned Paragraph Snippets</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($canned as $key => $snippet): ?>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <strong class="text-primary font-monospace"><?= htmlspecialchars($key) ?></strong>
                            <p class="small text-muted mb-0 mt-1"><em>"<?= htmlspecialchars($snippet) ?>"</em></p>
                        </div>
                    </div>
                <?php endforeach; ?>
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
