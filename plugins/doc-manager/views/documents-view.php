<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/../models/doc-models.php';

// Handle document creation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_document') {
        try {
            $doc_id = doc_create_document([
                'document_type_id' => $_POST['document_type_id'],
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'classification' => $_POST['classification'],
                'department' => $_POST['department'] ?? '',
                'content' => $_POST['content'] ?? '',
                'status' => 'Draft'
            ]);
            set_flash_message('success', 'Document created successfully.');
            redirect(url_for('doc_manager_document_detail') . '&id=' . $doc_id);
        } catch (Exception $e) {
            set_flash_message('danger', 'Error creating document: ' . $e->getMessage());
        }
    }
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $criteria = [
        'query' => $_GET['q'] ?? '',
        'type_id' => $_GET['type_id'] ?? '',
        'classification' => $_GET['classification'] ?? '',
        'status' => $_GET['status'] ?? '',
        'department' => $_GET['department'] ?? ''
    ];
    $docs = doc_search_documents($criteria);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="documents-export-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Document Number', 'Title', 'Type', 'Classification', 'Version', 'Status', 'Department', 'Created At']);

    foreach ($docs as $d) {
        fputcsv($out, [
            $d['document_number'],
            $d['title'],
            $d['document_type_code'] ?? 'DOC',
            $d['classification'],
            $d['current_version'],
            $d['status'],
            $d['department'] ?? '',
            $d['created_at']
        ]);
    }
    fclose($out);
    exit;
}

// Advanced Search Filtering
$criteria = [
    'query' => $_GET['q'] ?? '',
    'type_id' => $_GET['type_id'] ?? '',
    'classification' => $_GET['classification'] ?? '',
    'status' => $_GET['status'] ?? '',
    'department' => $_GET['department'] ?? ''
];

$documents = doc_search_documents($criteria);
$types = doc_get_all_types();
$canned = doc_get_canned_paragraphs();
?>

<!-- Quill WYSIWYG Rich Editor Resources -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-folder-open me-2"></i>Document Repository</h2>
            <p class="text-muted small mb-0">Search, filter, export, and manage enterprise governed documents.</p>
        </div>
        <div>
            <a href="<?= url_for('doc_manager_documents') ?>&export=csv&<?= http_build_query($_GET) ?>" class="btn btn-outline-success me-2">
                <i class="fa-solid fa-file-csv me-1"></i> Export CSV
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDocModal">
                <i class="fa-solid fa-plus me-1"></i> Create Document
            </button>
        </div>
    </div>

    <!-- Advanced Search Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-body bg-light">
            <form method="GET" action="index.php" class="row g-3">
                <input type="hidden" name="route" value="doc_manager_documents">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search Keyword / Number / Content</label>
                    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($criteria['query']) ?>" placeholder="e.g. RFO-2026, Core Network Outage...">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Document Type</label>
                    <select name="type_id" class="form-select">
                        <option value="">All Document Types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $criteria['type_id'] == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Classification</label>
                    <select name="classification" class="form-select">
                        <option value="">All Classifications</option>
                        <option value="Public" <?= $criteria['classification'] === 'Public' ? 'selected' : '' ?>>Public</option>
                        <option value="Internal" <?= $criteria['classification'] === 'Internal' ? 'selected' : '' ?>>Internal</option>
                        <option value="Confidential" <?= $criteria['classification'] === 'Confidential' ? 'selected' : '' ?>>Confidential</option>
                        <?php if (doc_can_access_lawful_requests() || has_permission('doc_manager_view_restricted')): ?>
                            <option value="Restricted" <?= $criteria['classification'] === 'Restricted' ? 'selected' : '' ?>>Restricted</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="Draft" <?= $criteria['status'] === 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Under Review" <?= $criteria['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="Approved" <?= $criteria['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Published" <?= $criteria['status'] === 'Published' ? 'selected' : '' ?>>Published</option>
                        <option value="Archived" <?= $criteria['status'] === 'Archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</button>
                    <a href="<?= url_for('doc_manager_documents') ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Document List Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Document #</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Classification</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Legal Hold</th>
                            <th>Created Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No documents found matching the search criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><code class="fw-bold"><?= htmlspecialchars($doc['document_number']) ?></code></td>
                                    <td>
                                        <a href="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($doc['title']) ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($doc['document_type_code'] ?: 'DOC') ?></span></td>
                                    <td>
                                        <?php
                                        $class_badge = match($doc['classification']) {
                                            'Public' => 'bg-success',
                                            'Internal' => 'bg-info text-dark',
                                            'Confidential' => 'bg-warning text-dark',
                                            'Restricted' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $class_badge ?>"><?= htmlspecialchars($doc['classification']) ?></span>
                                    </td>
                                    <td><code>v<?= htmlspecialchars($doc['current_version']) ?></code></td>
                                    <td><span class="badge bg-outline-secondary border text-dark"><?= htmlspecialchars($doc['status']) ?></span></td>
                                    <td>
                                        <?php if ($doc['is_under_legal_hold']): ?>
                                            <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i> Legal Hold</span>
                                        <?php else: ?>
                                            <span class="text-muted small">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= date('Y-m-d H:i', strtotime($doc['created_at'])) ?></small></td>
                                    <td class="text-end">
                                        <a href="<?= url_for('doc_manager_document_detail') ?>&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary">View / Edit</a>
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

<!-- Create Document Modal with Word-style WYSIWYG Editor -->
<div class="modal fade" id="createDocModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="<?= url_for('doc_manager_documents') ?>" id="createDocForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_document">
                <input type="hidden" name="content" id="createContentInput">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-header-title mb-0 fw-bold"><i class="fa-solid fa-file-word me-2"></i>Create New Governed Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Document Type <span class="text-danger">*</span></label>
                            <select name="document_type_id" class="form-select" required>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>">
                                        <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Classification <span class="text-danger">*</span></label>
                            <select name="classification" class="form-select" required>
                                <option value="Public">Public - Information approved for public distribution</option>
                                <option value="Internal" selected>Internal - Information intended for internal organizational use</option>
                                <option value="Confidential">Confidential - Sensitive business / operational data</option>
                                <?php if (doc_can_access_lawful_requests() || has_permission('doc_manager_view_restricted')): ?>
                                    <option value="Restricted">Restricted - Highly sensitive / explicitly authorized access</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Document Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Enter document title" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Department / Group</label>
                            <input type="text" name="department" class="form-control" placeholder="e.g. Operations, Legal">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description / Executive Summary</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief summary of document purpose..."></textarea>
                        </div>
                    </div>

                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label fw-semibold mb-0">Full Document Content (Leave empty to load default type template automatically)</label>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                Insert Canned Paragraph
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php foreach ($canned as $ckey => $cval): ?>
                                    <li><a class="dropdown-item small" href="#" onclick="insertCannedText(<?= json_encode($cval) ?>); return false;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $ckey))) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div id="createQuillEditor" style="min-height: 300px; background:#fff;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" onclick="syncCreateQuill()"><i class="fa-solid fa-floppy-disk me-1"></i> Save Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let createQuill;
    document.addEventListener('DOMContentLoaded', function() {
        createQuill = new Quill('#createQuillEditor', {
            theme: 'snow',
            placeholder: 'Type or paste document content here... (Will use default template if left empty)',
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

    function syncCreateQuill() {
        document.getElementById('createContentInput').value = createQuill.root.innerHTML;
    }

    function insertCannedText(text) {
        if (!createQuill) return;
        createQuill.focus();
        let range = createQuill.getSelection();
        let index = (range && range.index !== undefined) ? range.index : createQuill.getLength();
        createQuill.insertText(index, text + "\n");
    }
</script>
