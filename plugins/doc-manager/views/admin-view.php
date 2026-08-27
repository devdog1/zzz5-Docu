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
        } elseif ($action === 'save_verbose_settings') {
            // Module Enable/Disable Toggles
            doc_set_setting('module_rfo_enabled', !empty($_POST['module_rfo_enabled']) ? '1' : '0');
            doc_set_setting('module_post_mortem_enabled', !empty($_POST['module_post_mortem_enabled']) ? '1' : '0');
            doc_set_setting('module_lawful_enabled', !empty($_POST['module_lawful_enabled']) ? '1' : '0');
            doc_set_setting('module_legal_hold_enabled', !empty($_POST['module_legal_hold_enabled']) ? '1' : '0');
            doc_set_setting('module_retention_enabled', !empty($_POST['module_retention_enabled']) ? '1' : '0');
            doc_set_setting('module_reports_enabled', !empty($_POST['module_reports_enabled']) ? '1' : '0');
            doc_set_setting('widget_dashboard_enabled', !empty($_POST['widget_dashboard_enabled']) ? '1' : '0');

            // Branding & PDF Generation
            doc_set_setting('company_logo_url', trim($_POST['company_logo_url'] ?? ''));
            doc_set_setting('pdf_watermark_enabled', !empty($_POST['pdf_watermark_enabled']) ? '1' : '0');
            doc_set_setting('pdf_footer_notice', trim($_POST['pdf_footer_notice'] ?? ''));
            doc_set_setting('deadline_alert_days', (int)($_POST['deadline_alert_days'] ?? 3));

            // Canned Snippets
            if (isset($_POST['canned'])) {
                doc_set_setting('canned_snippets', json_encode($_POST['canned']));
            }

            set_flash_message('success', 'Verbose plugin settings and module options saved.');
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
$settings = doc_get_all_settings();
?>

<!-- Quill WYSIWYG Rich Editor Resources -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-gears me-2"></i>Verbose Document Plugin Administration</h2>
            <p class="text-muted small mb-0">Granular module toggles, branding, PDF watermarks, canned snippet editor & WYSIWYG document type templates.</p>
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

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="adminTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="modules-tab" data-bs-toggle="tab" data-bs-target="#modules" type="button" role="tab">
                <i class="fa-solid fa-toggle-on me-1 text-primary"></i> Module Toggles & Features
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="branding-tab" data-bs-toggle="tab" data-bs-target="#branding" type="button" role="tab">
                <i class="fa-solid fa-image me-1 text-info"></i> Branding & PDF Export
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="snippets-tab" data-bs-toggle="tab" data-bs-target="#snippets" type="button" role="tab">
                <i class="fa-solid fa-quote-left me-1 text-success"></i> Canned Text Snippets
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                <i class="fa-solid fa-file-word me-1 text-warning"></i> Types & WYSIWYG Templates
            </button>
        </li>
    </ul>

    <form method="POST" action="<?= url_for('doc_manager_admin') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_verbose_settings">

        <div class="tab-content" id="adminTabContent">
            <!-- Tab 1: Module Enable/Disable Toggles -->
            <div class="tab-pane fade show active" id="modules" role="tabpanel">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders me-2 text-primary"></i>Sub-Module Enable / Disable Controls</h5>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Settings</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="module_rfo_enabled" value="1" id="rfoToggle" <?= $settings['module_rfo_enabled'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="rfoToggle">RFO / Incident Report Module</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Enable Reason For Outage incident reporting, severity tracking, and timelines.</small>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="module_post_mortem_enabled" value="1" id="pmToggle" <?= $settings['module_post_mortem_enabled'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="pmToggle">Post-Mortem Review Module</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Enable structured technical post-mortems and trackable action items.</small>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="module_lawful_enabled" value="1" id="lawfulToggle" <?= $settings['module_lawful_enabled'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-danger" for="lawfulToggle">Lawful Requests & Warrants</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Enable statutory court orders and SHA-256 chain of custody tracking.</small>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="module_legal_hold_enabled" value="1" id="holdToggle" <?= $settings['module_legal_hold_enabled'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-danger" for="holdToggle">Legal Hold Preservation</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Enable litigation preservation directives and deletion freezes.</small>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="module_retention_enabled" value="1" id="retentionToggle" <?= $settings['module_retention_enabled'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="retentionToggle">Retention & Disposition Module</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Enable automated retention expiry queues and destruction certificates.</small>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="module_reports_enabled" value="1" id="reportsToggle" <?= $settings['module_reports_enabled'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="reportsToggle">Analytics & Governance Reports</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Enable operational performance and compliance metrics dashboards.</small>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="widget_dashboard_enabled" value="1" id="widgetToggle" <?= $settings['widget_dashboard_enabled'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="widgetToggle">Home Dashboard Quick Widget</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Render Document Management widget card on the portal homepage.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Branding & PDF Export -->
            <div class="tab-pane fade" id="branding" role="tabpanel">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-pdf me-2 text-danger"></i>Branding & PDF Generation Options</h5>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Settings</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Company Logo Image URL or Base64 Data URI</label>
                                <input type="text" name="company_logo_url" class="form-control" value="<?= htmlspecialchars($settings['company_logo_url']) ?>" placeholder="https://domain.com/assets/logo.png or data:image/png;base64,...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Deadline Alert Lead Time (Days)</label>
                                <input type="number" name="deadline_alert_days" class="form-control" value="<?= (int)$settings['deadline_alert_days'] ?>" min="1" max="30">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold d-block">PDF Repeating Background Watermark</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="pdf_watermark_enabled" value="1" id="pdfWatermarkToggle" <?= $settings['pdf_watermark_enabled'] === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="pdfWatermarkToggle">Display Repeating Security Classification Watermark across PDF pages</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">PDF Footer Notice / Disclaimer</label>
                                <input type="text" name="pdf_footer_notice" class="form-control" value="<?= htmlspecialchars($settings['pdf_footer_notice']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Canned Text Snippets -->
            <div class="tab-pane fade" id="snippets" role="tabpanel">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-quote-left me-2 text-success"></i>Canned Paragraph Snippets Manager</h5>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Snippets</button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Edit standardized disclaimers and notices that authors can insert into documents with one click.</p>
                        <div class="row g-3">
                            <?php foreach ($canned as $key => $snippet): ?>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold font-monospace text-primary"><?= htmlspecialchars($key) ?></label>
                                    <textarea name="canned[<?= htmlspecialchars($key) ?>]" class="form-control" rows="3"><?= htmlspecialchars($snippet) ?></textarea>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Types & WYSIWYG Templates -->
            <div class="tab-pane fade" id="templates" role="tabpanel">
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
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Edit Template Modals for each type -->
<?php foreach ($types as $t): ?>
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
<?php endforeach; ?>

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
