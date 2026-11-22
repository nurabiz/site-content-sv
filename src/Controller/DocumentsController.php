<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Controller;

use NuraBiz\SiteContent\Dto\DocDto;
use NuraBiz\SiteContent\Service\DocumentManager;
use NuraBiz\SiteContent\Repository\DocumentsRepository;
use NuraBiz\SiteContent\Repository\DocumentTypesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class DocumentsController extends AbstractController
{
    public function __construct(private readonly DocumentManager $docs)
    {
    }

    public function list(): JsonResponse
    {
        return $this->json(['data' => $this->docs->list()]);
    }

    public function types(DocumentTypesRepository $types): JsonResponse
    {
        return $this->json(['data' => $types->list()]);
    }

    public function locales(DocumentsRepository $contents): JsonResponse
    {
        return $this->json(['data' => $contents->listLanguages()]);
    }

    public function getByTranslit(string $translit, string $langId): JsonResponse
    {
        return $this->json(['data' => $this->docs->getByTranslitAndLocale($translit, $langId)]);
    }

    public function create(#[MapRequestPayload(acceptFormat: 'json')] DocDto $dto): JsonResponse
    {
        $doc = $this->docs->save($dto);

        return $this->json(['data' => $doc->toArray()], Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('docs_get', ['id' => $doc->getId()]),
        ]);
    }

    public function get(int $id): JsonResponse
    {
        return $this->json(['data' => $this->docs->getWithLocales($id)]);
    }

    public function update(int $id, #[MapRequestPayload(acceptFormat: 'json')] DocDto $dto): JsonResponse
    {
        return $this->json(['data' => $this->docs->save($dto, $id)->toArray()]);
    }

    public function delete(int $id): Response
    {
        $this->docs->delete($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
