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

// Fetch Post-Mortem details and trackable actions if Post-Mortem document type
$pm_details = null;
$pm_actions = [];
if (($doc['document_type_code'] ?? '') === 'PM' || stristr($doc['document_type_name'] ?? '', 'post-mortem') || stristr($doc['document_type_name'] ?? '', 'post mortem')) {
    $pm_details = doc_get_post_mortem_details($doc_id);
    $pm_actions = doc_get_post_mortem_actions($doc_id);
}

// Generate SVG background watermark URI for HTML canvas compatibility
$svg_text = htmlspecialchars($classification_str, ENT_QUOTES);
$svg_watermark = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='300' height='200'><text x='50%' y='50%' fill='rgba(0,0,0,0.06)' font-size='22' font-weight='bold' font-family='sans-serif' text-anchor='middle' transform='rotate(-30, 150, 100)'>{$svg_text}</text></svg>";

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
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        #pdfContent {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border: 1px solid #dee2e6;
            position: relative;
            <?php if ($watermark_enabled === '1'): ?>
            background-image: url("<?= $svg_watermark ?>");
            background-repeat: repeat;
            <?php endif; ?>
        }
        .pdf-header { border-bottom: 3px solid #0d6efd; padding-bottom: 15px; margin-bottom: 25px; }
        .classification-header { text-align: center; font-weight: bold; padding: 8px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; border-radius: 4px; }
        .classification-header.RESTRICTED { background-color: #dc3545; color: #fff; }
        .classification-header.CONFIDENTIAL { background-color: #ffc107; color: #000; }
        .classification-header.INTERNAL { background-color: #0dcaf0; color: #000; }
        .classification-header.PUBLIC { background-color: #198754; color: #fff; }
        .pdf-footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 40px; font-size: 0.85rem; color: #666; text-align: center; }
        .rich-content img { max-width: 100%; height: auto; }
        .signature-stamp { border: 2px dashed #198754; background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px; }
        .html2pdf__page-break { page-break-before: always; margin-top: 20px; }
        .section-box { background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin-bottom: 20px; }
        .pm-table th { background-color: #e9ecef; }

        <?php if ($watermark_enabled === '1'): ?>
        .watermark-banner {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #6c757d;
            letter-spacing: 3px;
            text-transform: uppercase;
            padding: 4px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 15px;
        }
        <?php endif; ?>

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            #pdfContent { border: none; padding: 0; }
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
            <div class="watermark-banner">
                *** <?= htmlspecialchars($classification_str) ?> SECURITY CLASSIFICATION — CONTROLLED DISTRIBUTION ***
            </div>
        <?php endif; ?>

        <div class="classification-header <?= $classification_str ?>">
            <?= $classification_str ?> — AUTHORIZED ACCESS ONLY
        </div>

        <div class="pdf-header d-flex justify-content-between align-items-center">
            <div class="flex-grow-1 d-flex justify-content-center align-items-center me-3" style="min-height: 70px;">
                <?php if (!empty($company_logo_url)): ?>
                    <img src="<?= htmlspecialchars($company_logo_url) ?>" alt="Logo" style="max-height: 75px; width: auto; object-fit: contain;" class="mx-auto">
                <?php else: ?>
                    <div class="bg-primary text-white rounded px-4 py-2 fw-bold mx-auto text-center d-flex align-items-center justify-content-center">ORGANIZATION LOGO</div>
                <?php endif; ?>
            </div>
            <div class="text-end d-flex align-items-center">
                <div class="text-end">
                    <h4 class="fw-bold mb-0 font-monospace text-primary"><?= htmlspecialchars($doc['document_number']) ?></h4>
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

        <!-- Show Document Content only if NOT an RFO or Post-Mortem report -->
        <?php if (!$rfo_details && !$pm_details): ?>
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
        <?php endif; ?>

        <!-- RFO / Incident Details Section -->
        <?php if ($rfo_details): ?>
            <div class="mb-4 pt-1">
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
                        <table class="table table-sm table-bordered small pm-table">
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

        <!-- Post-Mortem Details Section -->
        <?php if ($pm_details): ?>
            <div class="mb-4 pt-1">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-2 border-primary">
                    <h4 class="fw-bold text-primary mb-0"><i class="fa-solid fa-file-contract me-2"></i>Post-Mortem Technical Review Report</h4>
                </div>

                <div class="section-box">
                    <h6 class="fw-bold text-dark mb-2">1. Executive Summary & Overview</h6>
                    <p class="small mb-2"><strong>Executive Summary:</strong> <?= nl2br(htmlspecialchars($pm_details['executive_summary'] ?? 'N/A')) ?></p>
                    <p class="small mb-0"><strong>Incident Overview:</strong> <?= nl2br(htmlspecialchars($pm_details['incident_overview'] ?? 'N/A')) ?></p>
                </div>

                <div class="section-box">
                    <h6 class="fw-bold text-dark mb-2">2. Business & Technical Impact</h6>
                    <p class="small mb-2"><strong>Business Impact:</strong> <?= nl2br(htmlspecialchars($pm_details['business_impact'] ?? 'N/A')) ?></p>
                    <p class="small mb-0"><strong>Technical Impact:</strong> <?= nl2br(htmlspecialchars($pm_details['technical_impact'] ?? 'N/A')) ?></p>
                </div>

                <div class="section-box">
                    <h6 class="fw-bold text-dark mb-2">3. Analysis & Evaluation</h6>
                    <p class="small mb-2"><strong>What Happened:</strong> <?= nl2br(htmlspecialchars($pm_details['what_happened'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Root Cause:</strong> <?= nl2br(htmlspecialchars($pm_details['root_cause'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Contributing Factors:</strong> <?= nl2br(htmlspecialchars($pm_details['contributing_factors'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Detection Analysis:</strong> <?= nl2br(htmlspecialchars($pm_details['detection_analysis'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Response Analysis:</strong> <?= nl2br(htmlspecialchars($pm_details['response_analysis'] ?? 'N/A')) ?></p>
                    <p class="small mb-0"><strong>Recovery Analysis:</strong> <?= nl2br(htmlspecialchars($pm_details['recovery_analysis'] ?? 'N/A')) ?></p>
                </div>

                <div class="section-box">
                    <h6 class="fw-bold text-dark mb-2">4. Review & Lessons Learned</h6>
                    <p class="small mb-2"><strong>What Went Well:</strong> <?= nl2br(htmlspecialchars($pm_details['what_went_well'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>What Did Not Go Well:</strong> <?= nl2br(htmlspecialchars($pm_details['what_did_not_go_well'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Lessons Learned:</strong> <?= nl2br(htmlspecialchars($pm_details['lessons_learned'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Corrective Actions:</strong> <?= nl2br(htmlspecialchars($pm_details['corrective_actions'] ?? 'N/A')) ?></p>
                    <p class="small mb-2"><strong>Preventative Actions:</strong> <?= nl2br(htmlspecialchars($pm_details['preventative_actions'] ?? 'N/A')) ?></p>
                    <p class="small mb-0"><strong>Follow-Up Work:</strong> <?= nl2br(htmlspecialchars($pm_details['follow_up_work'] ?? 'N/A')) ?></p>
                </div>

                <!-- Trackable Action Items Table -->
                <?php if (!empty($pm_actions)): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-list-check me-2 text-primary"></i>Trackable Post-Mortem Action Items</h6>
                        <table class="table table-sm table-bordered small pm-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Action ID</th>
                                    <th style="width: 35%;">Description</th>
                                    <th style="width: 20%;">Assigned To</th>
                                    <th style="width: 10%;">Priority</th>
                                    <th style="width: 10%;">Status</th>
                                    <th style="width: 10%;">Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pm_actions as $act): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($act['action_identifier']) ?></code></td>
                                        <td><?= htmlspecialchars($act['description']) ?></td>
                                        <td><?= htmlspecialchars($act['assigned_to'] ?: 'Unassigned') ?></td>
                                        <td><span class="badge bg-<?= $act['priority'] === 'High' ? 'danger' : ($act['priority'] === 'Medium' ? 'warning text-dark' : 'secondary') ?>"><?= htmlspecialchars($act['priority']) ?></span></td>
                                        <td><span class="badge bg-<?= $act['status'] === 'Completed' ? 'success' : 'info text-dark' ?>"><?= htmlspecialchars($act['status']) ?></span></td>
                                        <td><?= htmlspecialchars($act['due_date'] ?: 'N/A') ?></td>
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
                margin:       [0.4, 0.4, 0.4, 0.4],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy'] }
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
