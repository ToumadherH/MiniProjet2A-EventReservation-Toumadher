<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProfileController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'id' => method_exists($user, 'getId') ? $user->getId() : null,
            'username' => method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null,
            'email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
            'firstName' => method_exists($user, 'getFirstName') ? $user->getFirstName() : null,
            'lastName' => method_exists($user, 'getLastName') ? $user->getLastName() : null,
            'passkeyEnabled' => method_exists($user, 'getPasskeyHash') ? null !== $user->getPasskeyHash() : false,
            'roles' => method_exists($user, 'getRoles') ? $user->getRoles() : [],
        ]);
    }
}
