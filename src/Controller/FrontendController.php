<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontendController extends AbstractController
{
    #[Route('/', name: 'frontend_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('frontend/home.html.twig');
    }

    #[Route('/app/login', name: 'frontend_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('frontend/login.html.twig');
    }

    #[Route('/app/events', name: 'frontend_events', methods: ['GET'])]
    public function events(): Response
    {
        return $this->render('frontend/events.html.twig');
    }

    #[Route('/app/reservations', name: 'frontend_reservations', methods: ['GET'])]
    public function reservations(): Response
    {
        return $this->render('frontend/reservations.html.twig');
    }

    #[Route('/app/admin', name: 'frontend_admin', methods: ['GET'])]
    public function admin(): Response
    {
        return $this->render('frontend/admin.html.twig');
    }
}
