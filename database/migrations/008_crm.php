<?php
// CRM, canaux d'acquisition, prospects, interactions, dépenses (gestion de l'entreprise).
return [
"CREATE TABLE acquisition_channels (id {{PK}}, company_id INT NOT NULL DEFAULT 1, code VARCHAR(30) NULL, name VARCHAR(100) NOT NULL, kind VARCHAR(30) NOT NULL DEFAULT 'autre',
  public TINYINT NOT NULL DEFAULT 1, active TINYINT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0, deleted_at DATETIME NULL) {{ENGINE}}",
"ALTER TABLE customers ADD COLUMN channel_id INT NULL",
"ALTER TABLE customers ADD COLUMN marketing_consent TINYINT NOT NULL DEFAULT 0",
"ALTER TABLE bookings ADD COLUMN channel_id INT NULL",
"CREATE INDEX idx_bookings_channel ON bookings (channel_id)",
"CREATE INDEX idx_customers_channel ON customers (channel_id)",
"CREATE TABLE leads (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(150) NOT NULL, phone VARCHAR(30) NULL, email VARCHAR(190) NULL, channel_id INT NULL, service_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new', estimated_value INT NOT NULL DEFAULT 0, assigned_user_id INT NULL, next_follow_up DATE NULL, lost_reason VARCHAR(255) NULL,
  customer_id INT NULL, notes TEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, deleted_at DATETIME NULL) {{ENGINE}}",
"CREATE INDEX idx_leads_status ON leads (company_id, status)",
"CREATE INDEX idx_leads_phone ON leads (phone)",
"CREATE TABLE interactions (id {{PK}}, company_id INT NOT NULL DEFAULT 1, customer_id INT NULL, lead_id INT NULL, user_id INT NULL, type VARCHAR(20) NOT NULL DEFAULT 'note',
  summary TEXT NOT NULL, created_at DATETIME NOT NULL) {{ENGINE}}",
"CREATE INDEX idx_interactions_customer ON interactions (customer_id)",
"CREATE INDEX idx_interactions_lead ON interactions (lead_id)",
"CREATE TABLE expenses (id {{PK}}, company_id INT NOT NULL DEFAULT 1, expense_date DATE NOT NULL, category VARCHAR(30) NOT NULL DEFAULT 'autre', label VARCHAR(190) NOT NULL,
  amount INT NOT NULL DEFAULT 0, channel_id INT NULL, supplier VARCHAR(120) NULL, notes TEXT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME NULL) {{ENGINE}}",
"CREATE INDEX idx_expenses_date ON expenses (company_id, expense_date)",
];
