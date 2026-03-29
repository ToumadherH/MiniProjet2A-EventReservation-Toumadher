<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ApiCsrfSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->requiresCsrfValidation($request)) {
            return;
        }

        $headerToken = (string) $request->headers->get('X-CSRF-Token', '');
        if ('' === $headerToken) {
            throw new AccessDeniedHttpException('Missing CSRF token.');
        }

        $valid = $this->csrfTokenManager->isTokenValid(new CsrfToken('api_action', $headerToken));
        if (!$valid) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    private function requiresCsrfValidation(Request $request): bool
    {
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return false;
        }

        if ('/api/login_check' === $path) {
            return false;
        }

        return in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
