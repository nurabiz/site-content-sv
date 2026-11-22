<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Controller;

use NuraBiz\SiteContent\Dto\DocContentDto;
use NuraBiz\SiteContent\Dto\DocContentUpdateDto;
use NuraBiz\SiteContent\Service\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/docs/{docId}/locales', name: 'doc_locales_', requirements: ['docId' => Requirement::DIGITS, 'id' => Requirement::DIGITS], format: 'json')]
final class DocLocalesController extends AbstractController
{
    public function __construct(private readonly DocumentManager $docs)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $docId): JsonResponse
    {
        return $this->json(['data' => $this->docs->listLocales($docId)]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(int $docId, #[MapRequestPayload(acceptFormat: 'json')] DocContentDto $dto): JsonResponse
    {
        $data = $this->docs->saveLocale($docId, $dto)->toArray();

        return $this->json(['data' => $data], Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('doc_locales_get', ['docId' => $docId, 'id' => $data['id']]),
        ]);
    }

    #[Route('', name: 'delete_all', methods: ['DELETE'])]
    public function deleteAll(int $docId): Response
    {
        $this->docs->deleteAllContent($docId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{langId}', name: 'update_by_language', requirements: ['langId' => '[A-Za-z]{2}'], methods: ['PUT'])]
    public function updateByLanguage(int $docId, string $langId, #[MapRequestPayload(acceptFormat: 'json')] DocContentUpdateDto $dto): JsonResponse
    {
        return $this->json(['data' => $this->docs->updateContent($docId, $langId, $dto->doc_content)->toArray()]);
    }

    #[Route('/{langId}', name: 'delete_by_language', requirements: ['langId' => '[A-Za-z]{2}'], methods: ['DELETE'])]
    public function deleteByLanguage(int $docId, string $langId): Response
    {
        $this->docs->deleteContent($docId, $langId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $docId, int $id): JsonResponse
    {
        return $this->json(['data' => $this->docs->getLocale($docId, $id)->toArray()]);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $docId, int $id, #[MapRequestPayload(acceptFormat: 'json')] DocContentDto $dto): JsonResponse
    {
        return $this->json(['data' => $this->docs->saveLocale($docId, $dto, $id)->toArray()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $docId, int $id): Response
    {
        $this->docs->deleteLocale($docId, $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
