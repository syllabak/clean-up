<?php
declare(strict_types=1);
namespace App\Services;

/** Choisit l'agent qui a le moins de missions ce jour-là (à égalité : plus petit identifiant). */
final class LeastLoadedStrategy implements AssignmentStrategy
{
    public function pick(array $booking, array $candidates): ?int
    {
        if (!$candidates) return null;
        usort($candidates, fn($a, $b) => [$a['missions_today'], $a['id']] <=> [$b['missions_today'], $b['id']]);
        return (int) $candidates[0]['id'];
    }
}
