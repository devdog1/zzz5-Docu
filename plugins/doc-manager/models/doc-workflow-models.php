<?php
// doc-workflow-models.php - Configurable Multi-Authority Document Authorization Sign-offs

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/doc-core-models.php';

/**
 * Initiate or reset document approval workflow steps with assigned authorities.
 */
function doc_initiate_approval_workflow($doc_id, $workflow_steps) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_approvals');

    $pdb->query("DELETE FROM {$tb} WHERE document_id = ?", [(int)$doc_id]);

    $step_num = 1;
    foreach ($workflow_steps as $step) {
        $step_name = is_array($step) ? ($step['step_name'] ?? 'Sign-off Step ' . $step_num) : $step;
        $authorized_user_ids = is_array($step) && isset($step['authorized_user_ids']) ? (is_array($step['authorized_user_ids']) ? json_encode($step['authorized_user_ids']) : $step['authorized_user_ids']) : '[]';
        $required_role = is_array($step) ? ($step['required_role'] ?? null) : null;

        $pdb->query("
            INSERT INTO {$tb} (document_id, step_number, step_name, authorized_user_ids, required_role, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [(int)$doc_id, $step_num, $step_name, $authorized_user_ids, $required_role, ($step_num === 1 ? 'Pending' : 'Scheduled')]);
        $step_num++;
    }

    $pdb->query("UPDATE " . $pdb->getTableName('documents') . " SET status = 'Under Review' WHERE id = ?", [(int)$doc_id]);
    doc_audit_log('WORKFLOW_INITIATED', 'document', $doc_id, 'SUCCESS', ['steps_count' => count($workflow_steps)]);
    doc_send_notification(null, 'Approval Workflow Initiated', "Document #{$doc_id} initiated for multi-authority review.", $doc_id);
}

/**
 * Checks if current user is authorized to sign off on a specific workflow step.
 * Authorized if:
 * 1. User possesses admin/management privileges.
 * 2. User's ID is in the step's authorized_user_ids list.
 * 3. User possesses the step's required_role.
 */
function doc_can_user_sign_off_step($step) {
    if (!isset($_SESSION['user_id'])) return false;
    $user_id = (int)$_SESSION['user_id'];

    if (has_permission('doc_manager_manage_documents') || has_permission('manage_settings')) {
        return true;
    }

    if (!empty($step['authorized_user_ids'])) {
        $assigned = json_decode($step['authorized_user_ids'], true);
        if (is_array($assigned) && in_array($user_id, $assigned)) {
            return true;
        }
    }

    if (!empty($step['required_role']) && has_role($step['required_role'])) {
        return true;
    }

    // If no specific users or roles defined, default to general doc edit rights
    if (empty($step['authorized_user_ids']) && empty($step['required_role'])) {
        return has_permission('doc_manager_edit_documents') || has_permission('edit_documents');
    }

    return false;
}

/**
 * Record a sign-off approval decision on a workflow step.
 */
function doc_record_approval_decision($doc_id, $step_id, $decision, $comments = '') {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_approvals');
    $user_id = (int)($_SESSION['user_id'] ?? 1);
    $now = date('Y-m-d H:i:s');

    $step = $pdb->query("SELECT * FROM {$tb} WHERE id = ? AND document_id = ?", [(int)$step_id, (int)$doc_id])->fetch(PDO::FETCH_ASSOC);
    if (!$step) {
        throw new Exception("Workflow step not found.");
    }

    if (!doc_can_user_sign_off_step($step)) {
        throw new Exception("Access Denied: You are not an authorized sign-off authority for this workflow step.");
    }

    $sig_hash = hash('sha256', $doc_id . '-' . $step_id . '-' . $user_id . '-' . $now . '-' . $decision);

    if ($decision === 'Approve') {
        $pdb->query("
            UPDATE {$tb}
            SET status = 'Approved', approver_user_id = ?, comments = ?, signature_hash = ?, decided_at = ?
            WHERE id = ? AND document_id = ?
        ", [$user_id, $comments, $sig_hash, $now, (int)$step_id, (int)$doc_id]);

        $current = $step['step_number'];
        $next_step = $pdb->query("SELECT id FROM {$tb} WHERE document_id = ? AND step_number = ?", [(int)$doc_id, ((int)$current + 1)])->fetch();

        if ($next_step) {
            $pdb->query("UPDATE {$tb} SET status = 'Pending' WHERE id = ?", [(int)$next_step['id']]);
            doc_send_notification(null, 'Workflow Advanced', "Document #{$doc_id} advanced to sign-off step " . ((int)$current + 1), $doc_id);
        } else {
            doc_update_document($doc_id, ['status' => 'Approved'], 'All workflow authorization sign-offs completed', true);
            doc_send_notification(null, 'Document Fully Authorized', "Document #{$doc_id} has completed all authorization sign-offs.", $doc_id);
        }
        doc_audit_log('AUTHORIZATION_SIGN_OFF_APPROVED', 'document', $doc_id, 'SUCCESS', ['step_id' => $step_id, 'sig_hash' => $sig_hash, 'comments' => $comments]);
    } else if ($decision === 'Reject' || $decision === 'Request Changes') {
        $new_status = ($decision === 'Reject') ? 'Rejected' : 'Changes Requested';
        $pdb->query("
            UPDATE {$tb}
            SET status = ?, approver_user_id = ?, comments = ?, signature_hash = ?, decided_at = ?
            WHERE id = ? AND document_id = ?
        ", [$new_status, $user_id, $comments, $sig_hash, $now, (int)$step_id, (int)$doc_id]);

        doc_update_document($doc_id, ['status' => ($decision === 'Reject' ? 'Closed' : 'Changes Requested')], 'Authorization decision: ' . $decision, false);
        doc_audit_log('AUTHORIZATION_DECISION_' . strtoupper(str_replace(' ', '_', $decision)), 'document', $doc_id, 'SUCCESS', ['step_id' => $step_id, 'comments' => $comments]);
        doc_send_notification(null, 'Workflow Decision: ' . $decision, "Document #{$doc_id} authorization decision: {$decision}.", $doc_id);
    }
}

function doc_get_approvals($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_approvals');
    return $pdb->query("
        SELECT a.*, COALESCE(NULLIF(u.display_name, ''), u.username) as approver_name, u.email as approver_email
        FROM {$tb} a
        LEFT JOIN users u ON a.approver_user_id = u.id
        WHERE a.document_id = ?
        ORDER BY a.step_number ASC
    ", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}
