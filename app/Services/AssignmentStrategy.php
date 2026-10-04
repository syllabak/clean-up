<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Point d'extension pour l'affectation automatique (activée plus tard via le paramètre auto_assign).
 * Une stratégie reçoit la réservation et la liste des agents candidats (de l'équipe réservée,
 * compétents, présents) et renvoie l'identifiant de l'agent retenu, ou null pour laisser l'équipe sans agent.
 */
interface AssignmentStrategy
{
    /** @param array[] $candidates lignes staff enrichies de 'missions_today' */
    public function pick(array $booking, array $candidates): ?int;
}
