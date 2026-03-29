<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EventController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly HtmlSanitizerInterface $appSanitizer,
    ) {
    }

    #[Route('/api/events', name: 'api_events_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 10)));

        $query = $request->query->get('q');
        $location = $request->query->get('location');

        $dateFrom = $this->parseDate($request->query->get('date_from'));
        $dateTo = $this->parseDate($request->query->get('date_to'));

        if (null === $dateFrom && null !== $request->query->get('date_from')) {
            return $this->json(['error' => 'Invalid date_from format. Use ISO 8601.'], 400);
        }

        if (null === $dateTo && null !== $request->query->get('date_to')) {
            return $this->json(['error' => 'Invalid date_to format. Use ISO 8601.'], 400);
        }

        $events = $this->eventRepository->findPaginatedWithFilters(
            $page,
            $limit,
            is_string($query) ? $query : null,
            is_string($location) ? $location : null,
            $dateFrom,
            $dateTo,
        );

        $total = $this->eventRepository->countWithFilters(
            is_string($query) ? $query : null,
            is_string($location) ? $location : null,
            $dateFrom,
            $dateTo,
        );

        $data = array_map(fn (Event $event): array => $this->serializeEvent($event), $events);

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'items' => $data,
        ]);
    }

    #[Route('/api/events/{id}', name: 'api_events_show', methods: ['GET'])]
    public function show(Event $event): JsonResponse
    {
        return $this->json($this->serializeEvent($event));
    }

    #[Route('/api/events', name: 'api_events_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $required = ['title', 'description', 'date', 'location', 'seats'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                return $this->json(['error' => sprintf('Missing field: %s', $field)], 400);
            }
        }

        $date = $this->parseDate($payload['date']);
        if (null === $date) {
            return $this->json(['error' => 'Invalid date format. Use ISO 8601.'], 400);
        }

        $seats = (int) $payload['seats'];
        if ($seats <= 0) {
            return $this->json(['error' => 'Seats must be greater than 0.'], 400);
        }

        $event = new Event();
        $event->setTitle($this->sanitizeText((string) $payload['title']));
        $event->setDescription($this->sanitizeText((string) $payload['description']));
        $event->setDate($date);
        $event->setLocation($this->sanitizeText((string) $payload['location']));
        $event->setSeats($seats);
        $event->setImage(isset($payload['image']) ? (string) $payload['image'] : null);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->json($this->serializeEvent($event), 201);
    }

    #[Route('/api/events/{id}', name: 'api_events_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Event $event, Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (array_key_exists('title', $payload)) {
            $event->setTitle($this->sanitizeText((string) $payload['title']));
        }

        if (array_key_exists('description', $payload)) {
            $event->setDescription($this->sanitizeText((string) $payload['description']));
        }

        if (array_key_exists('date', $payload)) {
            $date = $this->parseDate($payload['date']);
            if (null === $date) {
                return $this->json(['error' => 'Invalid date format. Use ISO 8601.'], 400);
            }

            $event->setDate($date);
        }

        if (array_key_exists('location', $payload)) {
            $event->setLocation($this->sanitizeText((string) $payload['location']));
        }

        if (array_key_exists('seats', $payload)) {
            $seats = (int) $payload['seats'];
            $reserved = $this->reservationRepository->countActiveByEventId((int) $event->getId());

            if ($seats < $reserved) {
                return $this->json([
                    'error' => sprintf('Seats cannot be below active reservations count (%d).', $reserved),
                ], 400);
            }

            $event->setSeats($seats);
        }

        if (array_key_exists('image', $payload)) {
            $event->setImage(null !== $payload['image'] ? (string) $payload['image'] : null);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeEvent($event));
    }

    #[Route('/api/events/{id}', name: 'api_events_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Event $event): JsonResponse
    {
        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return $this->json(null, 204);
    }

    private function serializeEvent(Event $event): array
    {
        $eventId = $event->getId();
        $reservedSeats = null !== $eventId ? $this->reservationRepository->countActiveByEventId($eventId) : 0;

        return [
            'id' => $eventId,
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'date' => $event->getDate()?->format(DATE_ATOM),
            'location' => $event->getLocation(),
            'seats' => $event->getSeats(),
            'reservedSeats' => $reservedSeats,
            'availableSeats' => max(0, (int) $event->getSeats() - $reservedSeats),
            'image' => $event->getImage(),
        ];
    }

    private function decodeJson(Request $request): array|JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON payload.'], 400);
        }

        if (!is_array($payload)) {
            return $this->json(['error' => 'JSON object expected.'], 400);
        }

        return $payload;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function sanitizeText(string $value): string
    {
        return trim($this->appSanitizer->sanitize($value));
    }
}
