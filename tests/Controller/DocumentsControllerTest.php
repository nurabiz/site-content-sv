<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DocumentsControllerTest extends WebTestCase
{
    public function testDocumentsWithCatalogLocalesCannotShareSlug(): void
    {
        if (!str_ends_with((string) parse_url($_SERVER['DATABASE_URL'] ?? '', PHP_URL_PATH), '_test')) {
            self::markTestSkipped('Set DATABASE_URL to a prepared database with a name ending in _test.');
        }

        $client = self::createClient();
        $slug = 'unique-locale-'.bin2hex(random_bytes(6));
        $documents = [];

        try {
            foreach ([$slug, $slug.'-second'] as $slug) {
                $client->jsonRequest('POST', '/docs', ['type_id' => 1, 'slug' => $slug, 'locales' => ['en' => 'en']]);
                self::assertResponseStatusCodeSame(201);
                $documents[] = $client->getResponse()->headers->get('Location');
            }

            $client->jsonRequest('POST', '/docs', ['type_id' => 1, 'slug' => $slug, 'locales' => ['en' => 'Duplicate']]);
            self::assertResponseStatusCodeSame(409);

            $client->jsonRequest('PUT', $documents[1], ['type_id' => 1, 'slug' => $slug, 'locales' => ['en' => 'Duplicate']]);
            self::assertResponseStatusCodeSame(409);

            $client->request('GET', '/docs/'.$slug.'/en');
            self::assertResponseIsSuccessful();
            self::assertSame('en', $client->getInternalResponse()->toArray()['data']['locales'][0]['doc_content']);

            $renamed = $slug.'-renamed';
            $client->jsonRequest('PUT', $documents[0], ['type_id' => 1, 'slu' => $renamed]);
            self::assertResponseIsSuccessful();
            $client->request('GET', '/docs/'.$renamed.'/en');
            self::assertResponseIsSuccessful();
            $client->request('GET', '/docs/'.$slug.'/en');
            self::assertResponseStatusCodeSame(404);
        } finally {
            foreach ($documents as $document) {
                $client->request('DELETE', $document);
                self::assertResponseStatusCodeSame(204);
            }
        }
    }

    public function testDocumentAndLocaleLifecycle(): void
    {
        if (!str_ends_with((string) parse_url($_SERVER['DATABASE_URL'] ?? '', PHP_URL_PATH), '_test')) {
            self::markTestSkipped('Set DATABASE_URL to a prepared database with a name ending in _test.');
        }

        $client = self::createClient();
        $client->request('GET', '/docs/types');
        self::assertResponseIsSuccessful();
        self::assertContains('rules', $client->getInternalResponse()->toArray()['data']);

        $client->jsonRequest('POST', '/docs', ['type_id' => 999999, 'slug' => 'invalid']);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('POST', '/docs', ['type_id' => 1, 'slug' => 'crud-test', 'updated_by' => 17]);
        self::assertResponseStatusCodeSame(201);
        $doc = $client->getResponse()->headers->get('Location');
        $createdDocument = $client->getInternalResponse()->toArray()['data'];
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $createdDocument['created_at']);
        self::assertSame($createdDocument['created_at'], $createdDocument['updated_at']);
        self::assertSame(['en' => '', 'ru' => '', 'uk' => ''], array_column($createdDocument['locales'], 'doc_content', 'locale'));

        try {
            self::assertSame([17, 17, 17], array_column($createdDocument['locales'], 'updated_by'));
            $client->request('GET', '/docs');
            self::assertResponseIsSuccessful();
            self::assertContains('crud-test', array_column($client->getInternalResponse()->toArray()['data'], 'slug'));

            $client->jsonRequest('PUT', $doc, ['type_id' => 1, 'slug' => 'crud-test-updated']);
            self::assertResponseIsSuccessful();
            $client->request('GET', $doc);
            $updatedDocument = $client->getInternalResponse()->toArray()['data'];
            self::assertSame('crud-test-updated', $updatedDocument['slug']);
            self::assertSame($createdDocument['created_at'], $updatedDocument['created_at']);
            self::assertGreaterThanOrEqual($createdDocument['updated_at'], $updatedDocument['updated_at']);

            $documentData = ['type_id' => 1, 'slug' => 'crud-test-updated'];
            $client->jsonRequest('PUT', $doc, $documentData + ['locales' => ['invalid' => '']]);
            self::assertResponseStatusCodeSame(422);

            $localeContents = ['en' => 'English', 'ru' => 'Русский', 'uk' => 'Українська'];
            $client->jsonRequest('PUT', $doc, $documentData + ['locales' => $localeContents, 'updated_by' => 42]);
            self::assertResponseIsSuccessful();
            self::assertSame($localeContents, array_column($client->getInternalResponse()->toArray()['data']['locales'], 'doc_content', 'locale'));

            $client->jsonRequest('PUT', $doc, $documentData + ['locales' => ['en' => 'English'], 'updated_by' => 99]);
            self::assertResponseIsSuccessful();

            $client->request('GET', $doc);
            self::assertResponseIsSuccessful();
            $document = $client->getInternalResponse()->toArray()['data'];
            self::assertSame($localeContents, array_column($document['locales'], 'doc_content', 'locale'));
            self::assertSame([42, 42, 42], array_column($document['locales'], 'updated_by'));
            self::assertSame(array_column($createdDocument['locales'], 'id'), array_column($document['locales'], 'id'));

            $client->request('GET', '/docs/crud-test-updated/UK');
            self::assertResponseIsSuccessful();
            self::assertSame(['uk' => 'Українська'], array_column($client->getInternalResponse()->toArray()['data']['locales'], 'doc_content', 'locale'));
        } finally {
            $client->request('DELETE', $doc);
            self::assertResponseStatusCodeSame(204);
        }

        $client->request('GET', $doc);
        self::assertResponseStatusCodeSame(404);
        self::assertFalse(self::getContainer()->get('doctrine.dbal.default_connection')->fetchOne(
            'SELECT id FROM documents_content WHERE doc_id = ?',
            [$createdDocument['id']],
        ));
    }
}
