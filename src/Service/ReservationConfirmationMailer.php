<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class ReservationConfirmationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFrom,
    ) {
    }

    public function sendCreated(User $user, Reservation $reservation): bool
    {
        $recipient = $user->getEmail();
        if (!is_string($recipient) || '' === trim($recipient)) {
            return false;
        }

        $event = $reservation->getEvent();
        $eventTitle = $event?->getTitle() ?? 'Your event';

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($recipient)
            ->subject(sprintf('Reservation confirmed: %s', $eventTitle))
            ->htmlTemplate('emails/reservation_confirmation.html.twig')
            ->context([
                'user' => $user,
                'reservation' => $reservation,
                'event' => $event,
            ]);

        try {
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send reservation confirmation email.', [
                'reservation_id' => $reservation->getId(),
                'user_id' => $user->getId(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
