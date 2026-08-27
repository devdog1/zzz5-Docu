<?php
/**
 * Plugin Name: Document Management System
 * Description: Enterprise Document Governance, Auto-Numbering, RFO Incident Reports, Post-Mortems, Lawful Work Orders, Chain of Custody & Legal Holds.
 * Version: 1.0.0
 * Author: DevDog
 * Permissions: view_documents, create_documents, edit_documents, view_restricted, view_confidential, legal_request_access, manage_lawful_requests, manage_legal_holds, manage_types, manage_documents
 * Roles: doc_admin:view_documents,create_documents,edit_documents,view_restricted,view_confidential,legal_request_access,manage_lawful_requests,manage_legal_holds,manage_types,manage_documents; legal_officer:view_documents,create_documents,edit_documents,view_restricted,view_confidential,legal_request_access,manage_lawful_requests,manage_legal_holds; incident_analyst:view_documents,create_documents,edit_documents,view_confidential; doc_viewer:view_documents
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

// Load Domain Models Engine
require_once __DIR__ . '/models/doc-models.php';

// Load Background Scheduled Tasks
require_once __DIR__ . '/tasks/doc-tasks.php';

/* =========================================================
 * ACTIVATION & DEACTIVATION HOOKS
 * ========================================================= */

add_action('plugin_activate_doc-manager', 'doc_plugin_install_tables');
function doc_plugin_install_tables() {
    $install_sql_file = __DIR__ . '/sql/install.sql';
    if (file_exists($install_sql_file)) {
        $pdb = doc_get_pdb();
        $db = get_db_connection();
        $sql = file_get_contents($install_sql_file);

        $sql = str_replace('{prefix}', $pdb->getPrefix(), $sql);
        $db->exec($sql);
    }
    doc_seed_default_types();
}

add_action('plugin_deactivate_doc-manager', 'doc_plugin_uninstall_tables');
function doc_plugin_uninstall_tables($purge_tables = false) {
    if (!$purge_tables) {
        return; // Retain tables unless explicitly requested to purge!
    }
    $uninstall_sql_file = __DIR__ . '/sql/uninstall.sql';
    if (file_exists($uninstall_sql_file)) {
        $pdb = doc_get_pdb();
        $db = get_db_connection();
        $sql = file_get_contents($uninstall_sql_file);

        $sql = str_replace('{prefix}', $pdb->getPrefix(), $sql);
        $db->exec($sql);
    }
}

/* =========================================================
 * SCHEDULED BACKGROUND TASKS REGISTRATION
 * ========================================================= */

add_action('init_scheduler', function($scheduler) {
    if (doc_get_setting('module_retention_enabled', '1') === '1') {
        $scheduler->registerTask(
            'doc_retention_check',
            'doc_task_retention_check',
            86400, // Daily (24 hours)
            'doc-manager'
        );
    }

    $scheduler->registerTask(
        'doc_deadline_alerts',
        'doc_task_deadline_alerts',
        3600, // Hourly
        'doc-manager'
    );
});

/* =========================================================
 * NAVIGATION MENU LINKS FILTER
 * ========================================================= */

add_filter('theme_nav_links', function($nav) {
    if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) {
        return $nav;
    }

    $doc_menu = [
        'label' => 'Document Management',
        'icon'  => 'fa-solid fa-folder-tree',
        'route' => 'doc_manager_dashboard',
        'children' => [
            ['label' => 'Dashboard', 'icon' => 'fa-solid fa-chart-line', 'route' => 'doc_manager_dashboard'],
            ['label' => 'Document Repository', 'icon' => 'fa-solid fa-folder-open', 'route' => 'doc_manager_documents'],
        ]
    ];

    if (doc_get_setting('module_rfo_enabled', '1') === '1') {
        $doc_menu['children'][] = ['label' => 'RFO / Incident Reports', 'icon' => 'fa-solid fa-triangle-exclamation', 'route' => 'doc_manager_rfo'];
    }

    if (doc_get_setting('module_post_mortem_enabled', '1') === '1') {
        $doc_menu['children'][] = ['label' => 'Post-Mortem Module', 'icon' => 'fa-solid fa-microscope', 'route' => 'doc_manager_post_mortem'];
    }

    if (doc_can_access_lawful_requests()) {
        if (doc_get_setting('module_lawful_enabled', '1') === '1') {
            $doc_menu['children'][] = ['label' => 'Lawful Work Orders', 'icon' => 'fa-solid fa-scale-balanced', 'route' => 'doc_manager_lawful'];
        }
        if (doc_get_setting('module_legal_hold_enabled', '1') === '1') {
            $doc_menu['children'][] = ['label' => 'Legal Holds', 'icon' => 'fa-solid fa-lock', 'route' => 'doc_manager_legal_hold'];
        }
    }

    if (doc_get_setting('module_retention_enabled', '1') === '1') {
        $doc_menu['children'][] = ['label' => 'Retention & Disposition', 'icon' => 'fa-solid fa-hourglass-half', 'route' => 'doc_manager_retention'];
    }

    if (doc_get_setting('module_reports_enabled', '1') === '1') {
        $doc_menu['children'][] = ['label' => 'Analytics & Reports', 'icon' => 'fa-solid fa-chart-column', 'route' => 'doc_manager_reports'];
    }

    if (has_permission('doc_manager_manage_types') || has_permission('manage_settings')) {
        $doc_menu['children'][] = ['label' => 'Admin Settings', 'icon' => 'fa-solid fa-gears', 'route' => 'doc_manager_admin'];
    }

    $nav[] = $doc_menu;
    return $nav;
});

/* =========================================================
 * ROUTE HANDLERS REGISTRATION
 * ========================================================= */

add_action('register_routes', function() {
    register_route('doc_manager_dashboard', function() {
        if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) die('Access Denied');
        require_once __DIR__ . '/views/dashboard-view.php';
    });

    register_route('doc_manager_documents', function() {
        if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) die('Access Denied');
        require_once __DIR__ . '/views/documents-view.php';
    });

    register_route('doc_manager_document_detail', function() {
        if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) die('Access Denied');
        require_once __DIR__ . '/views/document-detail-view.php';
    });

    register_route('doc_manager_rfo', function() {
        if (doc_get_setting('module_rfo_enabled', '1') !== '1') die('Module Disabled');
        if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) die('Access Denied');
        require_once __DIR__ . '/views/rfo-view.php';
    });

    register_route('doc_manager_post_mortem', function() {
        if (doc_get_setting('module_post_mortem_enabled', '1') !== '1') die('Module Disabled');
        if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) die('Access Denied');
        require_once __DIR__ . '/views/post-mortem-view.php';
    });

    register_route('doc_manager_lawful', function() {
        if (doc_get_setting('module_lawful_enabled', '1') !== '1') die('Module Disabled');
        if (!doc_can_access_lawful_requests()) die('Access Denied: Requires legal_request.access privilege.');
        require_once __DIR__ . '/views/lawful-view.php';
    });

    register_route('doc_manager_legal_hold', function() {
        if (doc_get_setting('module_legal_hold_enabled', '1') !== '1') die('Module Disabled');
        if (!doc_can_access_lawful_requests()) die('Access Denied: Requires legal_request.access privilege.');
        require_once __DIR__ . '/views/legal-hold-view.php';
    });

    register_route('doc_manager_retention', function() {
        if (doc_get_setting('module_retention_enabled', '1') !== '1') die('Module Disabled');
        if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) die('Access Denied');
        require_once __DIR__ . '/views/retention-view.php';
    });

    register_route('doc_manager_reports', function() {
        if (doc_get_setting('module_reports_enabled', '1') !== '1') die('Module Disabled');
        if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) die('Access Denied');
        require_once __DIR__ . '/views/reports-view.php';
    });

    register_route('doc_manager_pdf', function() {
        if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) die('Access Denied');
        require_once __DIR__ . '/views/pdf-view.php';
    });

    register_route('doc_manager_admin', function() {
        if (!has_permission('doc_manager_manage_types') && !has_permission('manage_settings')) die('Access Denied');
        require_once __DIR__ . '/views/admin-view.php';
    });
});

/* =========================================================
 * HOME DASHBOARD WIDGET
 * ========================================================= */

add_action('index_dashboard_widgets', function($userContext) {
    if (doc_get_setting('widget_dashboard_enabled', '1') !== '1') {
        return;
    }
    if (!has_permission('view_documents') && !has_permission('doc_manager_view_documents')) {
        return;
    }

    $user_id = (int)($_SESSION['user_id'] ?? 1);
    $my_drafts = doc_search_documents(['status' => 'Draft']);
    $my_drafts = array_filter($my_drafts, fn($d) => (int)$d['owner_user_id'] === $user_id);
    ?>
    <div class="col-12 col-md-6 col-lg-4 widget-block" data-widget-key="doc_manager_quick_widget" data-widget-title="Document Management Quick Hub">
        <div class="card shadow-sm border-start border-5 border-primary text-start">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="bg-primary text-white rounded-circle p-2 text-center me-3" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-folder-tree fs-5"></i>
                    </div>
                    <div>
                        <h6 class="card-title fw-bold mb-0 text-dark">Document Governance</h6>
                        <small class="text-muted">Document Management System</small>
                    </div>
                </div>
                <hr class="my-2">
                <p class="card-text small mb-2"><strong>Active Draft Documents:</strong> <span class="badge bg-primary"><?=$count = count($my_drafts)?> Drafts</span></p>
                <div class="d-grid">
                    <a href="<?= url_for('doc_manager_dashboard') ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Open Repository Hub</a>
                </div>
            </div>
        </div>
    </div>
    <?php
});
