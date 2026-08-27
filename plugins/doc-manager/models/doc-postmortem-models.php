<?php
// doc-postmortem-models.php - Post-Mortem Reviews & Trackable Action Item Management

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/doc-core-models.php';

function doc_save_post_mortem_details($doc_id, $data) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('post_mortem_details');

    $stmt = $pdb->query("SELECT id FROM {$tb} WHERE document_id = ?", [(int)$doc_id]);
    $exists = $stmt->fetchColumn();

    $fields = [
        'executive_summary', 'incident_overview', 'business_impact', 'technical_impact',
        'timeline', 'what_happened', 'root_cause', 'contributing_factors', 'detection_analysis',
        'response_analysis', 'recovery_analysis', 'what_went_well', 'what_did_not_go_well',
        'lessons_learned', 'corrective_actions', 'preventative_actions', 'follow_up_work'
    ];

    $params = [];
    foreach ($fields as $f) {
        $val = $data[$f] ?? null;
        if (is_string($val) && trim($val) === '') {
            $val = null;
        }
        $params[$f] = $val;
    }

    if ($exists) {
        $set_sql = implode(', ', array_map(fn($f) => "{$f} = ?", $fields));
        $sql_params = array_values($params);
        $sql_params[] = (int)$doc_id;
        $pdb->query("UPDATE {$tb} SET {$set_sql} WHERE document_id = ?", $sql_params);
    } else {
        $cols_sql = 'document_id, ' . implode(', ', $fields);
        $placeholders = '?, ' . implode(', ', array_fill(0, count($fields), '?'));
        $sql_params = array_merge([(int)$doc_id], array_values($params));
        $pdb->query("INSERT INTO {$tb} ({$cols_sql}) VALUES ({$placeholders})", $sql_params);
    }
}

function doc_get_post_mortem_details($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('post_mortem_details');
    $stmt = $pdb->query("SELECT * FROM {$tb} WHERE document_id = ?", [(int)$doc_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function doc_add_post_mortem_action($doc_id, $data) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('post_mortem_actions');

    $action_identifier = 'ACT-' . strtoupper(substr(md5(uniqid()), 0, 6));
    $desc = $data['description'] ?? '';
    $assigned_to = $data['assigned_to'] ?? '';
    $priority = $data['priority'] ?? 'Medium';
    $status = $data['status'] ?? 'Open';
    $due_date = !empty($data['due_date']) ? $data['due_date'] : null;
    $completion_date = !empty($data['completion_date']) ? $data['completion_date'] : null;
    $evidence = $data['evidence'] ?? '';
    $notes = $data['notes'] ?? '';

    $pdb->query("
        INSERT INTO {$tb} (document_id, action_identifier, description, assigned_to, priority, status, due_date, completion_date, evidence, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [(int)$doc_id, $action_identifier, $desc, $assigned_to, $priority, $status, $due_date, $completion_date, $evidence, $notes]);

    doc_audit_log('POST_MORTEM_ACTION_CREATED', 'document', $doc_id, 'SUCCESS', ['action_id' => $action_identifier, 'assigned' => $assigned_to]);
}

function doc_get_post_mortem_actions($doc_id = null) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('post_mortem_actions');
    if ($doc_id) {
        return $pdb->query("SELECT * FROM {$tb} WHERE document_id = ? ORDER BY id DESC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
    }
    return $pdb->query("SELECT a.*, d.document_number, d.title as document_title FROM {$tb} a LEFT JOIN " . $pdb->getTableName('documents') . " d ON a.document_id = d.id ORDER BY a.id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function doc_update_post_mortem_action($action_id, $status, $completion_date = null, $evidence = null, $verification = null) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('post_mortem_actions');
    $pdb->query("
        UPDATE {$tb}
        SET status = ?, completion_date = ?, evidence = COALESCE(?, evidence), verification_status = COALESCE(?, verification_status)
        WHERE id = ?
    ", [$status, $completion_date, $evidence, $verification, (int)$action_id]);
}
