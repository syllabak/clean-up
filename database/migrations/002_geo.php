<?php
// Hiérarchie géographique : Région → Ville → Commune → Quartier.
return [
"CREATE TABLE regions (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(120) NOT NULL, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL) {{ENGINE}}",
"CREATE TABLE cities (id {{PK}}, company_id INT NOT NULL DEFAULT 1, region_id INT NOT NULL, name VARCHAR(120) NOT NULL, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL,
  FOREIGN KEY (region_id) REFERENCES regions(id)) {{ENGINE}}",
"CREATE TABLE communes (id {{PK}}, company_id INT NOT NULL DEFAULT 1, city_id INT NOT NULL, name VARCHAR(120) NOT NULL, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL,
  FOREIGN KEY (city_id) REFERENCES cities(id)) {{ENGINE}}",
// intervention_days : numéros ISO séparés par des virgules (1=lundi … 7=dimanche). Vide = tous les jours.
"CREATE TABLE neighborhoods (id {{PK}}, company_id INT NOT NULL DEFAULT 1, commune_id INT NOT NULL, name VARCHAR(120) NOT NULL,
  travel_fee INT NOT NULL DEFAULT 0, avg_travel_minutes INT NOT NULL DEFAULT 20, intervention_days VARCHAR(20) NOT NULL DEFAULT '',
  slot_start VARCHAR(5) NOT NULL DEFAULT '', slot_end VARCHAR(5) NOT NULL DEFAULT '', conditions TEXT NULL,
  sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, deleted_at DATETIME NULL, FOREIGN KEY (commune_id) REFERENCES communes(id)) {{ENGINE}}",
];
