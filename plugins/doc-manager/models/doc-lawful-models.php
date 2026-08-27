<?php
// doc-lawful-models.php - Lawful Request Orders, Court Warrants & SHA-256 Chain of Custody

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/doc-core-models.php';

function doc_save_lawful_request($doc_id, $data) {
    if (!doc_can_access_lawful_requests()) {
        throw new Exception("Access Denied: You do not possess the required legal_request.access authorization.");
    }

    $doc = doc_get_document($doc_id);
    $doc_num = $doc ? $doc['document_number'] : ('LWO-' . date('Y') . '-000000');

    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('lawful_requests');

    $stmt = $pdb->query("SELECT id FROM {$tb} WHERE document_id = ?", [(int)$doc_id]);
    $exists = $stmt->fetchColumn();

    $fields = [
        'internal_request_number', 'request_type', 'requesting_organization', 'agency', 'officer_contact',
        'badge_or_id_number', 'contact_info', 'court_jurisdiction', 'court_file_number',
        'external_reference_number', 'date_received', 'received_by_user_id', 'method_received',
        'authority_cited', 'scope_of_request', 'requested_information', 'customer_account_reference',
        'target_identifiers', 'response_deadline', 'assigned_handler_user_id', 'approving_authority_user_id',
        'legal_review_status', 'execution_status', 'completion_date', 'disclosure_date',
        'disclosure_method', 'information_disclosed', 'notes'
    ];

    $params = [];
    foreach ($fields as $f) {
        $val = $data[$f] ?? null;
        if (is_string($val) && trim($val) === '') {
            $val = null;
        }
        $params[$f] = $val;
    }

    if (empty($params['internal_request_number'])) {
        $params['internal_request_number'] = $doc_num;
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

    doc_audit_log('LAWFUL_REQUEST_SAVED', 'document', $doc_id, 'SUCCESS', ['request_number' => $params['internal_request_number']]);
}

function doc_get_lawful_request($doc_id) {
    if (!doc_can_access_lawful_requests()) {
        return null;
    }
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('lawful_requests');
    $stmt = $pdb->query("SELECT * FROM {$tb} WHERE document_id = ?", [(int)$doc_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function doc_add_chain_of_custody($doc_id, $data) {
    if (!doc_can_access_lawful_requests()) {
        throw new Exception("Access Denied: Required legal_request.access authorization missing.");
    }
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('chain_of_custody');

    $activity_dt = $data['activity_datetime'] ?? date('Y-m-d H:i:s');
    $person = $data['person_name'] ?? (current_user()['name'] ?? 'Authorized User');
    $action = $data['action'] ?? 'Transfer';
    $source = $data['source'] ?? '';
    $destination = $data['destination'] ?? '';
    $desc = $data['description'] ?? '';
    $checksum = $data['file_checksum'] ?? '';
    $evidence_id = $data['evidence_identifier'] ?? '';
    $transfer_method = $data['method_of_transfer'] ?? '';
    $recipient = $data['recipient'] ?? '';
    $verification = $data['verification'] ?? 'Verified';

    $pdb->query("
        INSERT INTO {$tb} (document_id, activity_datetime, person_name, action, source, destination, description, file_checksum, evidence_identifier, method_of_transfer, recipient, verification)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [(int)$doc_id, $activity_dt, $person, $action, $source, $destination, $desc, $checksum, $evidence_id, $transfer_method, $recipient, $verification]);

    doc_audit_log('CHAIN_OF_CUSTODY_ADDED', 'document', $doc_id, 'SUCCESS', ['action' => $action, 'checksum' => $checksum]);
}

function doc_get_chain_of_custody($doc_id) {
    if (!doc_can_access_lawful_requests()) {
        return [];
    }
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('chain_of_custody');
    return $pdb->query("SELECT * FROM {$tb} WHERE document_id = ? ORDER BY activity_datetime ASC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}
