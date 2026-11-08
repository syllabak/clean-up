<?php
// Consentement marketing daté et traçable : quand, par quel moyen, et retrait éventuel.
return [
"ALTER TABLE customers ADD COLUMN marketing_consent_at DATETIME NULL",
"ALTER TABLE customers ADD COLUMN marketing_optout_at DATETIME NULL",
"ALTER TABLE customers ADD COLUMN marketing_consent_source VARCHAR(30) NULL",
];
