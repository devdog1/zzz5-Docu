<?php
// doc-tasks.php - Background Scheduled Tasks for Document Management System

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/../models/doc-models.php';

/**
 * Background Task: Retention Expiry Scanner
 * Runs daily to check for documents whose retention_expiry_date has passed
 * and updates their disposition_status to 'Pending Disposition'.
 */
function doc_task_retention_check() {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('documents');
    $today = date('Y-m-d');

    $stmt = $pdb->query("
        SELECT id, document_number, title FROM {$tb}
        WHERE retention_expiry_date <= ?
        AND disposition_status = 'Active'
        AND is_under_legal_hold = 0
    ", [$today]);

    $expired_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;

    foreach ($expired_docs as $doc) {
        $pdb->query("
            UPDATE {$tb}
            SET disposition_status = 'Pending Disposition'
            WHERE id = ?
        ", [(int)$doc['id']]);

        doc_audit_log('RETENTION_EXPIRY_TRIGGERED', 'document', $doc['id'], 'SUCCESS', [
            'document_number' => $doc['document_number'],
            'title' => $doc['title']
        ]);
        $count++;
    }

    echo "Retention Check Complete: {$count} document(s) marked Pending Disposition.\n";
}

/**
 * Background Task: Approaching Deadline & Overdue Action Alerts
 * Scans for lawful requests with deadlines approaching in <= 3 days or overdue post-mortem action items.
 */
function doc_task_deadline_alerts() {
    $pdb = doc_get_pdb();
    $tb_lwo = $pdb->getTableName('lawful_requests');
    $tb_act = $pdb->getTableName('post_mortem_actions');
    $today = date('Y-m-d');
    $warning_date = date('Y-m-d', strtotime('+3 days'));

    // Check Lawful Requests
    $lwos = $pdb->query("
        SELECT id, document_id, internal_request_number, response_deadline FROM {$tb_lwo}
        WHERE response_deadline IS NOT NULL
        AND response_deadline <= ?
        AND execution_status != 'Completed'
    ", [$warning_date])->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lwos as $l) {
        doc_send_notification(null, 'Lawful Request Deadline Approaching', "Request {$l['internal_request_number']} deadline: {$l['response_deadline']}", $l['document_id']);
    }

    // Check Overdue Post-Mortem Actions
    $overdue_acts = $pdb->query("
        SELECT id, document_id, action_identifier, assigned_to, due_date FROM {$tb_act}
        WHERE status != 'Completed'
        AND due_date IS NOT NULL
        AND due_date < ?
    ", [$today])->fetchAll(PDO::FETCH_ASSOC);

    foreach ($overdue_acts as $act) {
        doc_send_notification(null, 'Post-Mortem Action Overdue', "Action {$act['action_identifier']} assigned to {$act['assigned_to']} was due on {$act['due_date']}", $act['document_id']);
    }

    echo "Deadline Alerts Task Complete: Checked " . count($lwos) . " lawful request(s) and " . count($overdue_acts) . " overdue action(s).\n";
}
