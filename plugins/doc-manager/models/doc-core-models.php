<?php
// doc-core-models.php - Core Database Wrapper, Verbose Settings, Audit Logging & Security Permissions

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
 * VERBOSE PLUGIN SETTINGS ENGINE
 * ========================================================= */

function doc_get_default_settings() {
    return [
        'module_rfo_enabled' => '1',
        'module_post_mortem_enabled' => '1',
        'module_lawful_enabled' => '1',
        'module_legal_hold_enabled' => '1',
        'module_retention_enabled' => '1',
        'module_reports_enabled' => '1',
        'widget_dashboard_enabled' => '1',
        'pdf_watermark_enabled' => '1',
        'company_logo_url' => '',
        'pdf_footer_notice' => 'Governed Document Record — Managed by Portal Framework',
        'deadline_alert_days' => '3',
        'canned_snippets' => json_encode([
            'confidentiality_notice' => 'NOTICE OF CONFIDENTIALITY: The information contained in this document is strictly confidential and intended solely for the use of authorized personnel. Unauthorized distribution, copying, or disclosure is strictly prohibited under organizational security policy.',
            'incident_disclaimer' => 'INCIDENT ANALYSIS DISCLAIMER: This Reason for Outage (RFO) contains preliminary technical findings subject to ongoing forensic verification by Network Engineering and Security Operations.',
            'legal_hold_warning' => 'LITIGATION PRESERVATION DIRECTIVE: All records, attachments, and metadata associated with this legal hold must be preserved in their original state. Automatic purging, routine destruction, and record modifications are strictly suspended.',
            'compliance_statement' => 'REGULATORY COMPLIANCE STATEMENT: This governed record has been created, versioned, and audited in full compliance with ISO/IEC governance, statutory retention periods, and enterprise information security standards.'
        ])
    ];
}

function doc_get_setting($key, $default = null) {
    $defaults = doc_get_default_settings();
    $fallback = $default ?? ($defaults[$key] ?? '');
    return get_plugin_setting('doc-manager', $key, $fallback);
}

function doc_set_setting($key, $value) {
    set_plugin_setting('doc-manager', $key, $value);
}

function doc_get_all_settings() {
    $defaults = doc_get_default_settings();
    $settings = [];
    foreach ($defaults as $k => $def) {
        $settings[$k] = doc_get_setting($k, $def);
    }
    return $settings;
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
    if (doc_get_setting('module_lawful_enabled', '1') !== '1') {
        return false;
    }
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
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

    if ($classification === 'Confidential') {
        if (has_permission('doc_manager_view_confidential') || has_permission('doc_manager_manage_documents')) {
            return true;
        }
        if ((int)$doc['owner_user_id'] === $user_id) {
            return true;
        }
        return false;
    }

    return true;
}

/* =========================================================
 * NOTIFICATION LOGIC
 * ========================================================= */

function doc_send_notification($recipient_user_id, $subject, $message, $doc_id = null) {
    doc_audit_log('NOTIFICATION_SENT', 'document', $doc_id, 'SUCCESS', [
        'recipient_user_id' => $recipient_user_id,
        'subject' => $subject,
        'message' => $message
    ]);
}
