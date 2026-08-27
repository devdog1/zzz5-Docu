<?php
// doc-rfo-models.php - RFO / Incident Report Details & Timeline Management

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/doc-core-models.php';

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
