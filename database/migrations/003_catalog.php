<?php
// Catalogue : catégories, compétences, services, formules (service_prices), options, champs dynamiques.
return [
"CREATE TABLE service_categories (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL) {{ENGINE}}",
"CREATE TABLE skills (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(120) NOT NULL, deleted_at DATETIME NULL) {{ENGINE}}",
"CREATE TABLE services (id {{PK}}, company_id INT NOT NULL DEFAULT 1, category_id INT NULL, required_skill_id INT NULL, name VARCHAR(150) NOT NULL, slug VARCHAR(170) NOT NULL,
  short_description VARCHAR(255) NULL, description TEXT NULL, image VARCHAR(190) NULL, base_price INT NOT NULL DEFAULT 0,
  billing_unit VARCHAR(20) NOT NULL DEFAULT 'fixed', base_duration INT NOT NULL DEFAULT 60, duration_per_unit INT NOT NULL DEFAULT 0,
  conditions TEXT NULL, featured TINYINT NOT NULL DEFAULT 0, availability_status VARCHAR(20) NOT NULL DEFAULT 'available',
  active TINYINT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NULL, deleted_at DATETIME NULL) {{ENGINE}}",
"CREATE UNIQUE INDEX uq_services_slug ON services (company_id, slug)",
"CREATE TABLE service_images (id {{PK}}, service_id INT NOT NULL, path VARCHAR(190) NOT NULL, sort INT NOT NULL DEFAULT 0, FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE) {{ENGINE}}",
// Formules = lignes de tarif d'un service (ex. Lavage Simple / Complet / Premium)
"CREATE TABLE service_prices (id {{PK}}, service_id INT NOT NULL, name VARCHAR(120) NOT NULL, description VARCHAR(255) NULL, amount INT NOT NULL DEFAULT 0,
  duration_minutes INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL, FOREIGN KEY (service_id) REFERENCES services(id)) {{ENGINE}}",
"CREATE TABLE service_options (id {{PK}}, service_id INT NOT NULL, name VARCHAR(120) NOT NULL, description VARCHAR(255) NULL, price INT NOT NULL DEFAULT 0,
  duration_minutes INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL, FOREIGN KEY (service_id) REFERENCES services(id)) {{ENGINE}}",
// Champs dynamiques du formulaire. role : none | quantity | area (multiplie le prix) 
"CREATE TABLE service_fields (id {{PK}}, service_id INT NOT NULL, field_key VARCHAR(50) NOT NULL, label VARCHAR(150) NOT NULL, type VARCHAR(20) NOT NULL DEFAULT 'select',
  role VARCHAR(20) NOT NULL DEFAULT 'none', required TINYINT NOT NULL DEFAULT 1, min_value INT NULL, max_value INT NULL, help VARCHAR(255) NULL,
  sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL, FOREIGN KEY (service_id) REFERENCES services(id)) {{ENGINE}}",
"CREATE TABLE service_field_choices (id {{PK}}, field_id INT NOT NULL, label VARCHAR(150) NOT NULL, price_delta INT NOT NULL DEFAULT 0, multiplier DECIMAL(6,2) NOT NULL DEFAULT 1,
  duration_delta INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL, FOREIGN KEY (field_id) REFERENCES service_fields(id)) {{ENGINE}}",
];
