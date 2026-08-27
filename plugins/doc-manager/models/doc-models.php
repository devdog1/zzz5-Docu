<?php
// doc-models.php - Master Loader for Document Management Modular Domain Models

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

// Load Domain Model Modules
require_once __DIR__ . '/doc-core-models.php';
require_once __DIR__ . '/doc-types-models.php';
require_once __DIR__ . '/doc-crud-models.php';
require_once __DIR__ . '/doc-rfo-models.php';
require_once __DIR__ . '/doc-postmortem-models.php';
require_once __DIR__ . '/doc-lawful-models.php';
require_once __DIR__ . '/doc-legalhold-models.php';
require_once __DIR__ . '/doc-workflow-models.php';
require_once __DIR__ . '/doc-retention-models.php';
