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
            ['data' => ['documentTypes' => ['RULES', 'SERVICES']]],
            $client->getInternalResponse()->toArray(),
        );
    }

    public function testDocumentSchemaTypes(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/graphql', ['query' => <<<'GRAPHQL'
            {
                document: __type(name: "Document") {
                    fields { name type { kind name ofType { name kind } } }
                }
                content: __type(name: "DocumentContent") {
                    fields { name type { kind name ofType { name kind } } }
                }
                query: __type(name: "Query") {
                    fields { name type { ofType { ofType { ofType { name kind } } } } }
                }
            }
            GRAPHQL]);

        self::assertResponseIsSuccessful();
        $schema = $client->getInternalResponse()->toArray()['data'];
        $fields = array_column($schema['document']['fields'], 'type', 'name');

        foreach (['id' => 'Int', 'type_id' => 'Int', 'slug' => 'String', 'created_at' => 'DateTime', 'updated_at' => 'DateTime'] as $field => $type) {
            self::assertSame(
                ['kind' => 'NON_NULL', 'name' => null, 'ofType' => ['name' => $type, 'kind' => 'SCALAR']],
                $fields[$field],
            );
        }
        self::assertSame(['kind' => 'ENUM', 'name' => 'DocumentType', 'ofType' => null], $fields['type']);

        $contentFields = array_column($schema['content']['fields'], 'type', 'name');
        self::assertSame(
            ['kind' => 'NON_NULL', 'name' => null, 'ofType' => ['name' => 'LocaleCode', 'kind' => 'SCALAR']],
            $contentFields['locale'],
        );

        $queries = array_column($schema['query']['fields'], 'type', 'name');
        self::assertSame(['name' => 'Document', 'kind' => 'OBJECT'], $queries['documentsList']['ofType']['ofType']['ofType']);
        self::assertSame(['name' => 'LocaleCode', 'kind' => 'SCALAR'], $queries['documentLocales']['ofType']['ofType']['ofType']);
    }

    public function testDocumentContentRejectsInvalidLocale(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/graphql', [
            'query' => 'query ($locale: LocaleCode!) { documentContent(document_id: 1, locale: $locale) { id } }',
            'variables' => ['locale' => 'invalid'],
        ]);

        self::assertResponseIsSuccessful();
        $response = $client->getInternalResponse()->toArray();
        self::assertArrayNotHasKey('data', $response);
        self::assertStringContainsString('Expected a two-letter document locale code.', $response['errors'][0]['message']);
    }

    public function testDocumentListsMatchRest(): void
    {
        if (!str_ends_with((string) parse_url($_SERVER['DATABASE_URL'] ?? '', PHP_URL_PATH), '_test')) {
            self::markTestSkipped('Set DATABASE_URL to a prepared database with a name ending in _test.');
        }

        $client = self::createClient();
        $expected = [];
        foreach (['documentsList' => '/docs', 'documentTypes' => '/docs/types', 'documentLocales' => '/docs/locales'] as $field => $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $expected[$field] = $client->getInternalResponse()->toArray()['data'];
            if ('documentsList' === $field) {
                foreach ($expected[$field] as &$document) {
                    foreach (['created_at', 'updated_at'] as $timestamp) {
                        $document[$timestamp] = (new \DateTimeImmutable($document[$timestamp], new \DateTimeZone('UTC')))
                            ->format('Y-m-d\TH:i:s');
                    }
                }
                unset($document);
            }
            if ('documentTypes' === $field) {
                $expected[$field] = array_map(strtoupper(...), $expected[$field]);
            }
        }

        $client->jsonRequest('POST', '/graphql', ['query' => <<<'GRAPHQL'
            {
                documentsList { id type_id slug created_at updated_at }
                documentTypes
                documentLocales
            }
            GRAPHQL]);

        self::assertResponseIsSuccessful();
        self::assertSame(['data' => $expected], $client->getInternalResponse()->toArray());
    }
}
