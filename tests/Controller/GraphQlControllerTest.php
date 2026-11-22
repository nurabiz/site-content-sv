<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GraphQlControllerTest extends WebTestCase
{
    public function testDocumentTypes(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/graphql', ['query' => '{ documentTypes }']);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['data' => ['documentTypes' => ['rules', 'services']]],
            $client->getInternalResponse()->toArray(),
        );
    }

    public function testDocumentListsMatchRest(): void
    {
        if (!str_ends_with((string) parse_url($_SERVER['DATABASE_URL'] ?? '', PHP_URL_PATH), '_test')) {
            self::markTestSkipped('Set DATABASE_URL to a prepared database with a name ending in _test.');
        }

        $client = self::createClient();
        $expected = [];
        foreach (['documents' => '/docs', 'documentTypes' => '/docs/types', 'documentLocales' => '/docs/locales'] as $field => $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $expected[$field] = $client->getInternalResponse()->toArray()['data'];
        }

        $client->jsonRequest('POST', '/graphql', ['query' => <<<'GRAPHQL'
            {
                documents { id type_id translit created_at updated_at }
                documentTypes
                documentLocales
            }
            GRAPHQL]);

        self::assertResponseIsSuccessful();
        self::assertSame(['data' => $expected], $client->getInternalResponse()->toArray());
    }
}
