<?php

namespace App\Controller;

use App\Service\ReservationScheduler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{

    public function __construct(
        private ReservationScheduler $scheduler
    ) {}
    #[Route('/calendar/{date}', name: 'app_calendar')]
    public function index(string $date): Response
    {
        // Convertir la date string en DateTimeImmutable
        $day = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$day) {
            throw $this->createNotFoundException("Date invalide");
        }

        // Générer les créneaux de la journée
        $slots = $this->scheduler->generateDaySlots($day);

        return $this->render('calendar/index.html.twig', [
            'date' => $day,
            'slots' => $slots,
        ]);
    }
}
