<?php
// doc-crud-models.php - Core Document CRUD, Immutable Version Control, Relationships & Comments

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/doc-core-models.php';
require_once __DIR__ . '/doc-types-models.php';

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
    if (empty(trim($content)) && $type && !empty($type['template'])) {
        $content = doc_render_template($type['template'], [
            'document_number' => $doc_num,
            'title' => $title,
            'classification' => $classification,
            'department' => $department,
            'date' => date('F d, Y'),
            'owner' => current_user()['name'] ?? 'System User'
        ]);
    }

    $metadata = is_array($data['metadata'] ?? null) ? json_encode($data['metadata']) : ($data['metadata'] ?? '{}');
    $status = $data['status'] ?? 'Draft';
    $version = '0.1';

    $pdb->query("
        INSERT INTO {$tb_doc} (document_number, title, description, document_type_id, classification, current_version, status, owner_user_id, department, metadata, content, retention_expiry_date, verification_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [$doc_num, $title, $desc, $type_id, $classification, $version, $status, $user_id, $department, $metadata, $content, $retention_expiry, $verification_code]);

    $doc_id = $pdb->query("SELECT LAST_INSERT_ID()")->fetchColumn();

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
    $stmt = $pdb->query("SELECT d.*, dt.name as document_type_name, dt.code as document_type_code FROM {$tb} d LEFT JOIN " . $pdb->getTableName('document_types') . " dt ON d.document_type_id = dt.id WHERE d.document_number = ?", [$number]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function doc_get_versions($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_versions');
    return $pdb->query("SELECT v.*, u.name as user_name, u.email as user_email FROM {$tb} v LEFT JOIN users u ON v.user_id = u.id WHERE v.document_id = ? ORDER BY v.id DESC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}

function doc_attempt_delete_document($doc_id) {
    $doc = doc_get_document($doc_id);
    if (!$doc) return false;

    if ($doc['is_under_legal_hold']) {
        doc_audit_log('DOCUMENT_DELETE_BLOCKED_LEGAL_HOLD', 'document', $doc_id, 'BLOCKED', ['reason' => 'Document is under active legal hold']);
        throw new Exception("Security & Compliance Exception: Cannot delete record under active Legal Hold.");
    }

    doc_audit_log('DOCUMENT_DELETE_ATTEMPTED', 'document', $doc_id, 'PROHIBITED', ['reason' => 'No hard delete policy enforced']);

    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('documents');
    $pdb->query("UPDATE {$tb} SET disposition_status = 'Pending Disposition', status = 'Archived' WHERE id = ?", [(int)$doc_id]);
    return true;
}

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

    $filtered = [];
    foreach ($results as $doc) {
        if (doc_can_user_view_document($doc)) {
            $filtered[] = $doc;
        }
    }

    return $filtered;
}
