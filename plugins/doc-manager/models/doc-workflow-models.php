<?php
// doc-workflow-models.php - Configurable Multi-Step Approval Workflows

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/doc-core-models.php';

function doc_initiate_approval_workflow($doc_id, $workflow_steps) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_approvals');

    $pdb->query("DELETE FROM {$tb} WHERE document_id = ?", [(int)$doc_id]);

    $step_num = 1;
    foreach ($workflow_steps as $step_name) {
        $pdb->query("
            INSERT INTO {$tb} (document_id, step_number, step_name, status)
            VALUES (?, ?, ?, ?)
        ", [(int)$doc_id, $step_num, $step_name, ($step_num === 1 ? 'Pending' : 'Scheduled')]);
        $step_num++;
    }

    $pdb->query("UPDATE " . $pdb->getTableName('documents') . " SET status = 'Under Review' WHERE id = ?", [(int)$doc_id]);
    doc_audit_log('WORKFLOW_INITIATED', 'document', $doc_id, 'SUCCESS', ['steps_count' => count($workflow_steps)]);
    doc_send_notification(null, 'Approval Workflow Initiated', "Document #{$doc_id} initiated for review.", $doc_id);
}

function doc_record_approval_decision($doc_id, $step_id, $decision, $comments = '') {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_approvals');
    $user_id = (int)($_SESSION['user_id'] ?? 1);
    $now = date('Y-m-d H:i:s');

    if ($decision === 'Approve') {
        $pdb->query("
            UPDATE {$tb}
            SET status = 'Approved', approver_user_id = ?, comments = ?, decided_at = ?
            WHERE id = ? AND document_id = ?
        ", [$user_id, $comments, $now, (int)$step_id, (int)$doc_id]);

        $current = $pdb->query("SELECT step_number FROM {$tb} WHERE id = ?", [(int)$step_id])->fetchColumn();
        $next_step = $pdb->query("SELECT id FROM {$tb} WHERE document_id = ? AND step_number = ?", [(int)$doc_id, ((int)$current + 1)])->fetch();

        if ($next_step) {
            $pdb->query("UPDATE {$tb} SET status = 'Pending' WHERE id = ?", [(int)$next_step['id']]);
            doc_send_notification(null, 'Workflow Advanced', "Document #{$doc_id} advanced to step " . ((int)$current + 1), $doc_id);
        } else {
            doc_update_document($doc_id, ['status' => 'Approved'], 'All workflow approval steps completed', true);
            doc_send_notification(null, 'Document Approved', "Document #{$doc_id} has been fully approved.", $doc_id);
        }
        doc_audit_log('APPROVAL_DECISION_APPROVED', 'document', $doc_id, 'SUCCESS', ['step_id' => $step_id, 'comments' => $comments]);
    } else if ($decision === 'Reject' || $decision === 'Request Changes') {
        $new_status = ($decision === 'Reject') ? 'Rejected' : 'Changes Requested';
        $pdb->query("
            UPDATE {$tb}
            SET status = ?, approver_user_id = ?, comments = ?, decided_at = ?
            WHERE id = ? AND document_id = ?
        ", [$new_status, $user_id, $comments, $now, (int)$step_id, (int)$doc_id]);

        doc_update_document($doc_id, ['status' => ($decision === 'Reject' ? 'Closed' : 'Changes Requested')], 'Workflow decision: ' . $decision, false);
        doc_audit_log('APPROVAL_DECISION_' . strtoupper(str_replace(' ', '_', $decision)), 'document', $doc_id, 'SUCCESS', ['step_id' => $step_id, 'comments' => $comments]);
        doc_send_notification(null, 'Workflow Decision: ' . $decision, "Document #{$doc_id} decision: {$decision}.", $doc_id);
    }
}

function doc_get_approvals($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_approvals');
    return $pdb->query("SELECT a.*, COALESCE(NULLIF(u.display_name, ''), u.username) as approver_name, u.email as approver_email FROM {$tb} a LEFT JOIN users u ON a.approver_user_id = u.id WHERE a.document_id = ? ORDER BY a.step_number ASC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}
