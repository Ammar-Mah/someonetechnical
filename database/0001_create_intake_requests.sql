-- The intake's requests (#41): one column per question in PRODUCT.md's
-- conversion flow, and the visitor's contact. Written once for SQLite and
-- MySQL, in the subset .agent/framework/RULES.md section 8 gives. The model
-- stamps created_at and updated_at. The reverse drops the table and every row
-- in it.
CREATE TABLE intake_requests (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    building TEXT NULL,
    ai_tool VARCHAR(100) NULL,
    stuck_on TEXT NULL,
    is_live VARCHAR(20) NULL,
    help_wanted VARCHAR(20) NULL,
    contact_name VARCHAR(200) NOT NULL,
    contact_email VARCHAR(254) NOT NULL,
    preferred_time VARCHAR(200) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL
);

-- Requests are read newest first.
CREATE INDEX intake_requests_created_at ON intake_requests (created_at);
