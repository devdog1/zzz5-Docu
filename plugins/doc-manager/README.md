# Document Management System Plugin (`doc-manager`)

An enterprise-grade, highly governed Document Management module built for the `zzz5` portal framework. This plugin delivers dynamic document auto-numbering, Word-style WYSIWYG rich text formatting, template editing, canned paragraph insertion, security classification controls, RFO/Incident Reporting, Post-Mortems with independent action item tracking, Lawful Work Orders with SHA-256 Chain of Custody, Legal Holds, multi-authority approval sign-offs, immutable version control, audit trail logging, retention disposition management, automated background tasks, configurable background classification watermarks, verification QR codes, granular sub-module enable/disable toggles, inter-plugin creation and timeline action hooks, and professional PDF generation.

---

## Table of Contents
1. [Key Features](#key-features)
2. [Inter-Plugin Integration & Action Hooks](#inter-plugin-integration--action-hooks)
3. [Modular Domain Architecture](#modular-domain-architecture)
4. [Verbose Plugin Settings & Module Toggles](#verbose-plugin-settings--module-toggles)
5. [Word-Style WYSIWYG Editor & Templates](#word-style-wysiwyg-editor--templates)
6. [Multi-Authority Authorization Sign-Offs](#multi-authority-authorization-sign-offs)
7. [Security Classifications & RBAC](#security-classifications--rbac)
8. [Document Types & Auto-Numbering](#document-types--auto-numbering)
9. [RFO / Incident Report Module](#rfo--incident-report-module)
10. [Post-Mortem Module & Action Tracking](#post-mortem-module--action-tracking)
11. [Lawful Request & Chain of Custody Module](#lawful-request--chain-of-custody-module)
12. [Legal Hold Module](#legal-hold-module)
13. [Approval Workflows & Version Control](#approval-workflows--version-control)
14. [Retention & Disposition Management](#retention--disposition-management)
15. [Background Scheduled Tasks](#background-scheduled-tasks)
16. [PDF Watermarks, Verification QR Code & Audit Trail Inspector](#pdf-watermarks-verification-qr-code--audit-trail-inspector)
17. [Installation & Database Isolation](#installation--database-isolation)

---

## Key Features

- **Inter-Plugin Integration**: Other framework plugins (Monitoring, Helpdesk, Ticketing) can trigger RFO creation via `do_action('doc_manager_create_rfo', $data)` and append timeline entries via `do_action('doc_manager_add_rfo_timeline', $data)` or `doc_add_rfo_timeline_entry()`.
- **Multi-Authority Authorization Sign-offs**: Designate multiple sign-off authorities (specific users or required roles) per workflow step. Generates SHA-256 digital signature audit hashes upon sign-off.
- **Audit Trail Inspector**: Dedicated tab in Admin Settings for inspecting append-only audit logs with dynamic filtering and CSV export.
- **Modular Domain Models**: Domain architecture split across dedicated files (`doc-core-models.php`, `doc-types-models.php`, `doc-crud-models.php`, `doc-rfo-models.php`, `doc-postmortem-models.php`, `doc-lawful-models.php`, `doc-legalhold-models.php`, `doc-workflow-models.php`, `doc-retention-models.php`).
- **Verbose Admin Settings**: Comprehensive tabbed configuration panel for enable/disable sub-module toggles, company logo branding, PDF watermarks, footer notices, deadline alert thresholds, canned text snippets, and document type templates.
- **Word-Style WYSIWYG Editor**: Create and format document body content with headings, rich text formatting (bold, italic, underline, colors, text alignment, lists, tables), pasted images/logos, and canned paragraphs.
- **Document Templates & Placeholders**: Document types define default HTML templates with dynamic tags: `{ORGANIZATION_LOGO}`, `{DOCUMENT_NUMBER}`, `{TITLE}`, `{CLASSIFICATION}`, `{DEPARTMENT}`, `{DATE}`, and `{OWNER}`.
- **Repeating Classification Watermark**: Configurable option in Admin settings (`doc_manager_pdf_watermark_enabled`) to display repeating diagonal classification background watermarks across exported PDF pages.
- **Canned Paragraph Snippets**: Quick-insert pre-approved text blocks (Confidentiality Notices, Incident Analysis Disclaimers, Legal Hold Warnings, Regulatory Statements).
- **Document Auto-Numbering**: Formats document identifiers automatically based on configurable type templates (e.g., `RFO-2026-000123`, `PM-2026-000087`, `LWO-2026-000015`, `LH-2026-000004`, `SEC-2026-000032`, `POL-2026-000009`).
- **Security Classifications**: Supports `Public`, `Internal`, `Confidential`, and `Restricted` documents with explicit permission enforcement.
- **RFO / Incident Report Module**: Tracks incident severity (SEV-1, SEV-2, SEV-3), root cause, service impacts, resolution, and interactive event timelines.
- **Post-Mortem Module**: Structured incident reviews with independent, trackable action items including priority, status, assigned owners, and due dates.
- **Lawful Request Module**: Highly restricted module for court orders, warrants, and preservation demands with independent authorization (`legal_request.access`) and SHA-256 evidence chain of custody.
- **Legal Holds**: Issue litigation holds across specific documents or categories, freezing deletions and suspending retention expiry.
- **No Hard Delete Enforcement**: Enforces soft disposition states (`Pending Disposition`, `Destroyed Certificate`) to preserve compliance history.
- **Background Scheduled Tasks**: Automated daily retention expiry checks and hourly deadline/overdue alert monitoring.

---

## Inter-Plugin Integration & Action Hooks

Other framework plugins (such as Incident Management, Monitoring, Helpdesk, or Ticketing systems) can programmatically initiate RFO incident reports and append timeline entries within the Document Management System using framework event hooks or direct domain function calls.

### 1. Framework Action Hooks

#### Create an RFO Incident Report (`doc_manager_create_rfo`)

```php
// Trigger RFO Incident Report creation with initial timeline entries from an external ticketing plugin
do_action('doc_manager_create_rfo', [
    'incident_number'         => 'INC-2026-9081',
    'title'                   => 'Core IP Backhaul Fiber Cut Outage',
    'service_affected'        => 'Core IP Backhaul',
    'systems_affected'        => 'Gateway Router Stack 01, Switch Stack B',
    'customers_affected'      => 'Enterprise Transit Subscriptions',
    'geographic_areas_affected' => 'US-East Region',
    'incident_severity'       => 'SEV-1',
    'classification'          => 'Confidential',
    'start_datetime'          => '2026-08-18 12:49:00',
    'detection_datetime'      => '2026-08-18 13:49:00',
    'impact_description'      => 'Main fiber link severed during third-party excavation.',
    'initial_symptoms'        => 'BGP session drop, 100% loss on primary interface.',
    'detection_method'        => 'Zabbix Automated Telemetry',
    'timeline_entries'        => [
        [
            'timestamp' => '2026-08-18 12:49:00',
            'event'     => 'Primary link optical power loss detected',
            'person'    => 'Zabbix Sentinel',
            'source'    => 'Interface Monitoring',
            'notes'     => 'Automated alarm payload'
        ],
        [
            'timestamp' => '2026-08-18 13:05:00',
            'event'     => 'NOC On-Call Engineer dispatched fiber repair team',
            'person'    => 'J. Doe (NOC Lead)',
            'source'    => 'PagerDuty',
            'notes'     => 'Ticket #9901 escalated to Field Ops'
        ]
    ]
]);
```

#### Append an RFO Timeline Entry (`doc_manager_add_rfo_timeline`)

Append new chronological timeline entries to an existing RFO document using either the `document_id` or `incident_number`:

```php
// Append a new timeline event from an external monitoring or NOC plugin
do_action('doc_manager_add_rfo_timeline', [
    'incident_number' => 'INC-2026-9081', // Or 'document_id' => 12
    'timestamp'       => date('Y-m-d H:i:s'),
    'event'           => 'Fusing splice completed on core fiber strand',
    'person'          => 'Field Ops Technician',
    'source'          => 'OTDR Fiber Tester',
    'notes'           => 'Splice loss verified at <0.02 dB'
]);
```

### 2. Direct Programmatic Invocation

If `plugins/doc-manager/models/doc-rfo-models.php` is loaded, external plugins can invoke domain helpers directly:

```php
if (function_exists('doc_add_rfo_timeline_entry')) {
    doc_add_rfo_timeline_entry(
        $document_id,
        date('Y-m-d H:i:s'),
        'Service restoration confirmed by telemetry',
        'System Monitor',
        'Zabbix API',
        'Ping sweep 100% success'
    );
}
```

---

## Multi-Authority Authorization Sign-offs

- **Designated Signers**: Document workflows allow admins and owners to specify multiple designated sign-off authorities (specific users or required roles) for each approval step.
- **Digital Signature Hashes**: When an authorized user executes a sign-off, a unique SHA-256 signature hash is generated, recording the user ID, step ID, document ID, decision, and timestamp.
- **Sign-off Audit Stamps**: Exported PDF documents display official digital signature stamps with signer names, step titles, timestamps, and signature hashes.

---

## Modular Domain Architecture

The plugin business logic is organized into focused, modular domain files under `plugins/doc-manager/models/`:
- `doc-core-models.php`: Core database wrapper, settings engine, audit logging, security permission checks.
- `doc-types-models.php`: Document type administration, auto-numbering, templates, canned snippets.
- `doc-crud-models.php`: Document CRUD, version history, search, comments, relationships, soft deletion.
- `doc-rfo-models.php`: RFO / Incident report details, inter-plugin API creation, and timeline entries.
- `doc-postmortem-models.php`: Post-Mortem reviews and trackable action items.
- `doc-lawful-models.php`: Lawful requests and SHA-256 chain of custody.
- `doc-legalhold-models.php`: Legal holds and litigation preservation rules.
- `doc-workflow-models.php`: Approval workflow steps and decisions.
- `doc-retention-models.php`: Retention disposition queue and destruction certificates.
- `doc-models.php`: Master domain model loader for clean backward compatibility.

---

## Verbose Plugin Settings & Module Toggles

Access via `index.php?route=doc_manager_admin`:
- **Sub-Module Enable/Disable Toggles**: Turn on or off RFO, Post-Mortem, Lawful Requests, Legal Holds, Retention, Analytics Reports, or Home Dashboard Widget. Disabled modules are hidden from navigation menus and protected at the route level.
- **Branding & PDF Export**: Configure company logo URL, PDF background watermark toggle, and custom PDF footer disclaimers.
- **Deadline Thresholds**: Configure response deadline alert lead times in days.
- **Canned Snippet Manager**: Edit or add standardized disclaimers and legal notices.
- **Audit Trail Inspector**: View audit logs with action filters and CSV export.

---

## Word-Style WYSIWYG Editor & Templates

- **Rich Text Toolbar**: Full formatting support including headings (H1-H4), text styles, colors, alignment, lists, blockquotes, links, and embedded images.
- **Dynamic Placeholder Tags**:
  - `{ORGANIZATION_LOGO}`: Embedded company logo.
  - `{DOCUMENT_NUMBER}`: Auto-generated document number.
  - `{TITLE}`: Document title.
  - `{CLASSIFICATION}`: Document security classification.
  - `{DEPARTMENT}`: Department name.
  - `{DATE}`: Document date.
  - `{OWNER}`: Document owner/author name.
- **Canned Snippets**: One-click insertion of standardized legal disclaimers and compliance notices.

---

## Security Classifications & RBAC

Every document managed by the plugin MUST have a security classification:

1. **Public**: Information approved for public distribution.
2. **Internal**: Information intended for internal organizational use.
3. **Confidential**: Sensitive business, customer, personnel, or operational information.
4. **Restricted**: Highly sensitive records requiring explicitly authorized access. (Examples: Lawful work orders, legal holds, police requests, security investigations, lawful intercepts, cryptographic info).

### Dedicated Permissions & Roles

- **`doc_manager_view_documents`**: Access the document repository and general dashboard.
- **`doc_manager_create_documents`**: Create new governed documents.
- **`doc_manager_edit_documents`**: Modify existing documents and save new major/minor versions.
- **`doc_manager_view_confidential`**: View confidential documents.
- **`doc_manager_view_restricted`**: View restricted documents.
- **`legal_request.access` / `doc_manager_legal_request_access`**: Independent authorization for Lawful Work Orders and Legal Holds. Access is strictly controlled via explicit permission assignment.
- **`doc_manager_manage_types`**: Administration rights to configure document types, retention rules, and numbering formats.

---

## Document Types & Auto-Numbering

Document types are fully configurable through the administration interface (`index.php?route=doc_manager_admin`). Each document type defines:
- **Name & Code** (e.g., `RFO`, `PM`, `LWO`, `LH`, `SEC`, `POL`)
- **Default Security Classification**
- **Numbering Format** (e.g., `{CODE}-{YYYY}-{NUMBER:6}`)
- **Default Workflow Steps & Retention Period (Years)**
- **Default WYSIWYG Document Body Template**

Example numbering output:
- `RFO-2026-000123`
- `PM-2026-000087`
- `LWO-2026-000015`
- `LH-2026-000004`
- `SEC-2026-000032`
- `POL-2026-000009`

---

## RFO / Incident Report Module

Access via `index.php?route=doc_manager_rfo`. Tracks comprehensive incident fields:
- Incident Number, Title, Severity (SEV-1, SEV-2, SEV-3), Service Affected
- Impact Description, Root Cause Analysis, Resolution & Recovery Actions
- Interactive Event Timeline entries: `Timestamp`, `Event`, `Person`, `Source`, `Notes`

---

## Post-Mortem Module & Action Tracking

Access via `index.php?route=doc_manager_post_mortem`.
- Structured sections for Executive Summary, Technical & Business Impact, Detection & Response Analysis, What Went Well, and What Did Not Go Well.
- **Independent Action Tracking**: Each action contains an Action ID, Description, Assigned User/Team, Priority, Status (`Open`, `In Progress`, `Completed`), Due Date, and Verification Status.

---

## Lawful Request & Chain of Custody Module

Access via `index.php?route=doc_manager_lawful`.
- Built for court orders, warrants, production orders, preservation demands, police requests, and statutory interception orders.
- Enforces independent permission checks (`legal_request.access`).
- **SHA-256 Chain of Custody**: Every evidence activity logs Date/Time, Person, Action, Source, Destination, Description, File SHA-256 Checksum, Evidence ID, Method of Transfer, and Recipient.

---

## Legal Hold Module

Access via `index.php?route=doc_manager_legal_hold`.
- Create and apply legal holds to specific documents or categories.
- While under active legal hold:
  - Document deletion is strictly blocked.
  - Retention expiry is suspended.
  - Record alterations are logged in the compliance audit trail.

---

## Approval Workflows & Version Control

- **Configurable Approval Steps**: Sequential multi-step approval workflows (e.g., `Author` -> `Technical Reviewer` -> `Manager` -> `Approved`).
- **Version Control**: Every modification creates a new version entry (`v0.1`, `v0.2` ... `v1.0`). Historical versions are immutable and never overwritten.

---

## Retention & Disposition Management

Access via `index.php?route=doc_manager_retention`.
- Automatically calculates retention expiry based on document type policies.
- Expired records enter `Pending Disposition`. Records administrators can approve destruction, generating a permanent, tamper-evident **Destruction Certificate**.

---

## Background Scheduled Tasks

The plugin registers background scheduled jobs with the framework's `Scheduler`:
1. **`doc_retention_check`** (Interval: 86,400s / Daily): Scans active documents whose retention period has expired and marks them as `Pending Disposition`.
2. **`doc_deadline_alerts`** (Interval: 3,600s / Hourly): Scans for upcoming lawful request response deadlines and overdue post-mortem action items, logging alert notifications.

---

## PDF Watermarks, Verification QR Code & Audit Trail Inspector

- **Verification QR Code**: Inline QR code rendered on exported PDF documents allowing authorized mobile/scanner verification of record authenticity.
- **PDF Watermarks**: Administrators can enable or disable repeating diagonal classification background watermarks (`RESTRICTED`, `CONFIDENTIAL`, `INTERNAL`, `PUBLIC`) on PDF exports in Admin settings.
- **Audit Log Inspector**: Dedicated tab in Admin settings displaying audit history with action filters and CSV export.

---

## Installation & Database Isolation

The plugin uses `PluginDatabase('doc-manager')` to ensure all database tables are isolated within the `plug_doc_manager_` namespace:
- `plug_doc_manager_documents`
- `plug_doc_manager_document_types`
- `plug_doc_manager_document_versions`
- `plug_doc_manager_document_approvals`
- `plug_doc_manager_rfo_details`
- `plug_doc_manager_rfo_timelines`
- `plug_doc_manager_post_mortem_details`
- `plug_doc_manager_post_mortem_actions`
- `plug_doc_manager_lawful_requests`
- `plug_doc_manager_chain_of_custody`
- `plug_doc_manager_legal_holds`
- `plug_doc_manager_legal_hold_documents`
- `plug_doc_manager_document_relationships`
- `plug_doc_manager_audit_log`
- `plug_doc_manager_comments`
