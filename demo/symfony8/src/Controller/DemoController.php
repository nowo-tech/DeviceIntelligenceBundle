<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Demo home: loads the Device Intelligence browser client.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DemoController extends AbstractController
{
    #[Route('/', name: 'homepage_default', methods: ['GET'])]
    public function homeDefault(): Response
    {
        return $this->redirectToRoute('homepage', ['_locale' => 'en'], Response::HTTP_FOUND);
    }

    #[Route('/{_locale}', name: 'homepage', requirements: ['_locale' => 'en|es'], methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('demo/home.html.twig');
    }
}
