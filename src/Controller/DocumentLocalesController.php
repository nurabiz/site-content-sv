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

final class DocumentLocalesController extends AbstractController
{
    public function __construct(private readonly DocumentManager $docs)
    {
    }

    public function list(int $docId): JsonResponse
    {
        return $this->json(['data' => $this->docs->listLocales($docId)]);
    }

    public function create(int $docId, #[MapRequestPayload(acceptFormat: 'json')] DocContentDto $dto): JsonResponse
    {
        $data = $this->docs->saveLocale($docId, $dto)->toArray();

        return $this->json(['data' => $data], Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('doc_locales_get', ['docId' => $docId, 'id' => $data['id']]),
        ]);
    }

    public function deleteAll(int $docId): Response
    {
        $this->docs->deleteAllContent($docId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function updateByLanguage(int $docId, string $langId, #[MapRequestPayload(acceptFormat: 'json')] DocContentUpdateDto $dto): JsonResponse
    {
        return $this->json(['data' => $this->docs->updateContent($docId, $langId, $dto->doc_content)->toArray()]);
    }

    public function deleteByLanguage(int $docId, string $langId): Response
    {
        $this->docs->deleteContent($docId, $langId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function get(int $docId, int $id): JsonResponse
    {
        return $this->json(['data' => $this->docs->getLocale($docId, $id)->toArray()]);
    }

    public function update(int $docId, int $id, #[MapRequestPayload(acceptFormat: 'json')] DocContentDto $dto): JsonResponse
    {
        return $this->json(['data' => $this->docs->saveLocale($docId, $dto, $id)->toArray()]);
    }

    public function delete(int $docId, int $id): Response
    {
        $this->docs->deleteLocale($docId, $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
