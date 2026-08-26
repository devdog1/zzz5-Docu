CREATE TABLE IF NOT EXISTS {prefix}document_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    description TEXT,
    default_classification VARCHAR(50) DEFAULT 'Internal',
    required_metadata TEXT,
    required_fields TEXT,
    workflow_steps TEXT,
    review_requirements TEXT,
    approval_requirements TEXT,
    retention_period_years INT DEFAULT 7,
    retention_policy_type VARCHAR(50) DEFAULT '7 years',
    notification_rules TEXT,
    allowed_departments TEXT,
    required_attachments TINYINT(1) DEFAULT 0,
    template TEXT,
    numbering_format VARCHAR(100) DEFAULT '{CODE}-{YYYY}-{NUMBER:6}',
    current_counter INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_number VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    document_type_id INT NOT NULL,
    classification VARCHAR(50) NOT NULL DEFAULT 'Internal',
    current_version VARCHAR(20) DEFAULT '0.1',
    status VARCHAR(50) DEFAULT 'Draft',
    owner_user_id INT NOT NULL,
    department VARCHAR(100),
    metadata TEXT,
    content TEXT,
    is_under_legal_hold TINYINT(1) DEFAULT 0,
    retention_expiry_date DATE,
    disposition_status VARCHAR(50) DEFAULT 'Active',
    destruction_certificate TEXT,
    verification_code VARCHAR(64) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}document_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version VARCHAR(20) NOT NULL,
    user_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    title VARCHAR(255),
    content TEXT,
    metadata TEXT,
    fields_changed TEXT,
    change_reason TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}document_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    step_number INT DEFAULT 1,
    step_name VARCHAR(100),
    approver_user_id INT,
    required_role VARCHAR(100),
    status VARCHAR(50) DEFAULT 'Pending',
    comments TEXT,
    decided_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}rfo_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNIQUE NOT NULL,
    incident_number VARCHAR(100),
    service_affected VARCHAR(255),
    systems_affected TEXT,
    customers_affected TEXT,
    geographic_areas_affected TEXT,
    incident_severity VARCHAR(50),
    start_datetime DATETIME,
    detection_datetime DATETIME,
    escalation_datetime DATETIME,
    service_restoration_datetime DATETIME,
    total_duration VARCHAR(100),
    impact_description TEXT,
    initial_symptoms TEXT,
    detection_method TEXT,
    root_cause TEXT,
    contributing_factors TEXT,
    resolution TEXT,
    recovery_actions TEXT,
    corrective_actions TEXT,
    preventative_actions TEXT,
    lessons_learned TEXT,
    monitoring_improvements TEXT,
    assigned_owner_id INT,
    reviewer_id INT,
    approver_id INT,
    related_tickets TEXT,
    related_changes TEXT,
    related_rfos TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}rfo_timelines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    timestamp DATETIME NOT NULL,
    event VARCHAR(255) NOT NULL,
    person VARCHAR(100),
    source VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}post_mortem_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNIQUE NOT NULL,
    executive_summary TEXT,
    incident_overview TEXT,
    business_impact TEXT,
    technical_impact TEXT,
    timeline TEXT,
    what_happened TEXT,
    root_cause TEXT,
    contributing_factors TEXT,
    detection_analysis TEXT,
    response_analysis TEXT,
    recovery_analysis TEXT,
    what_went_well TEXT,
    what_did_not_go_well TEXT,
    lessons_learned TEXT,
    corrective_actions TEXT,
    preventative_actions TEXT,
    follow_up_work TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}post_mortem_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    action_identifier VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    assigned_to VARCHAR(100) NOT NULL,
    priority VARCHAR(20) DEFAULT 'Medium',
    status VARCHAR(50) DEFAULT 'Open',
    due_date DATE,
    completion_date DATE,
    evidence TEXT,
    notes TEXT,
    verification_status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}lawful_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNIQUE NOT NULL,
    internal_request_number VARCHAR(100) NOT NULL,
    request_type VARCHAR(100) NOT NULL,
    requesting_organization VARCHAR(255) NOT NULL,
    agency VARCHAR(255),
    officer_contact VARCHAR(255),
    badge_or_id_number VARCHAR(100),
    contact_info TEXT,
    court_jurisdiction VARCHAR(255),
    court_file_number VARCHAR(100),
    external_reference_number VARCHAR(100),
    date_received DATE,
    received_by_user_id INT,
    method_received VARCHAR(100),
    authority_cited TEXT,
    scope_of_request TEXT,
    requested_information TEXT,
    customer_account_reference VARCHAR(255),
    target_identifiers TEXT,
    response_deadline DATE,
    assigned_handler_user_id INT,
    approving_authority_user_id INT,
    legal_review_status VARCHAR(50) DEFAULT 'Pending',
    execution_status VARCHAR(50) DEFAULT 'Received',
    completion_date DATE,
    disclosure_date DATE,
    disclosure_method VARCHAR(100),
    information_disclosed TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}chain_of_custody (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    activity_datetime DATETIME NOT NULL,
    person_name VARCHAR(255) NOT NULL,
    action VARCHAR(100) NOT NULL,
    source VARCHAR(255),
    destination VARCHAR(255),
    description TEXT,
    file_checksum VARCHAR(128),
    evidence_identifier VARCHAR(100),
    method_of_transfer VARCHAR(100),
    recipient VARCHAR(255),
    verification VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}legal_holds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT,
    legal_hold_number VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    authority VARCHAR(255),
    created_by_user_id INT NOT NULL,
    approved_by_user_id INT,
    start_date DATE,
    status VARCHAR(50) DEFAULT 'Active',
    scope TEXT,
    reason TEXT,
    related_case_request VARCHAR(255),
    custodians TEXT,
    systems_affected TEXT,
    search_criteria TEXT,
    release_authorization TEXT,
    release_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}legal_hold_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    legal_hold_id INT NOT NULL,
    document_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}document_relationships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_document_id INT NOT NULL,
    target_document_id INT NOT NULL,
    relationship_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}document_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version_str VARCHAR(20) DEFAULT '1.0',
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    sha256_hash VARCHAR(64) NOT NULL,
    uploaded_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT,
    username VARCHAR(100),
    ip_address VARCHAR(45),
    session_id VARCHAR(128),
    user_agent TEXT,
    action VARCHAR(100) NOT NULL,
    object_type VARCHAR(100),
    object_id VARCHAR(100),
    result VARCHAR(50) DEFAULT 'SUCCESS',
    metadata TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {prefix}comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    author_user_id INT NOT NULL,
    author_name VARCHAR(100) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    comment_type VARCHAR(50) DEFAULT 'General',
    comment_text TEXT NOT NULL,
    version_history TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
