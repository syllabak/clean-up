<?php
// Socle : entreprise, rôles/permissions, utilisateurs, paramètres, audit, limitation de débit.
return [
"CREATE TABLE companies (id {{PK}}, name VARCHAR(150) NOT NULL, slug VARCHAR(100) NOT NULL, active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL) {{ENGINE}}",
"CREATE TABLE roles (id {{PK}}, name VARCHAR(50) NOT NULL UNIQUE, label VARCHAR(100) NOT NULL) {{ENGINE}}",
"CREATE TABLE permissions (id {{PK}}, name VARCHAR(80) NOT NULL UNIQUE, label VARCHAR(150) NOT NULL) {{ENGINE}}",
"CREATE TABLE role_permissions (role_id INT NOT NULL, permission_id INT NOT NULL, PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE) {{ENGINE}}",
"CREATE TABLE users (id {{PK}}, company_id INT NOT NULL DEFAULT 1, role_id INT NOT NULL, staff_id INT NULL, name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE, phone VARCHAR(30) NULL, password_hash VARCHAR(255) NOT NULL, active TINYINT NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, deleted_at DATETIME NULL, FOREIGN KEY (role_id) REFERENCES roles(id)) {{ENGINE}}",
"CREATE TABLE settings (id {{PK}}, company_id INT NOT NULL DEFAULT 1, skey VARCHAR(100) NOT NULL, svalue TEXT NULL, UNIQUE (company_id, skey)) {{ENGINE}}",
"CREATE TABLE audit_logs (id {{PK}}, company_id INT NOT NULL DEFAULT 1, user_id INT NULL, action VARCHAR(80) NOT NULL, entity VARCHAR(80) NULL,
  entity_id INT NULL, data TEXT NULL, ip VARCHAR(45) NULL, created_at DATETIME NOT NULL) {{ENGINE}}",
"CREATE INDEX idx_audit_created ON audit_logs (created_at)",
"CREATE TABLE rate_limits (rkey VARCHAR(64) NOT NULL PRIMARY KEY, hits INT NOT NULL, window_start INT NOT NULL) {{ENGINE}}",
];
