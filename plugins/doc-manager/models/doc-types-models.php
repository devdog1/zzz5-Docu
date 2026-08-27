<?php
// doc-types-models.php - Document Type Administration, Auto-Numbering, Templates & Canned Snippets

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once __DIR__ . '/doc-core-models.php';

function doc_get_canned_paragraphs() {
    $raw = doc_get_setting('canned_snippets');
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && !empty($decoded)) {
        return $decoded;
    }
    return json_decode(doc_get_default_settings()['canned_snippets'], true);
}

function doc_render_template($template_html, $variables = []) {
    $logo_url = doc_get_setting('company_logo_url', '');
    $logo_html = !empty($logo_url) ? '<img src="' . htmlspecialchars($logo_url) . '" alt="Company Logo" style="max-height:65px; margin-bottom:15px;">' : '<div style="background:#0d6efd; color:#fff; padding:10px 15px; font-weight:bold; display:inline-block; border-radius:4px; margin-bottom:15px;">ENTERPRISE PORTAL LOGO</div>';

    $placeholders = [
        '{DOCUMENT_NUMBER}' => $variables['document_number'] ?? 'DOC-2026-000000',
        '{TITLE}' => $variables['title'] ?? 'Document Title',
        '{CLASSIFICATION}' => $variables['classification'] ?? 'Internal',
        '{DEPARTMENT}' => $variables['department'] ?? 'Operations',
        '{DATE}' => $variables['date'] ?? date('F d, Y'),
        '{OWNER}' => $variables['owner'] ?? (current_user()['name'] ?? 'System User'),
        '{ORGANIZATION_LOGO}' => $logo_html
    ];

    return str_replace(array_keys($placeholders), array_values($placeholders), $template_html);
}

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

function doc_update_type_template($id, $template_html, $numbering_format, $default_classification, $retention_years) {
    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_types');
    $pdb->query("
        UPDATE {$tb}
        SET template = ?, numbering_format = ?, default_classification = ?, retention_period_years = ?
        WHERE id = ?
    ", [$template_html, $numbering_format, $default_classification, (int)$retention_years, (int)$id]);
}

function doc_generate_number($type_id) {
    $type = doc_get_type($type_id);
    if (!$type) {
        return 'DOC-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    $pdb = doc_get_pdb();
    $tb = $pdb->getTableName('document_types');

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

    $canned = doc_get_canned_paragraphs();

    $defaults = [
        [
            'name' => 'Reason For Outage / Incident Report',
            'code' => 'RFO',
            'description' => 'Incident analysis, outages, service impacts, and root cause investigations.',
            'default_classification' => 'Internal',
            'numbering_format' => 'RFO-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Author', 'Technical Reviewer', 'Manager', 'Approved']),
            'retention_period_years' => 7,
            'template' => "{ORGANIZATION_LOGO}\n<h2>Reason For Outage Report — {DOCUMENT_NUMBER}</h2>\n<p><strong>Incident Title:</strong> {TITLE}</p>\n<p><strong>Department:</strong> {DEPARTMENT} | <strong>Owner:</strong> {OWNER} | <strong>Date:</strong> {DATE}</p>\n<p><strong>Security Classification:</strong> {CLASSIFICATION}</p>\n<hr>\n<h3>1. Executive Incident Summary</h3>\n<p>Enter high-level description of outage event and business impact...</p>\n<h3>2. Root Cause Analysis</h3>\n<p>Detailed technical cause and triggering factors...</p>\n<h3>3. Resolution & Restoration Actions</h3>\n<p>Steps executed to restore service and verify stability...</p>\n<hr>\n<p><em>" . $canned['incident_disclaimer'] . "</em></p>"
        ],
        [
            'name' => 'Post-Mortem Report',
            'code' => 'PM',
            'description' => 'Structured post-incident technical and operational review.',
            'default_classification' => 'Internal',
            'numbering_format' => 'PM-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Author', 'Technical Reviewer', 'Manager', 'Director', 'Final']),
            'retention_period_years' => 7,
            'template' => "{ORGANIZATION_LOGO}\n<h2>Post-Mortem Review — {DOCUMENT_NUMBER}</h2>\n<p><strong>Review Subject:</strong> {TITLE}</p>\n<p><strong>Lead Investigator:</strong> {OWNER} | <strong>Department:</strong> {DEPARTMENT} | <strong>Date:</strong> {DATE}</p>\n<p><strong>Classification:</strong> {CLASSIFICATION}</p>\n<hr>\n<h3>Executive Summary</h3>\n<p>Summary of technical findings and lessons learned...</p>\n<h3>Technical & Business Impact</h3>\n<p>Systems affected and user impact metrics...</p>\n<h3>What Went Well / What Did Not Go Well</h3>\n<p>Response evaluation...</p>\n<hr>\n<p><em>" . $canned['compliance_statement'] . "</em></p>"
        ],
        [
            'name' => 'Lawful Work Order',
            'code' => 'LWO',
            'description' => 'Court orders, warrants, and statutory interception/production orders.',
            'default_classification' => 'Restricted',
            'numbering_format' => 'LWO-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Authorized Request Handler', 'Designated Authority', 'Execution', 'Verification', 'Closed']),
            'retention_period_years' => 10,
            'template' => "{ORGANIZATION_LOGO}\n<h2>Statutory Lawful Request Record — {DOCUMENT_NUMBER}</h2>\n<p><strong>Request Order Title:</strong> {TITLE}</p>\n<p><strong>Date Received:</strong> {DATE} | <strong>Assigned Handler:</strong> {OWNER}</p>\n<p><strong>Classification:</strong> RESTRICTED AUTHORIZED ACCESS ONLY</p>\n<hr>\n<h3>Scope of Statutory Demand</h3>\n<p>Enter court file numbers, jurisdiction, and legal authority cited...</p>\n<h3>Evidence & Chain of Custody Summary</h3>\n<p>Record of evidence transfers and SHA-256 file checksums...</p>\n<hr>\n<p><em>" . $canned['confidentiality_notice'] . "</em></p>"
        ],
        [
            'name' => 'Legal Hold Directive',
            'code' => 'LH',
            'description' => 'Litigation hold directives and records preservation commands.',
            'default_classification' => 'Restricted',
            'numbering_format' => 'LH-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Draft', 'Approved', 'Active', 'Released']),
            'retention_period_years' => 10,
            'template' => "{ORGANIZATION_LOGO}\n<h2>Legal Preservation Directive — {DOCUMENT_NUMBER}</h2>\n<p><strong>Directive Title:</strong> {TITLE}</p>\n<p><strong>Issued By:</strong> {OWNER} | <strong>Effective Date:</strong> {DATE}</p>\n<hr>\n<p><strong>" . $canned['legal_hold_warning'] . "</strong></p>\n<h3>Scope & Affected Custodians</h3>\n<p>List custodians, systems, and date ranges subject to hold...</p>"
        ],
        [
            'name' => 'Security Investigation',
            'code' => 'SEC',
            'description' => 'Internal security assessments, forensic reports, and vulnerability notes.',
            'default_classification' => 'Restricted',
            'numbering_format' => 'SEC-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Draft', 'Under Review', 'Approved', 'Closed']),
            'retention_period_years' => 7,
            'template' => "{ORGANIZATION_LOGO}\n<h2>Security Investigation Report — {DOCUMENT_NUMBER}</h2>\n<p><strong>Investigation Subject:</strong> {TITLE}</p>\n<p><strong>Lead Investigator:</strong> {OWNER} | <strong>Date:</strong> {DATE}</p>\n<hr>\n<h3>Forensic Findings</h3>\n<p>Detailed analysis of security events and vulnerability vectors...</p>\n<p><em>" . $canned['confidentiality_notice'] . "</em></p>"
        ],
        [
            'name' => 'Policy / Standard Operating Procedure',
            'code' => 'POL',
            'description' => 'Governance policy, operational procedures, and guideline documents.',
            'default_classification' => 'Internal',
            'numbering_format' => 'POL-{YYYY}-{NUMBER:6}',
            'workflow_steps' => json_encode(['Draft', 'Review', 'Approval', 'Published']),
            'retention_period_years' => 10,
            'template' => "{ORGANIZATION_LOGO}\n<h2>Standard Operating Procedure — {DOCUMENT_NUMBER}</h2>\n<p><strong>SOP Title:</strong> {TITLE}</p>\n<p><strong>Department:</strong> {DEPARTMENT} | <strong>Effective Date:</strong> {DATE}</p>\n<hr>\n<h3>1. Objective & Scope</h3>\n<p>Define operational purpose and applicable teams...</p>\n<h3>2. Standard Procedure Steps</h3>\n<p>Step-by-step operational instructions...</p>\n<hr>\n<p><em>" . $canned['compliance_statement'] . "</em></p>"
        ]
    ];

    foreach ($defaults as $d) {
        $pdb->query("
            INSERT INTO {$tb} (name, code, description, default_classification, numbering_format, workflow_steps, retention_period_years, template)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [$d['name'], $d['code'], $d['description'], $d['default_classification'], $d['numbering_format'], $d['workflow_steps'], $d['retention_period_years'], $d['template']]);
    }
}

function doc_seed_demo_data() {
    doc_seed_default_types();

    $rfo_type_id = doc_get_type_id_by_code('RFO');
    if ($rfo_type_id) {
        $doc_id = doc_create_document([
            'document_type_id' => $rfo_type_id,
            'title' => 'Sample RFO: Core Backbone BGP Flap',
            'description' => 'Automated demo incident report for backbone fiber disruption.',
            'classification' => 'Internal',
            'department' => 'Network Operations',
            'content' => 'Root cause determined to be physical fiber cut during highway construction.',
            'status' => 'Submitted'
        ]);

        doc_save_rfo_details($doc_id, [
            'incident_number' => 'INC-2026-9081',
            'service_affected' => 'Core IP Backhaul',
            'incident_severity' => 'SEV-1',
            'root_cause' => 'Physical fiber line severed by third-party contractor.',
            'resolution' => 'Traffic rerouted via redundant MPLS path.'
        ]);

        doc_add_rfo_timeline_entry($doc_id, date('Y-m-d H:i:s', strtotime('-2 hours')), 'Alarms triggered in Zabbix', 'NOC Automation', 'Zabbix', 'BGP peer session down.');
    }
}
