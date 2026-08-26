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
    die("Access Denied.");
}

// Log print / PDF export audit event
doc_audit_log('DOCUMENT_EXPORT_PDF', 'document', $doc_id, 'SUCCESS', ['document_number' => $doc['document_number']]);

$approvals = doc_get_approvals($doc_id);
$site_name = get_setting('site_name', 'ENTERPRISE PORTAL');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PDF Export - <?= htmlspecialchars($doc['document_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #fff; color: #333; }
        .pdf-header { border-bottom: 3px solid #0d6efd; padding-bottom: 15px; margin-bottom: 25px; }
        .classification-header { text-align: center; font-weight: bold; padding: 6px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
        .classification-header.RESTRICTED { background-color: #dc3545; color: #fff; }
        .classification-header.CONFIDENTIAL { background-color: #ffc107; color: #000; }
        .classification-header.INTERNAL { background-color: #0dcaf0; color: #000; }
        .classification-header.PUBLIC { background-color: #198754; color: #fff; }
        .pdf-footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 40px; font-size: 0.85rem; color: #666; text-align: center; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="p-5">
    <div class="no-print mb-4 text-end">
        <button onclick="window.print();" class="btn btn-primary"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
    </div>

    <div class="classification-header <?= strtoupper($doc['classification']) ?>">
        <?= strtoupper($doc['classification']) ?> — AUTHORIZED ACCESS ONLY
    </div>

    <div class="pdf-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($site_name) ?></h2>
            <small class="text-muted">Official Governed Document Record</small>
        </div>
        <div class="text-end">
            <h4 class="fw-bold mb-0 font-monospace"><?= htmlspecialchars($doc['document_number']) ?></h4>
            <span class="badge bg-secondary">Version: v<?= htmlspecialchars($doc['current_version']) ?></span>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <p class="mb-1"><strong>Document Title:</strong> <?= htmlspecialchars($doc['title']) ?></p>
            <p class="mb-1"><strong>Type:</strong> <?= htmlspecialchars($doc['document_type_name']) ?></p>
            <p class="mb-1"><strong>Classification:</strong> <?= htmlspecialchars($doc['classification']) ?></p>
        </div>
        <div class="col-6 text-end">
            <p class="mb-1"><strong>Status:</strong> <?= htmlspecialchars($doc['status']) ?></p>
            <p class="mb-1"><strong>Created Date:</strong> <?= date('F d, Y', strtotime($doc['created_at'])) ?></p>
            <p class="mb-1"><strong>Verification Code:</strong> <code class="small"><?= htmlspecialchars(substr($doc['verification_code'] ?? '', 0, 16)) ?>...</code></p>
        </div>
    </div>

    <hr class="my-4">

    <div class="mb-5">
        <h5 class="fw-bold text-dark mb-3">Document Content</h5>
        <div class="p-3 bg-light rounded border">
            <?= nl2br(htmlspecialchars($doc['content'] ?: ($doc['description'] ?: 'No body content recorded.'))) ?>
        </div>
    </div>

    <?php if (!empty($approvals)): ?>
        <div class="mb-5">
            <h5 class="fw-bold text-dark mb-3">Approval History</h5>
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Step</th>
                        <th>Workflow Step</th>
                        <th>Approver</th>
                        <th>Status</th>
                        <th>Decided At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approvals as $app): ?>
                        <tr>
                            <td><?= $app['step_number'] ?></td>
                            <td><?= htmlspecialchars($app['step_name']) ?></td>
                            <td><?= htmlspecialchars($app['approver_name'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($app['status']) ?></td>
                            <td><?= htmlspecialchars($app['decided_at'] ?: 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="pdf-footer">
        <p class="mb-1"><strong><?= htmlspecialchars($doc['classification']) ?></strong> — <?= htmlspecialchars($site_name) ?> Document Management System</p>
        <p class="mb-0">Document Verification Identifier: <code><?= htmlspecialchars($doc['verification_code'] ?? 'N/A') ?></code></p>
    </div>
</body>
</html>
