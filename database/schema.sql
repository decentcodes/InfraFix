CREATE TABLE citizens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    citizen_id VARCHAR(20) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;


CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE regions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region_id VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    region_type VARCHAR(50) NOT NULL,
    parent_region_id INT UNSIGNED NULL,
    boundary POLYGON SRID 4326 NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_regions_parent
        FOREIGN KEY (parent_region_id)
        REFERENCES regions(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE authorities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    authority_id VARCHAR(20) NOT NULL UNIQUE,
    public_name VARCHAR(150) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(255) NOT NULL,
    parent_authority_id INT UNSIGNED NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_authorities_parent
        FOREIGN KEY (parent_authority_id)
        REFERENCES authorities(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE authority_regions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    authority_id INT UNSIGNED NOT NULL,
    region_id INT UNSIGNED NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_authority_regions_authority
        FOREIGN KEY (authority_id)
        REFERENCES authorities(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_authority_regions_region
        FOREIGN KEY (region_id)
        REFERENCES regions(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT uq_authority_region
        UNIQUE (authority_id, region_id)
) ENGINE=InnoDB;

CREATE TABLE authority_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    authority_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_authority_categories_authority
        FOREIGN KEY (authority_id)
        REFERENCES authorities(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_authority_categories_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT uq_authority_category
        UNIQUE (authority_id, category_id)
) ENGINE=InnoDB;

CREATE TABLE issues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_id VARCHAR(20) NOT NULL UNIQUE,
    category_id INT UNSIGNED NOT NULL,
    region_id INT UNSIGNED NOT NULL,
    authority_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_issues_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_issues_region
        FOREIGN KEY (region_id)
        REFERENCES regions(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_issues_authority
        FOREIGN KEY (authority_id)
        REFERENCES authorities(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_issues_status
        CHECK (status IN (
            'Open',
            'In Progress',
            'Resolved',
            'Closed',
            'Rejected'
        ))
) ENGINE=InnoDB;

CREATE TABLE reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id VARCHAR(20) NOT NULL UNIQUE,
    issue_id INT UNSIGNED NOT NULL,
    citizen_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    description VARCHAR(500) NOT NULL,
    reported_latitude DECIMAL(10, 7) NOT NULL,
    reported_longitude DECIMAL(10, 7) NOT NULL,
    reporter_latitude DECIMAL(10, 7) NOT NULL,
    reporter_longitude DECIMAL(10, 7) NOT NULL,
    location_verified BOOLEAN NOT NULL DEFAULT FALSE,
    location_verification_distance_m DECIMAL(8, 3) NULL,
    reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reports_issue
        FOREIGN KEY (issue_id)
        REFERENCES issues(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_reports_citizen
        FOREIGN KEY (citizen_id)
        REFERENCES citizens(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_reports_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE report_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_photo_id VARCHAR(20) NOT NULL UNIQUE,
    report_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_report_photos_report
        FOREIGN KEY (report_id)
        REFERENCES reports(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE issue_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_status_history_id VARCHAR(20) NOT NULL UNIQUE,
    issue_id INT UNSIGNED NOT NULL,
    previous_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NOT NULL,
    changed_by_authority_id INT UNSIGNED NULL,
    remark VARCHAR(500) NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_status_history_issue
        FOREIGN KEY (issue_id)
        REFERENCES issues(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_status_history_authority
        FOREIGN KEY (changed_by_authority_id)
        REFERENCES authorities(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_status_history_previous
        CHECK (
            previous_status IS NULL
            OR previous_status IN (
                'Open',
                'In Progress',
                'Resolved',
                'Closed',
                'Rejected'
            )
        ),

    CONSTRAINT chk_status_history_new
        CHECK (
            new_status IN (
                'Open',
                'In Progress',
                'Resolved',
                'Closed',
                'Rejected'
            )
        )
) ENGINE=InnoDB;

CREATE TABLE issue_resolutions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resolution_id VARCHAR(20) NOT NULL UNIQUE,
    issue_id INT UNSIGNED NOT NULL,
    authority_id INT UNSIGNED NOT NULL,
    remark VARCHAR(500) NOT NULL,
    resolved_at TIMESTAMP NOT NULL,
    evidence_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_issue_resolutions_issue
        FOREIGN KEY (issue_id)
        REFERENCES issues(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_issue_resolutions_authority
        FOREIGN KEY (authority_id)
        REFERENCES authorities(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE resolution_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feedback_id VARCHAR(20) NOT NULL UNIQUE,
    resolution_id INT UNSIGNED NOT NULL,
    citizen_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment VARCHAR(500) NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_resolution_feedback_resolution
        FOREIGN KEY (resolution_id)
        REFERENCES issue_resolutions(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_resolution_feedback_citizen
        FOREIGN KEY (citizen_id)
        REFERENCES citizens(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_resolution_feedback_rating
        CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;