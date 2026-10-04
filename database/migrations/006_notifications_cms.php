<?php
// Notifications (modèles + journal) et contenus du site public.
return [
"CREATE TABLE notifications (id {{PK}}, company_id INT NOT NULL DEFAULT 1, event VARCHAR(50) NOT NULL, audience VARCHAR(10) NOT NULL DEFAULT 'client',
  channel VARCHAR(10) NOT NULL, subject VARCHAR(190) NULL, body TEXT NOT NULL, active TINYINT NOT NULL DEFAULT 1) {{ENGINE}}",
"CREATE TABLE notification_logs (id {{PK}}, company_id INT NOT NULL DEFAULT 1, event VARCHAR(50) NOT NULL, audience VARCHAR(10) NOT NULL DEFAULT 'client', channel VARCHAR(10) NOT NULL,
  recipient VARCHAR(190) NOT NULL, subject VARCHAR(190) NULL, content TEXT NOT NULL, template_id INT NULL, booking_id INT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending', attempts INT NOT NULL DEFAULT 0, error VARCHAR(500) NULL, next_attempt_at DATETIME NULL,
  sent_at DATETIME NULL, created_at DATETIME NOT NULL) {{ENGINE}}",
"CREATE INDEX idx_notiflogs_status ON notification_logs (status, next_attempt_at)",
"CREATE TABLE pages (id {{PK}}, company_id INT NOT NULL DEFAULT 1, slug VARCHAR(100) NOT NULL, title VARCHAR(190) NOT NULL, content TEXT NOT NULL, active TINYINT NOT NULL DEFAULT 1, updated_at DATETIME NULL) {{ENGINE}}",
"CREATE UNIQUE INDEX uq_pages_slug ON pages (company_id, slug)",
"CREATE TABLE faq (id {{PK}}, company_id INT NOT NULL DEFAULT 1, question VARCHAR(255) NOT NULL, answer TEXT NOT NULL, sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1) {{ENGINE}}",
"CREATE TABLE testimonials (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(120) NOT NULL, content TEXT NOT NULL, rating INT NOT NULL DEFAULT 5, sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1) {{ENGINE}}",
"CREATE TABLE contact_messages (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(120) NOT NULL, email VARCHAR(190) NULL, phone VARCHAR(30) NULL, message TEXT NOT NULL,
  handled TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL) {{ENGINE}}",
];
