<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Controller;

use NuraBiz\SiteContent\Dto\NewsDto;
use NuraBiz\SiteContent\Dto\NewsListQuery;
use NuraBiz\SiteContent\Repository\NewsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/news', name: 'news_', format: 'json')]
final class NewsController extends AbstractController
{
    public function __construct(private readonly NewsRepository $news)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        NewsListQuery $query = new NewsListQuery(),
    ): JsonResponse {
        return $this->json(['data' => $this->news->list(
            $query->limit,
            $query->offset,
            $query->valid,
            $query->client_type,
        )]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] NewsDto $dto): JsonResponse
    {
        $news = $this->news->save($dto);

        return $this->json(['data' => $news], Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('news_get', ['id' => $news['id']]),
        ]);
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => '[0-9]+'], methods: ['GET'])]
    #[Route('/{id}/{locale}', name: 'get_localized', requirements: ['id' => '[0-9]+', 'locale' => '[A-Za-z]{2}'], methods: ['GET'])]
    public function get(int $id, ?string $locale = null): JsonResponse
    {
        $news = $this->news->find($id, $locale) ?? throw $this->createNotFoundException('News not found.');

        return $this->json(['data' => $news]);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '[0-9]+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload(acceptFormat: 'json')] NewsDto $dto): JsonResponse
    {
        $news = $this->news->save($dto, $id) ?? throw $this->createNotFoundException('News not found.');

        return $this->json(['data' => $news]);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '[0-9]+'], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        if (!$this->news->delete($id)) {
            throw $this->createNotFoundException('News not found.');
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
