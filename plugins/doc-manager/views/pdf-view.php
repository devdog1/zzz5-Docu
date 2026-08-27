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
$owner = current_user()['name'] ?? 'Document Owner';
$company_logo_url = doc_get_setting('company_logo_url', '');
$watermark_enabled = doc_get_setting('pdf_watermark_enabled', '1');
$pdf_footer_notice = doc_get_setting('pdf_footer_notice', 'Governed Document Record — Managed by Portal Framework');
$classification_str = strtoupper($doc['classification'] ?? 'INTERNAL');

// Fetch RFO details and timelines if RFO / Incident document type
$rfo_details = null;
$rfo_timelines = [];
if (($doc['document_type_code'] ?? '') === 'RFO' || stristr($doc['document_type_name'] ?? '', 'rfo') || stristr($doc['document_type_name'] ?? '', 'incident')) {
    $rfo_details = doc_get_rfo_details($doc_id);
    $rfo_timelines = doc_get_rfo_timelines($doc_id);
}

$auto_download = isset($_GET['download']) && $_GET['download'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PDF - <?= htmlspecialchars($doc['document_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #fff; color: #333; position: relative; margin: 0; padding: 20px; overflow-x: hidden; }
        #pdfContent { max-width: 750px; margin: 0 auto; background: #fff; padding: 15px; position: relative; z-index: 2; }
        .pdf-header { border-bottom: 3px solid #0d6efd; padding-bottom: 15px; margin-bottom: 25px; position: relative; z-index: 2; height: 85px; }
        .classification-header { text-align: center; font-weight: bold; padding: 6px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; position: relative; z-index: 2; }
        .classification-header.RESTRICTED { background-color: #dc3545; color: #fff; }
        .classification-header.CONFIDENTIAL { background-color: #ffc107; color: #000; }
        .classification-header.INTERNAL { background-color: #0dcaf0; color: #000; }
        .classification-header.PUBLIC { background-color: #198754; color: #fff; }
        .pdf-footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 40px; font-size: 0.85rem; color: #666; text-align: center; position: relative; z-index: 2; }
        .rich-content img { max-width: 100%; height: auto; }
        .signature-stamp { border: 2px dashed #198754; background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px; }
        .page-break { page-break-before: always; }
        .section-box { background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin-bottom: 20px; }
        .rfo-table th { background-color: #e9ecef; }

        <?php if ($watermark_enabled === '1'): ?>
        .watermark-container {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
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
            font-size: 28px;
            font-weight: 900;
            color: #000;
            transform: rotate(-35deg);
            user-select: none;
            margin: 40px 20px;
            white-space: nowrap;
            letter-spacing: 4px;
        }
        <?php endif; ?>

        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print mb-4 text-center">
        <button onclick="downloadPDF();" class="btn btn-danger fw-bold" id="downloadBtn">
            <i class="fa-solid fa-file-pdf me-1"></i> Download PDF Document
        </button>
        <button onclick="window.print();" class="btn btn-outline-secondary ms-2">
            <i class="fa-solid fa-print me-1"></i> Print View
        </button>
    </div>

    <div id="pdfContent">
        <?php if ($watermark_enabled === '1'): ?>
            <div class="watermark-container">
                <?php for ($i = 0; $i < 20; $i++): ?>
                    <div class="watermark-text"><?= htmlspecialchars($classification_str) ?></div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <div class="classification-header <?= $classification_str ?>">
            <?= $classification_str ?> — AUTHORIZED ACCESS ONLY
        </div>

        <div class="pdf-header d-flex justify-content-between align-items-center">
            <div class="flex-grow-1 d-flex justify-content-center align-items-center" style="height: 100%;">
                <?php if (!empty($company_logo_url)): ?>
                    <img src="<?= htmlspecialchars($company_logo_url) ?>" alt="Logo" style="height: 100%; max-height: 80px; width: auto; object-fit: contain;" class="mx-auto">
                <?php else: ?>
                    <div class="bg-primary text-white rounded px-4 py-2 fw-bold mx-auto text-center d-flex align-items-center justify-content-center" style="height: 100%;">ORGANIZATION LOGO</div>
                <?php endif; ?>
            </div>
            <div class="text-end d-flex align-items-center">
                <div class="text-end">
                    <h4 class="fw-bold mb-0 font-monospace"><?= htmlspecialchars($doc['document_number']) ?></h4>
                    <span class="badge bg-secondary">Version: v<?= htmlspecialchars($doc['current_version']) ?></span>
                </div>
            </div>
        </div>

        <!-- Cover Page / Header Overview -->
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

        <div class="mb-4">
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

        <!-- RFO / Incident Details Section -->
        <?php if ($rfo_details): ?>
            <div class="page-break"></div>
            <div class="mb-4 pt-3">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-2 border-danger">
                    <h4 class="fw-bold text-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Reason For Outage (RFO) Incident Analysis</h4>
                    <span class="badge bg-<?= $rfo_details['incident_severity'] === 'SEV-1' ? 'danger' : 'warning text-dark' ?> fs-6">
                        <?= htmlspecialchars($rfo_details['incident_severity'] ?? 'SEV-2') ?>
                    </span>
                </div>

                <div class="section-box">
                    <h6 class="fw-bold text-dark mb-3">1. Incident Overview & Timings</h6>
                    <div class="row g-2 small">
                        <div class="col-6"><strong>Incident Number:</strong> <?= htmlspecialchars($rfo_details['incident_number'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Total Duration:</strong> <?= htmlspecialchars($rfo_details['total_duration'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Services Affected:</strong> <?= htmlspecialchars($rfo_details['service_affected'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Systems Affected:</strong> <?= htmlspecialchars($rfo_details['systems_affected'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Customers Affected:</strong> <?= htmlspecialchars($rfo_details['customers_affected'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Geographic Areas:</strong> <?= htmlspecialchars($rfo_details['geographic_areas_affected'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Start Time:</strong> <?= htmlspecialchars($rfo_details['start_datetime'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Detection Time:</strong> <?= htmlspecialchars($rfo_details['detection_datetime'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Escalation Time:</strong> <?= htmlspecialchars($rfo_details['escalation_datetime'] ?? 'N/A') ?></div>
                        <div class="col-6"><strong>Restoration Time:</strong> <?= htmlspecialchars($rfo_details['service_restoration_datetime'] ?? 'N/A') ?></div>
                    </div>
                </div>

                <div class="section-box">
                    <h6 class="fw-bold text-dark mb-2">2. Impact & Detection Analysis</h6>
                    <p class="small mb-2"><strong>Impact Description:</strong> <?= nl2br(htmlspecialchars($rfo_details['impact_description'] ?? 'None recorded.')) ?></p>
                    <p class="small mb-2"><strong>Initial Symptoms:</strong> <?= nl2br(htmlspecialchars($rfo_details['initial_symptoms'] ?? 'None recorded.')) ?></p>
                    <p class="small mb-0"><strong>Detection Method:</strong> <?= htmlspecialchars($rfo_details['detection_method'] ?? 'N/A') ?></p>
                </div>

                <div class="section-box">
                    <h6 class="fw-bold text-dark mb-2">3. Root Cause & Contributing Factors</h6>
                    <p class="small mb-2"><strong>Root Cause Analysis:</strong> <?= nl2br(htmlspecialchars($rfo_details['root_cause'] ?? 'Analysis pending.')) ?></p>
                    <p class="small mb-0"><strong>Contributing Factors:</strong> <?= nl2br(htmlspecialchars($rfo_details['contributing_factors'] ?? 'None specified.')) ?></p>
                </div>

                <div class="section-box">
                    <h6 class="fw-bold text-dark mb-2">4. Resolution & Preventative Action Items</h6>
                    <p class="small mb-2"><strong>Resolution Summary:</strong> <?= nl2br(htmlspecialchars($rfo_details['resolution'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Recovery Actions:</strong> <?= nl2br(htmlspecialchars($rfo_details['recovery_actions'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Corrective Actions:</strong> <?= nl2br(htmlspecialchars($rfo_details['corrective_actions'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Preventative Actions:</strong> <?= nl2br(htmlspecialchars($rfo_details['preventative_actions'] ?? 'N/A')) ?></p>
                    <p class="small mb-0"><strong>Lessons Learned:</strong> <?= nl2br(htmlspecialchars($rfo_details['lessons_learned'] ?? 'N/A')) ?></p>
                </div>

                <!-- Interactive Timeline Table -->
                <?php if (!empty($rfo_timelines)): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-clock-rotate-left me-2"></i>Chronological Event Timeline</h6>
                        <table class="table table-sm table-bordered small rfo-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Timestamp</th>
                                    <th style="width: 35%;">Event Description</th>
                                    <th style="width: 20%;">Person / Actor</th>
                                    <th style="width: 20%;">Source / Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rfo_timelines as $t): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($t['timestamp']) ?></code></td>
                                        <td><?= htmlspecialchars($t['event']) ?></td>
                                        <td><?= htmlspecialchars($t['person'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($t['source'] ?: '') ?> <?= !empty($t['notes']) ? '(' . htmlspecialchars($t['notes']) . ')' : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
                                <div><strong>Signer:</strong> <?= htmlspecialchars($app['approver_name'] ?: 'Pending Assignment') ?></div>
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

        <div class="pdf-footer text-center">
            <p class="mb-1"><strong><?= htmlspecialchars($doc['classification']) ?></strong> — <?= htmlspecialchars($pdf_footer_notice) ?></p>
            <p class="mb-0">Document Verification Identifier: <code><?= htmlspecialchars($doc['verification_code'] ?? 'N/A') ?></code></p>
        </div>
    </div>

    <script>
        function downloadPDF() {
            let btn = document.getElementById('downloadBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Generating PDF...';

            let element = document.getElementById('pdfContent');
            let filename = '<?= htmlspecialchars($doc['document_number']) ?>.pdf';

            let opt = {
                margin:       [0.3, 0.3, 0.3, 0.3],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false, windowWidth: 800 },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-file-pdf me-1"></i> Download PDF Document';
            }).catch(function(err) {
                console.error("PDF generation error: ", err);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-file-pdf me-1"></i> Download PDF Document';
            });
        }

        <?php if ($auto_download): ?>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(downloadPDF, 500);
            });
        <?php endif; ?>
    </script>
</body>
</html>
