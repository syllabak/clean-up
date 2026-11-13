<?php
// Espace de recrutement : candidatures reçues par le formulaire public, suivies depuis l'administration.
use App\Core\{Database, Migrator};

return function (): void {
    $x = fn(string $sql) => Database::pdo()->exec(Migrator::translate($sql));
    $x("CREATE TABLE job_applications (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(120) NOT NULL, phone VARCHAR(20) NOT NULL, email VARCHAR(190) NULL,
        neighborhood_id INT NULL, skills VARCHAR(255) NOT NULL DEFAULT '', experience VARCHAR(20) NULL, availability VARCHAR(20) NULL, has_whatsapp INT NOT NULL DEFAULT 1,
        message TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new', notes TEXT NULL, source VARCHAR(40) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL) {{ENGINE}}");
    $x('CREATE INDEX idx_job_applications_status ON job_applications (company_id, status, id)');

    $pid = Database::insert('permissions', ['name' => 'recruitment.manage', 'label' => 'Gérer le recrutement (candidatures)']);
    foreach (['admin', 'manager'] as $role) {
        $rid = Database::val('SELECT id FROM roles WHERE name = ?', [$role]);
        if ($rid) Database::insert('role_permissions', ['role_id' => $rid, 'permission_id' => $pid]);
    }
};
