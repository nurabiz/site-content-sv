<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Controller;

use Overblog\GraphQLBundle\Controller\GraphController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GraphQlController extends AbstractController
{
    public function __invoke(
        Request         $request,
        GraphController $graphql
    ): Response
    {
        return $graphql->endpointAction($request);
    }
}
