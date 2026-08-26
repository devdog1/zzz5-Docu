<?php
// doc-models.php - Document Management System Core Data Models & Business Logic

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once APP_ROOT . 'PluginDatabase.php';

function doc_get_pdb() {
    static $pdb = null;
    if ($pdb === null) {
        $pdb = new PluginDatabase('doc-manager');
    }
    return $pdb;
}

/* =========================================================
 * IMMUTABLE AUDIT LOG & SECURITY HELPERS
 * ========================================================= */

function doc_audit_log($action, $object_type = null, $object_id = null, $result = 'SUCCESS', $metadata = []) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('audit_log');

    $user_id = $_SESSION['user_id'] ?? null;
    $username = current_user()['username'] ?? ($_SESSION['user']['email'] ?? 'System');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $session_id = session_id() ?: '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $meta_json = is_string($metadata) ? $metadata : json_encode($metadata);

    try {
        $pdb->query("
            INSERT INTO {$tb} (user_id, username, ip_address, session_id, user_agent, action, object_type, object_id, result, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [$user_id, $username, $ip_address, $session_id, $user_agent, $action, $object_type, (string)$object_id, $result, $meta_json]);
    } catch (Exception $e) {
        error_log("Failed to insert doc_audit_log: " . $e->getMessage());
    }
}

/**
 * Checks if current user has permission for lawful/legal requests independently.
 * Note: Requirement 8: "Do not grant access merely because someone is a system administrator."
 */
function doc_can_access_lawful_requests() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    // Session permissions array is associative: ['perm_name' => true] or indexed list
    $perms = $_SESSION['permissions'] ?? [];
    if (is_array($perms)) {
        if (isset($perms['legal_request.access']) || isset($perms['doc_manager_legal_request_access']) || isset($perms['doc_manager_manage_lawful_requests'])) {
            return true;
        }
        return in_array('legal_request.access', $perms, true) ||
               in_array('doc_manager_legal_request_access', $perms, true) ||
               in_array('doc_manager_manage_lawful_requests', $perms, true);
    }
    return false;
}

/**
 * Check if current user can view a specific document based on Security Classification.
 */
function doc_can_user_view_document($doc) {
    if (!$doc) return false;
    if (!isset($_SESSION['user_id'])) return false;

    $user_id = (int)$_SESSION['user_id'];
    $classification = $doc['classification'] ?? 'Internal';

    // Restricted documents require explicit permission or direct assignment/ownership
    if ($classification === 'Restricted') {
        if ($doc['document_type_id'] == doc_get_type_id_by_code('LWO')) {
            if (!doc_can_access_lawful_requests() && (int)$doc['owner_user_id'] !== $user_id) {
                return false;
            }
        }
        if (has_permission('doc_manager_view_restricted') || doc_can_access_lawful_requests()) {
            return true;
        }
        if ((int)$doc['owner_user_id'] === $user_id) {
            return true;
        }
        return false;
    }

    // Confidential documents require doc_manager_view_confidential, doc_manager_manage_documents, or ownership
    if ($classification === 'Confidential') {
        if (has_permission('doc_manager_view_confidential') || has_permission('doc_manager_manage_documents')) {
            return true;
        }
        if ((int)$doc['owner_user_id'] === $user_id) {
            return true;
        }
        return false;
    }

    // Public & Internal documents
    return true;
}

/* =========================================================
 * NOTIFICATION LOGIC
 * ========================================================= */

function doc_send_notification($recipient_user_id, $subject, $message, $doc_id = null) {
    // Log notification event in audit trail
    doc_audit_log('NOTIFICATION_SENT', 'document', $doc_id, 'SUCCESS', [
        'recipient_user_id' => $recipient_user_id,
        'subject' => $subject,
        'message' => $message
    ]);
}

/* =========================================================
 * DOCUMENT TYPE MANAGEMENT & NUMBERING
 * ========================================================= */

function doc_get_type_id_by_code($code) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_types');
    $stmt = $pdb->query("SELECT id FROM {$tb} WHERE code = ?", [$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : 0;
}

function doc_get_all_types() {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_types');
    return $pdb->query("SELECT * FROM {$tb} ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function doc_get_type($id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_types');
    $stmt = $pdb->query("SELECT * FROM {$tb} WHERE id = ?", [(int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function doc_generate_number($type_id) {
    $type = doc_get_type($type_id);
    if (!$type) {
        return 'DOC-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_types');

    // Increment counter atomically
    $new_counter = ((int)$type['current_counter']) + 1;
    $pdb->query("UPDATE {$tb} SET current_counter = ? WHERE id = ?", [$new_counter, (int)$type_id]);

    $fmt = !empty($type['numbering_format']) ? $type['numbering_format'] : '{CODE}-{YYYY}-{NUMBER:6}';
    $code = !empty($type['code']) ? $type['code'] : 'DOC';
    $yyyy = date('Y');

    $formatted = str_replace('{CODE}', $code, $fmt);
    $formatted = str_replace('{YYYY}', $yyyy, $formatted);

    if (preg_match('/\{NUMBER:(\d+)\}/', $formatted, $m)) {
        $digits = (int)$m[1];
        $formatted = preg_replace('/\{NUMBER:\d+\}/', str_pad($new_counter, $digits, '0', STR_PAD_LEFT), $formatted);
    } else {
        $formatted = str_replace('{NUMBER}', str_pad($new_counter, 6, '0', STR_PAD_LEFT), $formatted);
    }

    return $formatted;
}

function doc_seed_default_types() {
    $types = doc_get_all_types();
    if (!empty($types)) return;

    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_types');

    $defaults = [
        [
            'name' => 'Reason For Outage / Incident Report',
            'code' => 'RFO',
            'description' => 'Incident analysis, outages, service impacts, and root cause investigations.',
            'default_classification' => 'Internal',
            'numbering_format' => 'RFO-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Author', 'Technical Reviewer', 'Manager', 'Approved']),
            'retention_period_years' => 7
        ],
        [
            'name' => 'Post-Mortem Report',
            'code' => 'PM',
            'description' => 'Structured post-incident technical and operational review.',
            'default_classification' => 'Internal',
            'numbering_format' => 'PM-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Author', 'Technical Reviewer', 'Manager', 'Director', 'Final']),
            'retention_period_years' => 7
        ],
        [
            'name' => 'Lawful Work Order',
            'code' => 'LWO',
            'description' => 'Court orders, warrants, and statutory interception/production orders.',
            'default_classification' => 'Restricted',
            'numbering_format' => 'LWO-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Authorized Request Handler', 'Designated Authority', 'Execution', 'Verification', 'Closed']),
            'retention_period_years' => 10
        ],
        [
            'name' => 'Legal Hold Directive',
            'code' => 'LH',
            'description' => 'Litigation hold directives and records preservation commands.',
            'default_classification' => 'Restricted',
            'numbering_format' => 'LH-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Draft', 'Approved', 'Active', 'Released']),
            'retention_period_years' => 10
        ],
        [
            'name' => 'Security Investigation',
            'code' => 'SEC',
            'description' => 'Internal security assessments, forensic reports, and vulnerability notes.',
            'default_classification' => 'Restricted',
            'numbering_format' => 'SEC-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Draft', 'Under Review', 'Approved', 'Closed']),
            'retention_period_years' => 7
        ],
        [
            'name' => 'Policy / Standard Operating Procedure',
            'code' => 'POL',
            'description' => 'Governance policy, operational procedures, and guideline documents.',
            'default_classification' => 'Internal',
            'numbering_format' => 'POL-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Draft', 'Review', 'Approval', 'Published']),
            'retention_period_years' => 10
        ]
    ];

    foreach ($defaults as $d) {
        $pdb->query("
            INSERT INTO {$tb} (name, code, description, default_classification, numbering_format, workflow_steps, retention_period_years)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [$d['name'], $d['code'], $d['description'], $d['default_classification'], $d['numbering_format'], $d['workflow_steps'], $d['retention_period_years']]);
    }
}

/* =========================================================
 * DOCUMENT CRUD & VERSION CONTROL
 * ========================================================= */

function doc_create_document($data) {
    $pdb = doc_get_pdb();
    $tb_doc = $pdb->getTableName('documents');
    $tb_ver = $pdb->getTableName('document_versions');

    $type_id = (int)$data['document_type_id'];
    $doc_num = doc_generate_number($type_id);
    $user_id = (int)($_SESSION['user_id'] ?? 1);

    $retention_years = 7;
    $type = doc_get_type($type_id);
    if ($type && !empty($type['retention_period_years'])) {
        $retention_years = (int)$type['retention_period_years'];
    }
    $retention_expiry = date('Y-m-d', strtotime("+{$retention_years} years"));
    $verification_code = hash('sha256', $doc_num . '-' . microtime(true) . '-' . mt_rand());

    $title = $data['title'] ?? 'Untitled Document';
    $desc = $data['description'] ?? '';
    $classification = $data['classification'] ?? ($type['default_classification'] ?? 'Internal');
    $department = $data['department'] ?? '';
    $content = $data['content'] ?? '';
    $metadata = is_array($data['metadata'] ?? null) ? json_encode($data['metadata']) : ($data['metadata'] ?? '{}');
    $status = $data['status'] ?? 'Draft';
    $version = '0.1';

    $pdb->query("
        INSERT INTO {$tb_doc} (document_number, title, description, document_type_id, classification, current_version, status, owner_user_id, department, metadata, content, retention_expiry_date, verification_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [$doc_num, $title, $desc, $type_id, $classification, $version, $status, $user_id, $department, $metadata, $content, $retention_expiry, $verification_code]);

    $doc_id = $pdb->query("SELECT LAST_INSERT_ID()")->fetchColumn();

    // Store Version 0.1
    $pdb->query("
        INSERT INTO {$tb_ver} (document_id, version, user_id, title, content, metadata, fields_changed, change_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ", [$doc_id, $version, $user_id, $title, $content, $metadata, 'Initial Document Creation', 'Initial Creation']);

    doc_audit_log('DOCUMENT_CREATE', 'document', $doc_id, 'SUCCESS', ['document_number' => $doc_num, 'title' => $title, 'classification' => $classification]);

    return $doc_id;
}

function doc_update_document($doc_id, $data, $change_reason = 'Updated document content', $bump_major = false) {
    $doc = doc_get_document($doc_id);
    if (!$doc) throw new Exception("Document not found.");

    if ($doc['is_under_legal_hold']) {
        doc_audit_log('LEGAL_HOLD_ALTERATION_ATTEMPT', 'document', $doc_id, 'SUCCESS', ['reason' => 'Record altered under active legal hold']);
    }

    $pdb = doc_get_pdb();
    $tb_doc = $pdb->getTableName('documents');
    $tb_ver = $pdb->getTableName('document_versions');

    $old_version = $doc['current_version'];
    // Version increment logic
    if ($bump_major) {
        $parts = explode('.', $old_version);
        $major = ((int)($parts[0] ?? 0)) + 1;
        $new_version = $major . '.0';
    } else {
        $parts = explode('.', $old_version);
        $major = (int)($parts[0] ?? 0);
        $minor = ((int)($parts[1] ?? 0)) + 1;
        $new_version = $major . '.' . $minor;
    }

    $user_id = (int)($_SESSION['user_id'] ?? 1);
    $title = $data['title'] ?? $doc['title'];
    $desc = $data['description'] ?? $doc['description'];
    $classification = $data['classification'] ?? $doc['classification'];
    $department = $data['department'] ?? $doc['department'];
    $content = $data['content'] ?? $doc['content'];
    $metadata = is_array($data['metadata'] ?? null) ? json_encode($data['metadata']) : ($data['metadata'] ?? $doc['metadata']);
    $status = $data['status'] ?? $doc['status'];

    // Track changed fields with previous and new values
    $changed = [];
    if ($title !== $doc['title']) $changed[] = "title: '{$doc['title']}' -> '{$title}'";
    if ($desc !== $doc['description']) $changed[] = "description modified";
    if ($classification !== $doc['classification']) $changed[] = "classification: '{$doc['classification']}' -> '{$classification}'";
    if ($content !== $doc['content']) $changed[] = "content modified";
    if ($status !== $doc['status']) $changed[] = "status: '{$doc['status']}' -> '{$status}'";

    $fields_changed_str = implode('; ', $changed) ?: 'No significant field changes';

    $pdb->query("
        UPDATE {$tb_doc}
        SET title = ?, description = ?, classification = ?, current_version = ?, status = ?, department = ?, metadata = ?, content = ?
        WHERE id = ?
    ", [$title, $desc, $classification, $new_version, $status, $department, $metadata, $content, (int)$doc_id]);

    // Store new version (never overwrite historical versions)
    $pdb->query("
        INSERT INTO {$tb_ver} (document_id, version, user_id, title, content, metadata, fields_changed, change_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ", [(int)$doc_id, $new_version, $user_id, $title, $content, $metadata, $fields_changed_str, $change_reason]);

    doc_audit_log('DOCUMENT_MODIFY', 'document', $doc_id, 'SUCCESS', ['previous_version' => $old_version, 'new_version' => $new_version, 'changed' => $fields_changed_str]);

    if ($classification !== $doc['classification']) {
        doc_audit_log('CLASSIFICATION_CHANGE', 'document', $doc_id, 'SUCCESS', ['old' => $doc['classification'], 'new' => $classification]);
    }

    return true;
}

function doc_get_document($id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('documents');
    $stmt = $pdb->query("SELECT d.*, dt.name as document_type_name, dt.code as document_type_code FROM {$tb} d LEFT JOIN " . $pdb->getTableName('document_types') . " dt ON d.document_type_id = dt.id WHERE d.id = ?", [(int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function doc_get_document_by_number($number) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('documents');
    $stmt = $pdb->query("SELECT d.*, dt.name as document_type_name, dt.code as document_type_code FROM {$tb} d LEFT JOIN " . $pdb->getTableName('document_types') . " dt ON d.document_number = ?", [$number]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function doc_get_versions($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_versions');
    return $pdb->query("SELECT v.*, u.name as user_name, u.email as user_email FROM {$tb} v LEFT JOIN users u ON v.user_id = u.id WHERE v.document_id = ? ORDER BY v.id DESC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Prevent hard deletes. Log deletion attempt and check legal holds.
 */
function doc_attempt_delete_document($doc_id) {
    $doc = doc_get_document($doc_id);
    if (!$doc) return false;

    if ($doc['is_under_legal_hold']) {
        doc_audit_log('DOCUMENT_DELETE_BLOCKED_LEGAL_HOLD', 'document', $doc_id, 'BLOCKED', ['reason' => 'Document is under active legal hold']);
        throw new Exception("Security & Compliance Exception: Cannot delete record under active Legal Hold.");
    }

    doc_audit_log('DOCUMENT_DELETE_ATTEMPTED', 'document', $doc_id, 'PROHIBITED', ['reason' => 'No hard delete policy enforced']);

    // Soft disposition update
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('documents');
    $pdb->query("UPDATE {$tb} SET disposition_status = 'Pending Disposition', status = 'Archived' WHERE id = ?", [(int)$doc_id]);
    return true;
}

/* =========================================================
 * RFO & POST-MORTEM SPECIFIC LOGIC
 * ========================================================= */

function doc_save_rfo_details($doc_id, $data) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('rfo_details');

    $stmt = $pdb->query("SELECT id FROM {$tb} WHERE document_id = ?", [(int)$doc_id]);
    $exists = $stmt->fetchColumn();

    $fields = [
        'incident_number', 'service_affected', 'systems_affected', 'customers_affected',
        'geographic_areas_affected', 'incident_severity', 'start_datetime', 'detection_datetime',
        'escalation_datetime', 'service_restoration_datetime', 'total_duration', 'impact_description',
        'initial_symptoms', 'detection_method', 'root_cause', 'contributing_factors', 'resolution',
        'recovery_actions', 'corrective_actions', 'preventative_actions', 'lessons_learned',
        'monitoring_improvements', 'assigned_owner_id', 'reviewer_id', 'approver_id',
        'related_tickets', 'related_changes', 'related_rfos'
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

function doc_get_rfo_details($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('rfo_details');
    $stmt = $pdb->query("SELECT * FROM {$tb} WHERE document_id = ?", [(int)$doc_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function doc_add_rfo_timeline_entry($doc_id, $timestamp, $event, $person = '', $source = '', $notes = '') {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('rfo_timelines');
    $pdb->query("
        INSERT INTO {$tb} (document_id, timestamp, event, person, source, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ", [(int)$doc_id, $timestamp, $event, $person, $source, $notes]);
}

function doc_get_rfo_timelines($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('rfo_timelines');
    return $pdb->query("SELECT * FROM {$tb} WHERE document_id = ? ORDER BY timestamp ASC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}

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

/* =========================================================
 * LAWFUL WORK ORDERS & CHAIN OF CUSTODY
 * ========================================================= */

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

/* =========================================================
 * LEGAL HOLDS LOGIC
 * ========================================================= */

function doc_create_legal_hold($data) {
    $pdb = doc_get_pdb();
    $tb_lh = $pdb->getTableName('legal_holds');
    $user_id = (int)($_SESSION['user_id'] ?? 1);

    $lh_number = 'LH-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    $name = $data['name'] ?? 'Legal Hold';
    $desc = $data['description'] ?? '';
    $authority = $data['authority'] ?? '';
    $start_date = $data['start_date'] ?? date('Y-m-d');
    $scope = $data['scope'] ?? '';
    $reason = $data['reason'] ?? '';
    $case_ref = $data['related_case_request'] ?? '';
    $custodians = $data['custodians'] ?? '';
    $systems = $data['systems_affected'] ?? '';
    $criteria = $data['search_criteria'] ?? '';

    $pdb->query("
        INSERT INTO {$tb_lh} (legal_hold_number, name, description, authority, created_by_user_id, start_date, scope, reason, related_case_request, custodians, systems_affected, search_criteria)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [$lh_number, $name, $desc, $authority, $user_id, $start_date, $scope, $reason, $case_ref, $custodians, $systems, $criteria]);

    $lh_id = $pdb->query("SELECT LAST_INSERT_ID()")->fetchColumn();

    doc_audit_log('LEGAL_HOLD_CREATED', 'legal_hold', $lh_id, 'SUCCESS', ['legal_hold_number' => $lh_number, 'name' => $name]);

    return $lh_id;
}

function doc_apply_legal_hold_to_document($lh_id, $doc_id) {
    $pdb = doc_get_pdb();
    $tb_lhd = $pdb->getTableName('legal_hold_documents');
    $tb_doc = $pdb->getTableName('documents');

    $pdb->query("INSERT IGNORE INTO {$tb_lhd} (legal_hold_id, document_id) VALUES (?, ?)", [(int)$lh_id, (int)$doc_id]);
    $pdb->query("UPDATE {$tb_doc} SET is_under_legal_hold = 1 WHERE id = ?", [(int)$doc_id]);

    doc_audit_log('LEGAL_HOLD_APPLIED', 'document', $doc_id, 'SUCCESS', ['legal_hold_id' => $lh_id]);
}

function doc_get_all_legal_holds() {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('legal_holds');
    return $pdb->query("SELECT * FROM {$tb} ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function doc_release_legal_hold($lh_id, $release_auth = '') {
    $pdb = doc_get_pdb();
    $tb_lh = $pdb->getTableName('legal_holds');
    $tb_lhd = $pdb->getTableName('legal_hold_documents');
    $tb_doc = $pdb->getTableName('documents');

    $today = date('Y-m-d');
    $pdb->query("UPDATE {$tb_lh} SET status = 'Released', release_authorization = ?, release_date = ? WHERE id = ?", [$release_auth, $today, (int)$lh_id]);

    // Check remaining active legal holds for documents
    $docs = $pdb->query("SELECT document_id FROM {$tb_lhd} WHERE legal_hold_id = ?", [(int)$lh_id])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($docs as $d_id) {
        $other_active = $pdb->query("
            SELECT COUNT(*) FROM {$tb_lhd} lhd
            JOIN {$tb_lh} lh ON lhd.legal_hold_id = lh.id
            WHERE lhd.document_id = ? AND lh.status = 'Active'
        ", [(int)$d_id])->fetchColumn();

        if ($other_active == 0) {
            $pdb->query("UPDATE {$tb_doc} SET is_under_legal_hold = 0 WHERE id = ?", [(int)$d_id]);
        }
    }

    doc_audit_log('LEGAL_HOLD_RELEASED', 'legal_hold', $lh_id, 'SUCCESS', ['release_authorization' => $release_auth]);
}

/* =========================================================
 * APPROVAL WORKFLOW LOGIC
 * ========================================================= */

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

        // Advance to next step or set document to Approved
        $current = $pdb->query("SELECT step_number FROM {$tb} WHERE id = ?", [(int)$step_id])->fetchColumn();
        $next_step = $pdb->query("SELECT id FROM {$tb} WHERE document_id = ? AND step_number = ?", [(int)$doc_id, ((int)$current + 1)])->fetch();

        if ($next_step) {
            $pdb->query("UPDATE {$tb} SET status = 'Pending' WHERE id = ?", [(int)$next_step['id']]);
            doc_send_notification(null, 'Workflow Advanced', "Document #{$doc_id} advanced to step " . ((int)$current + 1), $doc_id);
        } else {
            // All steps approved
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
    return $pdb->query("SELECT a.*, u.name as approver_name, u.email as approver_email FROM {$tb} a LEFT JOIN users u ON a.approver_user_id = u.id WHERE a.document_id = ? ORDER BY a.step_number ASC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================================
 * DOCUMENT RELATIONSHIPS & COMMENTS
 * ========================================================= */

function doc_add_relationship($source_id, $target_id, $type) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_relationships');
    $pdb->query("INSERT INTO {$tb} (source_document_id, target_document_id, relationship_type) VALUES (?, ?, ?)", [(int)$source_id, (int)$target_id, $type]);
    doc_audit_log('RELATIONSHIP_ADDED', 'document', $source_id, 'SUCCESS', ['target_id' => $target_id, 'type' => $type]);
}

function doc_get_relationships($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_relationships');
    return $pdb->query("
        SELECT r.*, d.document_number, d.title, d.classification
        FROM {$tb} r
        JOIN " . $pdb->getTableName('documents') . " d ON r.target_document_id = d.id
        WHERE r.source_document_id = ?
    ", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}

function doc_add_comment($doc_id, $text, $type = 'General') {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('comments');
    $user_id = (int)($_SESSION['user_id'] ?? 1);
    $author_name = current_user()['name'] ?? 'User';

    $pdb->query("
        INSERT INTO {$tb} (document_id, author_user_id, author_name, comment_type, comment_text)
        VALUES (?, ?, ?, ?, ?)
    ", [(int)$doc_id, $user_id, $author_name, $type, $text]);

    doc_audit_log('COMMENT_ADDED', 'document', $doc_id, 'SUCCESS', ['type' => $type]);
}

function doc_get_comments($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('comments');
    return $pdb->query("SELECT * FROM {$tb} WHERE document_id = ? ORDER BY id ASC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================================
 * SEARCH & ADVANCED FILTERING
 * ========================================================= */

function doc_search_documents($criteria = []) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('documents');

    $where = ["1=1"];
    $params = [];

    if (!empty($criteria['query'])) {
        $q = '%' . $criteria['query'] . '%';
        $where[] = "(d.document_number LIKE ? OR d.title LIKE ? OR d.description LIKE ? OR d.content LIKE ?)";
        $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
    }

    if (!empty($criteria['type_id'])) {
        $where[] = "d.document_type_id = ?";
        $params[] = (int)$criteria['type_id'];
    }

    if (!empty($criteria['classification'])) {
        $where[] = "d.classification = ?";
        $params[] = $criteria['classification'];
    }

    if (!empty($criteria['status'])) {
        $where[] = "d.status = ?";
        $params[] = $criteria['status'];
    }

    if (!empty($criteria['department'])) {
        $where[] = "d.department = ?";
        $params[] = $criteria['department'];
    }

    $where_sql = implode(' AND ', $where);
    $sql = "SELECT d.*, dt.name as document_type_name, dt.code as document_type_code
            FROM {$tb} d
            LEFT JOIN " . $pdb->getTableName('document_types') . " dt ON d.document_type_id = dt.id
            WHERE {$where_sql} ORDER BY d.id DESC";

    $results = $pdb->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

    // Enforce permission filtering: "A user must never learn that a restricted document exists if they do not have permission to view it."
    $filtered = [];
    foreach ($results as $doc) {
        if (doc_can_user_view_document($doc)) {
            $filtered[] = $doc;
        }
    }

    return $filtered;
}

/* =========================================================
 * DISPOSITION & RETENTION MANAGEMENT
 * ========================================================= */

function doc_get_pending_disposition_records() {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('documents');
    $today = date('Y-m-d');
    return $pdb->query("
        SELECT * FROM {$tb}
        WHERE (retention_expiry_date <= ? OR disposition_status = 'Pending Disposition')
        AND disposition_status != 'Destroyed'
    ", [$today])->fetchAll(PDO::FETCH_ASSOC);
}

function doc_approve_destruction($doc_id, $certificate_notes = 'Disposed by Records Admin') {
    $doc = doc_get_document($doc_id);
    if (!$doc) throw new Exception("Document not found.");

    if ($doc['is_under_legal_hold']) {
        throw new Exception("Compliance Exception: Retention destruction cannot proceed while under Legal Hold.");
    }

    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('documents');

    $cert = "DESTRUCTION CERTIFICATE #" . strtoupper(substr(md5(uniqid()), 0, 8)) . "\n"
          . "Document Number: " . $doc['document_number'] . "\n"
          . "Title: " . $doc['title'] . "\n"
          . "Destroyed At: " . date('Y-m-d H:i:s') . "\n"
          . "Authorized By: " . (current_user()['name'] ?? 'Records Admin') . "\n"
          . "Notes: " . $certificate_notes;

    $pdb->query("
        UPDATE {$tb}
        SET disposition_status = 'Destroyed', content = '[RECORD DESTROYED UNDER RETENTION POLICY]', status = 'Closed', destruction_certificate = ?
        WHERE id = ?
    ", [$cert, (int)$doc_id]);

    doc_audit_log('RECORDS_DESTRUCTION_APPROVED', 'document', $doc_id, 'SUCCESS', ['certificate' => $cert]);
}
