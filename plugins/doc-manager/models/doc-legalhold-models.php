<?php
// doc-legalhold-models.php - Legal Hold Directives & Litigation Preservation Freeze Logic

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/doc-core-models.php';

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
