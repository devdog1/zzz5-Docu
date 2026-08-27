<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
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

doc_audit_log('DOCUMENT_EXPORT_PDF', 'document', $doc_id, 'SUCCESS', ['document_number' => $doc['document_number']]);

$approvals = doc_get_approvals($doc_id);
$site_name = get_setting('site_name', 'ENTERPRISE PORTAL');
$owner = current_user()['name'] ?? 'Document Owner';
$company_logo_url = doc_get_setting('company_logo_url', '');
$watermark_enabled = doc_get_setting('pdf_watermark_enabled', '1');
$pdf_footer_notice = doc_get_setting('pdf_footer_notice', 'Governed Document Record — Managed by Portal Framework');
$classification_str = strtoupper($doc['classification'] ?? 'INTERNAL');

$verify_url = url_for('doc_manager_document_detail') . '&id=' . $doc['id'] . '&v=' . urlencode($doc['verification_code'] ?? '');
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=" . urlencode($verify_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PDF Export - <?= htmlspecialchars($doc['document_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #fff; color: #333; position: relative; }
        .pdf-header { border-bottom: 3px solid #0d6efd; padding-bottom: 15px; margin-bottom: 25px; position: relative; z-index: 2; }
        .classification-header { text-align: center; font-weight: bold; padding: 6px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; position: relative; z-index: 2; }
        .classification-header.RESTRICTED { background-color: #dc3545; color: #fff; }
        .classification-header.CONFIDENTIAL { background-color: #ffc107; color: #000; }
        .classification-header.INTERNAL { background-color: #0dcaf0; color: #000; }
        .classification-header.PUBLIC { background-color: #198754; color: #fff; }
        .pdf-footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 40px; font-size: 0.85rem; color: #666; text-align: center; position: relative; z-index: 2; }
        .rich-content img { max-width: 100%; height: auto; }
        .signature-stamp { border: 2px dashed #198754; background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px; }

        <?php if ($watermark_enabled === '1'): ?>
        .watermark-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
            opacity: 0.08;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            align-content: space-around;
        }
        .watermark-text {
            font-size: 32px;
            font-weight: 900;
            color: #000;
            transform: rotate(-35deg);
            user-select: none;
            margin: 60px 40px;
            white-space: nowrap;
            letter-spacing: 4px;
        }
        <?php endif; ?>

        .content-body { position: relative; z-index: 2; }

        @media print {
            .no-print { display: none !important; }
            @page {
                @bottom-right {
                    content: "Page " counter(page) " of " counter(pages);
                }
            }
        }
    </style>
</head>
<body class="p-5">

    <?php if ($watermark_enabled === '1'): ?>
        <div class="watermark-container">
            <?php for ($i = 0; $i < 24; $i++): ?>
                <div class="watermark-text"><?= htmlspecialchars($classification_str) ?></div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <div class="content-body">
        <div class="no-print mb-4 text-end">
            <button onclick="window.print();" class="btn btn-primary"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
        </div>

        <div class="classification-header <?= $classification_str ?>">
            <?= $classification_str ?> — AUTHORIZED ACCESS ONLY
        </div>

        <div class="pdf-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <?php if (!empty($company_logo_url)): ?>
                    <img src="<?= htmlspecialchars($company_logo_url) ?>" alt="Logo" style="max-height:65px;" class="me-3">
                <?php else: ?>
                    <div class="bg-primary text-white rounded p-2 me-3 fw-bold">ORGANIZATION LOGO</div>
                <?php endif; ?>
                <div>
                    <h2 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($site_name) ?></h2>
                    <small class="text-muted">Official Governed Document Record</small>
                </div>
            </div>
            <div class="text-end d-flex align-items-center">
                <div class="me-3 text-end">
                    <h4 class="fw-bold mb-0 font-monospace"><?= htmlspecialchars($doc['document_number']) ?></h4>
                    <span class="badge bg-secondary">Version: v<?= htmlspecialchars($doc['current_version']) ?></span>
                </div>
                <img src="<?= htmlspecialchars($qr_code_url) ?>" alt="Verification QR Code" style="width: 75px; height: 75px;" class="border p-1 bg-white">
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <p class="mb-1"><strong>Document Title:</strong> <?= htmlspecialchars($doc['title']) ?></p>
                <p class="mb-1"><strong>Type:</strong> <?= htmlspecialchars($doc['document_type_name']) ?></p>
                <p class="mb-1"><strong>Classification:</strong> <?= htmlspecialchars($doc['classification']) ?></p>
                <p class="mb-1"><strong>Document Owner:</strong> <?= htmlspecialchars($owner) ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1"><strong>Status:</strong> <?= htmlspecialchars($doc['status']) ?></p>
                <p class="mb-1"><strong>Created Date:</strong> <?= date('F d, Y', strtotime($doc['created_at'])) ?></p>
                <p class="mb-1"><strong>Verification Hash:</strong> <code class="small"><?= htmlspecialchars(substr($doc['verification_code'] ?? '', 0, 16)) ?>...</code></p>
            </div>
        </div>

        <hr class="my-4">

        <div class="mb-5">
            <h5 class="fw-bold text-dark mb-3">Document Content</h5>
            <div class="p-4 bg-light rounded border rich-content">
                <?php
                $raw_content = $doc['content'] ?: ($doc['description'] ?: '<p>No body content recorded.</p>');
                if (strip_tags($raw_content) !== $raw_content) {
                    echo $raw_content;
                } else {
                    echo nl2br(htmlspecialchars($raw_content));
                }
                ?>
            </div>
        </div>

        <?php if (!empty($approvals)): ?>
            <div class="mb-5">
                <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-file-signature me-2 text-success"></i>Digital Authorization Sign-off Audit</h5>
                <div class="row g-3">
                    <?php foreach ($approvals as $app): ?>
                        <div class="col-md-6">
                            <div class="signature-stamp">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-primary"><?= htmlspecialchars($app['step_name']) ?></strong>
                                    <span class="badge bg-<?= $app['status'] === 'Approved' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($app['status']) ?></span>
                                </div>
                                <div><strong>Authorized Signer:</strong> <?= htmlspecialchars($app['approver_name'] ?: 'Pending Assignment') ?></div>
                                <?php if (!empty($app['decided_at'])): ?>
                                    <div><strong>Signed At:</strong> <?= date('F d, Y H:i:s', strtotime($app['decided_at'])) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($app['signature_hash'])): ?>
                                    <div><strong>Signature Hash:</strong> <code class="small"><?= htmlspecialchars(substr($app['signature_hash'], 0, 24)) ?>...</code></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="pdf-footer d-flex justify-content-between align-items-center">
            <div>
                <p class="mb-1"><strong><?= htmlspecialchars($doc['classification']) ?></strong> — <?= htmlspecialchars($pdf_footer_notice) ?></p>
                <p class="mb-0">Document Verification Identifier: <code><?= htmlspecialchars($doc['verification_code'] ?? 'N/A') ?></code></p>
            </div>
            <div>
                <small class="text-muted">Scan QR to verify document version</small>
            </div>
        </div>
    </div>
</body>
</html>
