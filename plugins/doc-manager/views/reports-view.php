<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/../models/doc-models.php';

$all_rfos = doc_search_documents(['type_id' => doc_get_type_id_by_code('RFO')]);
$all_pms = doc_search_documents(['type_id' => doc_get_type_id_by_code('PM')]);
$all_actions = doc_get_post_mortem_actions();

$can_legal = doc_can_access_lawful_requests();
$lawful_count = 0;
if ($can_legal) {
    $lwos = doc_search_documents(['type_id' => doc_get_type_id_by_code('LWO')]);
    $lawful_count = count($lwos);
}

// Group RFOs by severity safely
$sev1_count = count(array_filter($all_rfos, fn($r) => strpos($r['title'], 'SEV-1') !== false || ($r['classification'] ?? '') === 'SEV-1'));
$sev2_count = count(array_filter($all_rfos, fn($r) => strpos($r['title'], 'SEV-2') !== false || ($r['classification'] ?? '') === 'SEV-2'));

$completed_actions = count(array_filter($all_actions, fn($a) => $a['status'] === 'Completed'));
$open_actions = count($all_actions) - $completed_actions;
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-chart-column me-2"></i>Analytics & Governance Reports</h2>
            <p class="text-muted small mb-0">Operational performance, RFO metrics, corrective actions completion & compliance audits.</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                    <span class="text-muted small">Total Incident Reports (RFO)</span>
                    <h3 class="fw-bold text-primary mb-0"><?= count($all_rfos) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-info">
                <div class="card-body">
                    <span class="text-muted small">Total Post-Mortems</span>
                    <h3 class="fw-bold text-info mb-0"><?= count($all_pms) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <span class="text-muted small">Completed Action Items</span>
                    <h3 class="fw-bold text-success mb-0"><?= $completed_actions ?> / <?= count($all_actions) ?></h3>
                </div>
            </div>
        </div>
        <?php if ($can_legal): ?>
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-4 border-danger">
                    <div class="card-body">
                        <span class="text-muted small">Total Lawful Requests</span>
                        <h3 class="fw-bold text-danger mb-0"><?= $lawful_count ?></h3>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check me-2 text-success"></i>Corrective Actions Completion Rate</h5>
                </div>
                <div class="card-body">
                    <?php
                    $percent = count($all_actions) > 0 ? round(($completed_actions / count($all_actions)) * 100) : 100;
                    ?>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar bg-success font-monospace fw-bold" role="progressbar" style="width: <?= $percent ?>%;"><?= $percent ?>% Completed</div>
                    </div>
                    <p class="small text-muted mb-0">Total Tracked Actions: <strong><?= count($all_actions) ?></strong> | Open Actions: <strong class="text-warning"><?= $open_actions ?></strong></p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-shield-halved me-2 text-primary"></i>Security Classification Breakdown</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Public Information
                            <span class="badge bg-success rounded-pill">Public</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Internal Organizational Use
                            <span class="badge bg-info text-dark rounded-pill">Internal</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Confidential Business Data
                            <span class="badge bg-warning text-dark rounded-pill">Confidential</span>
                        </li>
                        <?php if ($can_legal): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Restricted (Lawful / Warrants / Credentials)
                                <span class="badge bg-danger rounded-pill">Restricted</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
