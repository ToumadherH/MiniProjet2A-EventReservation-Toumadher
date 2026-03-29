<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PasskeyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
    ) {
    }

    #[Route('/api/passkey/create', name: 'api_passkey_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authenticated user not found.'], 401);
        }

        $passkey = $this->generatePasskey();
        $hash = password_hash($passkey, PASSWORD_ARGON2ID);

        if (!is_string($hash)) {
            return $this->json(['error' => 'Unable to generate passkey.'], 500);
        }

        $user->setPasskeyHash($hash);
        $this->entityManager->flush();

        return $this->json([
            'passkey' => $passkey,
            'message' => 'Passkey generated successfully. Save it now, it will not be shown again.',
        ]);
    }

    #[Route('/api/passkey/login', name: 'api_passkey_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $username = isset($payload['username']) ? trim((string) $payload['username']) : '';
        $passkey = isset($payload['passkey']) ? trim((string) $payload['passkey']) : '';

        if ('' === $username || '' === $passkey) {
            return $this->json(['error' => 'Username and passkey are required.'], 400);
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        $hash = $user->getPasskeyHash();
        if (!is_string($hash) || '' === $hash) {
            return $this->json(['error' => 'No passkey configured for this account.'], 401);
        }

        if (!password_verify($passkey, $hash)) {
            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        return $this->json([
            'token' => $this->jwtTokenManager->create($user),
        ]);
    }

    private function generatePasskey(): string
    {
        $raw = strtoupper(bin2hex(random_bytes(8)));

        return implode('-', str_split($raw, 4));
    }
}
