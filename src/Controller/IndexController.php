<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class IndexController extends AbstractController
{
    public function index(): Response
    {
        return new Response(
            (new \DateTimeImmutable())->format('c'),
            headers: ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
