<?php
// doc-retention-models.php - Retention Expiry Queue & Permanent Destruction Certificate Management

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/doc-core-models.php';

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
