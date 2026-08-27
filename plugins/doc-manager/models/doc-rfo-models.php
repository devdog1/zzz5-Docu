<?php
// doc-rfo-models.php - RFO / Incident Report Details & Timeline Management

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/doc-core-models.php';

/**
 * Programmatically create an RFO / Incident Report document and save its details.
 * Useful for external plugins (e.g., Incident Management, Monitoring, Ticketing) to initiate RFOs.
 *
 * @param array $data Contains title, incident_number, service_affected, systems_affected, impact_description, timeline_entries, etc.
 * @return int Created document ID
 */
function doc_create_rfo_report($data) {
    $rfo_type_id = doc_get_type_id_by_code('RFO');
    $title = $data['title'] ?? 'Incident Report: ' . ($data['service_affected'] ?? 'Outage Event');

    $doc_id = doc_create_document([
        'document_type_id' => $rfo_type_id,
        'title'           => $title,
        'description'     => $data['impact_description'] ?? ($data['description'] ?? ''),
        'classification'  => $data['classification'] ?? 'Internal',
        'department'      => $data['service_affected'] ?? 'Operations',
        'status'          => $data['status'] ?? 'Draft'
    ]);

    doc_save_rfo_details($doc_id, $data);

    // Support single initial timeline entry string
    if (!empty($data['initial_event_timeline'])) {
        doc_add_rfo_timeline_entry(
            $doc_id,
            date('Y-m-d H:i:s'),
            $data['initial_event_timeline'],
            $data['person'] ?? 'System / External Plugin',
            $data['source'] ?? 'Inter-Plugin API',
            $data['notes'] ?? 'Auto-generated event timeline from external plugin trigger'
        );
    }

    // Support array of timeline entries in creation payload
    $timeline_entries = $data['timeline_entries'] ?? ($data['timelines'] ?? []);
    if (is_array($timeline_entries)) {
        foreach ($timeline_entries as $entry) {
            if (is_array($entry) && !empty($entry['event'])) {
                doc_add_rfo_timeline_entry(
                    $doc_id,
                    $entry['timestamp'] ?? date('Y-m-d H:i:s'),
                    $entry['event'],
                    $entry['person'] ?? ($data['person'] ?? 'System'),
                    $entry['source'] ?? ($data['source'] ?? 'External Plugin'),
                    $entry['notes'] ?? ''
                );
            }
        }
    }

    doc_audit_log('RFO_INITIATED_EXTERNALLY', 'document', $doc_id, 'SUCCESS', [
        'incident_number' => $data['incident_number'] ?? 'N/A',
        'service' => $data['service_affected'] ?? 'N/A'
    ]);

    return $doc_id;
}

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

    doc_audit_log('RFO_TIMELINE_ENTRY_ADDED', 'document', $doc_id, 'SUCCESS', [
        'event' => $event,
        'person' => $person,
        'source' => $source
    ]);
}

function doc_get_rfo_timelines($doc_id) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('rfo_timelines');
    return $pdb->query("SELECT * FROM {$tb} WHERE document_id = ? ORDER BY timestamp ASC", [(int)$doc_id])->fetchAll(PDO::FETCH_ASSOC);
}
