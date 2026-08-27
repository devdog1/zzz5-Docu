# Document Management System Plugin (`doc-manager`)

An enterprise-grade, highly governed Document Management module built for the `zzz5` portal framework. This plugin delivers dynamic document auto-numbering, Word-style WYSIWYG rich text formatting, template editing, canned paragraph insertion, security classification controls, RFO/Incident Reporting, Post-Mortems with independent action item tracking, Lawful Work Orders with SHA-256 Chain of Custody, Legal Holds, approval workflows, immutable version control, audit trail logging, retention disposition management, automated background tasks, configurable background classification watermarks, and professional PDF generation.

---

## Table of Contents
1. [Key Features](#key-features)
2. [Word-Style WYSIWYG Editor & Templates](#word-style-wysiwyg-editor--templates)
3. [Security Classifications & RBAC](#security-classifications--rbac)
4. [Document Types & Auto-Numbering](#document-types--auto-numbering)
5. [RFO / Incident Report Module](#rfo--incident-report-module)
6. [Post-Mortem Module & Action Tracking](#post-mortem-module--action-tracking)
7. [Lawful Request & Chain of Custody Module](#lawful-request--chain-of-custody-module)
8. [Legal Hold Module](#legal-hold-module)
9. [Approval Workflows & Version Control](#approval-workflows--version-control)
10. [Retention & Disposition Management](#retention--disposition-management)
11. [Background Scheduled Tasks](#background-scheduled-tasks)
12. [PDF Watermarks & Audit Log](#pdf-watermarks--audit-log)
13. [Installation & Database Isolation](#installation--database-isolation)

---

## Key Features

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
- **PDF Export**: Renders professional printable document views complete with classification headers, company logo, footers, approval logs, and verification hash codes.

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

## PDF Watermarks & Audit Log

- **PDF Watermarks**: Administrators can enable or disable repeating diagonal classification background watermarks (`RESTRICTED`, `CONFIDENTIAL`, `INTERNAL`, `PUBLIC`) on PDF exports in Admin settings.
- **Audit Log**: Every creation, access, update, export, approval, classification change, or legal hold operation is logged in `plug_doc_manager_audit_log` with user ID, IP, timestamp, session ID, and metadata.
- **PDF Export**: Generates printable document records with security banners, company logo, document metadata, approval signatures, and verification hashes.

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
