<?php
// Formules : badge « populaire » et liste de ce qui est inclus (une ligne par élément).
return [
"ALTER TABLE service_prices ADD COLUMN popular TINYINT NOT NULL DEFAULT 0",
"ALTER TABLE service_prices ADD COLUMN includes TEXT NULL",
];
