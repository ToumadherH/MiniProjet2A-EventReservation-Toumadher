<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ReservationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    #[Route('/api/events/{id}/reservations', name: 'api_reservations_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Event $event): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authenticated user not found.'], 401);
        }

        $eventId = $event->getId();
        if (null === $eventId) {
            return $this->json(['error' => 'Event id is required.'], 400);
        }

        $alreadyReserved = $this->reservationRepository->findActiveForUserAndEvent((int) $user->getId(), $eventId);
        if (null !== $alreadyReserved) {
            return $this->json(['error' => 'You already reserved this event.'], 409);
        }

        $reservedSeats = $this->reservationRepository->countActiveByEventId($eventId);
        if ($reservedSeats >= (int) $event->getSeats()) {
            return $this->json(['error' => 'Event is fully booked.'], 409);
        }

        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setEvent($event);
        $reservation->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        return $this->json($this->serializeReservation($reservation), 201);
    }

    #[Route('/api/reservations/me', name: 'api_reservations_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myReservations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authenticated user not found.'], 401);
        }

        $includeCancelled = 'true' === strtolower((string) $request->query->get('include_cancelled', 'false'));

        $items = $this->reservationRepository->findByUserId((int) $user->getId(), $includeCancelled);

        return $this->json([
            'items' => array_map(fn (Reservation $reservation): array => $this->serializeReservation($reservation), $items),
        ]);
    }

    #[Route('/api/reservations/{id}', name: 'api_reservations_cancel', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(Reservation $reservation): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authenticated user not found.'], 401);
        }

        $isOwner = $reservation->getUser()?->getId() === $user->getId();
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            return $this->json(['error' => 'You can only cancel your own reservation.'], 403);
        }

        if ($reservation->isCancelled()) {
            return $this->json(['error' => 'Reservation already cancelled.'], 409);
        }

        $reservation->setCancelledAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json(null, 204);
    }

    private function serializeReservation(Reservation $reservation): array
    {
        $event = $reservation->getEvent();

        return [
            'id' => $reservation->getId(),
            'createdAt' => $reservation->getCreatedAt()?->format(DATE_ATOM),
            'cancelledAt' => $reservation->getCancelledAt()?->format(DATE_ATOM),
            'status' => $reservation->isCancelled() ? 'cancelled' : 'active',
            'event' => null !== $event ? [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'date' => $event->getDate()?->format(DATE_ATOM),
                'location' => $event->getLocation(),
            ] : null,
        ];
    }
}
