<?php
// Équipes mobiles, agents, compétences, horaires, congés, zones desservies.
return [
"CREATE TABLE teams (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(120) NOT NULL, main_commune_id INT NULL, vehicle VARCHAR(100) NULL,
  daily_capacity INT NOT NULL DEFAULT 6, status VARCHAR(20) NOT NULL DEFAULT 'active', notes TEXT NULL, deleted_at DATETIME NULL) {{ENGINE}}",
"CREATE TABLE staff (id {{PK}}, company_id INT NOT NULL DEFAULT 1, team_id INT NULL, name VARCHAR(120) NOT NULL, phone VARCHAR(30) NULL, email VARCHAR(190) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active', deleted_at DATETIME NULL, FOREIGN KEY (team_id) REFERENCES teams(id)) {{ENGINE}}",
"CREATE TABLE staff_skills (staff_id INT NOT NULL, skill_id INT NOT NULL, PRIMARY KEY (staff_id, skill_id),
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE, FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE) {{ENGINE}}",
"CREATE TABLE team_services (team_id INT NOT NULL, service_id INT NOT NULL, PRIMARY KEY (team_id, service_id),
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE, FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE) {{ENGINE}}",
"CREATE TABLE neighborhood_teams (neighborhood_id INT NOT NULL, team_id INT NOT NULL, PRIMARY KEY (neighborhood_id, team_id),
  FOREIGN KEY (neighborhood_id) REFERENCES neighborhoods(id) ON DELETE CASCADE, FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE) {{ENGINE}}",
"CREATE TABLE working_hours (id {{PK}}, company_id INT NOT NULL DEFAULT 1, team_id INT NULL, staff_id INT NULL, day_of_week INT NOT NULL,
  start_time VARCHAR(5) NOT NULL, end_time VARCHAR(5) NOT NULL, break_start VARCHAR(5) NULL, break_end VARCHAR(5) NULL) {{ENGINE}}",
// team_id et staff_id NULL ensemble = fermeture de toute l'entreprise (jour férié)
"CREATE TABLE time_off (id {{PK}}, company_id INT NOT NULL DEFAULT 1, team_id INT NULL, staff_id INT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL,
  reason VARCHAR(190) NULL, deleted_at DATETIME NULL) {{ENGINE}}",
];
