<?php

namespace App\Controller;

use App\Service\ReservationScheduler;
use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservation')]
final class ReservationController extends AbstractController
{

    public function __construct(
        private ReservationScheduler $scheduler
    ) {}

    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservation = new Reservation();

        // 🔹 Pré-remplir si startAt/endAt dans l'URL
        $startAt = $request->query->get('startAt');
        $endAt = $request->query->get('endAt');

        if ($startAt) {
            $reservation->setStartAt(new \DateTimeImmutable($startAt));
        }
        if ($endAt) {
            $reservation->setEndAt(new \DateTimeImmutable($endAt));
        }

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->scheduler->validateReservation(
                    $reservation->getStartAt(),
                    $reservation->getEndAt()
                );

                $entityManager->persist($reservation);
                $entityManager->flush();

                return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/reservation/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->scheduler->validateReservation(
                    $reservation->getStartAt(),
                    $reservation->getEndAt(),
                    $reservation->getId() // pour ignorer conflit avec soi-même
                );

                $entityManager->flush();

                $this->addFlash('success', 'Réservation mise à jour !');

                return $this->redirectToRoute('app_reservation_index');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reservation/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reservation);
            $entityManager->flush();
            $this->addFlash('success', 'Réservation supprimée !');
        }

        return $this->redirectToRoute('app_reservation_index');
    }


    #[Route('/reservation/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
