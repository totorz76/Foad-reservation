<?php

namespace App\Service;

use App\Repository\ReservationRepository;

class ReservationScheduler
{
    private const OPEN_HOUR = 8;
    private const CLOSE_HOUR = 19;
    private const MAX_DURATION_HOURS = 4;
    private const SLOT_INTERVAL = 30; // minutes

    public function __construct(
        private ReservationRepository $reservationRepository
    ) {}

    /* =====================================================
       VALIDATION COMPLETE D’UNE RÉSERVATION
    ===================================================== */

    public function validateReservation(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): void {

        // 1. Cohérence temporelle
        if ($end <= $start) {
            throw new \DomainException("La date de fin doit être après la date de début.");
        }

        $duration = $end->getTimestamp() - $start->getTimestamp();
        if ($duration > self::MAX_DURATION_HOURS * 3600) {
            throw new \DomainException("La durée maximale est de 4 heures.");
        }

        // 2. Pas dans le passé
        if ($start <= new \DateTimeImmutable()) {
            throw new \DomainException("Impossible de réserver dans le passé.");
        }

        // 3. Jour autorisé (lundi → samedi)
        if ($start->format('N') == 7) {
            throw new \DomainException("Fermé le dimanche.");
        }

        // 4. Horaires d’ouverture
        if (!$this->isWithinOpeningHours($start, $end)) {
            throw new \DomainException("En dehors des horaires d'ouverture (08h00-19h00).");
        }

        // 5. Alignement sur 30 minutes
        if (!$this->isHalfHourAligned($start, $end)) {
            throw new \DomainException("Les créneaux doivent être alignés sur 30 minutes.");
        }

        // 6. Conflit
        if ($this->hasConflict($start, $end)) {
            throw new \DomainException("Ce créneau est déjà réservé.");
        }
    }

    /* =====================================================
       HORAIRES D’OUVERTURE
    ===================================================== */

    private function isWithinOpeningHours(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): bool {

        $open = $start->setTime(self::OPEN_HOUR, 0);
        $close = $start->setTime(self::CLOSE_HOUR, 0);

        return $start >= $open && $end <= $close;
    }

    private function isHalfHourAligned(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): bool {

        return (
            $start->format('i') % self::SLOT_INTERVAL === 0 &&
            $end->format('i') % self::SLOT_INTERVAL === 0
        );
    }

    /* =====================================================
       DÉTECTION DES CONFLITS
    ===================================================== */

    public function hasConflict(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): bool {

        $conflicts = $this->reservationRepository
            ->createQueryBuilder('r')
            ->where('r.startAt < :end')
            ->andWhere('r.endAt > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        return count($conflicts) > 0;
    }

    /* =====================================================
       GÉNÉRATION DES CRÉNEAUX D’UNE JOURNÉE
    ===================================================== */

    public function generateDaySlots(\DateTimeImmutable $date): array
    {
        if ($date->format('N') == 7) {
            return []; // fermé le dimanche
        }

        $slots = [];

        $current = $date->setTime(self::OPEN_HOUR, 0);
        $close = $date->setTime(self::CLOSE_HOUR, 0);

        while ($current < $close) {

            $end = $current->modify('+'.self::SLOT_INTERVAL.' minutes');

            $slots[] = [
                'start' => $current,
                'end' => $end,
                'available' => !$this->hasConflict($current, $end)
            ];

            $current = $end;
        }

        return $slots;
    }

    /* =====================================================
       VÉRIFICATION D’UN CRÉNEAU
    ===================================================== */

    public function isBookable(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): bool {

        try {
            $this->validateReservation($start, $end);
            return true;
        } catch (\DomainException $e) {
            return false;
        }
    }
}

