<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DocumentsControllerTest extends WebTestCase
{
    public function testDocumentsWithCatalogLocalesCannotShareTranslit(): void
    {
        if (!str_ends_with((string) parse_url($_SERVER['DATABASE_URL'] ?? '', PHP_URL_PATH), '_test')) {
            self::markTestSkipped('Set DATABASE_URL to a prepared database with a name ending in _test.');
        }

        $client = self::createClient();
        $translit = 'unique-locale-'.bin2hex(random_bytes(6));
        $documents = [];

        try {
            foreach ([$translit, $translit.'-second'] as $slug) {
                $client->jsonRequest('POST', '/docs', ['type_id' => 1, 'translit' => $slug, 'locales' => ['en' => 'en']]);
                self::assertResponseStatusCodeSame(201);
                $documents[] = $client->getResponse()->headers->get('Location');
            }

            $client->jsonRequest('POST', '/docs', ['type_id' => 1, 'translit' => $translit, 'locales' => ['en' => 'Duplicate']]);
            self::assertResponseStatusCodeSame(409);

            $client->jsonRequest('PUT', $documents[1], ['type_id' => 1, 'translit' => $translit, 'locales' => ['en' => 'Duplicate']]);
            self::assertResponseStatusCodeSame(409);

            $client->request('GET', '/docs/'.$translit.'/en');
            self::assertResponseIsSuccessful();
            self::assertSame('en', $client->getInternalResponse()->toArray()['data']['locales'][0]['doc_content']);

            $renamed = $translit.'-renamed';
            $client->jsonRequest('PUT', $documents[0], ['type_id' => 1, 'translit' => $renamed]);
            self::assertResponseIsSuccessful();
            $client->request('GET', '/docs/'.$renamed.'/en');
            self::assertResponseIsSuccessful();
            $client->request('GET', '/docs/'.$translit.'/en');
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

        $client->jsonRequest('POST', '/docs', ['type_id' => 999999, 'translit' => 'invalid']);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('POST', '/docs', ['type_id' => 1, 'translit' => 'crud-test']);
        self::assertResponseStatusCodeSame(201);
        $doc = $client->getResponse()->headers->get('Location');
        $createdDocument = $client->getInternalResponse()->toArray()['data'];
        self::assertNotEmpty($createdDocument['created_at']);
        self::assertSame($createdDocument['created_at'], $createdDocument['updated_at']);
        self::assertSame(['en' => '', 'ru' => '', 'uk' => ''], array_column($createdDocument['locales'], 'doc_content', 'lang_id'));

        try {
            $client->request('GET', '/docs');
            self::assertResponseIsSuccessful();
            self::assertContains('crud-test', array_column($client->getInternalResponse()->toArray()['data'], 'translit'));

            $client->jsonRequest('PUT', $doc, ['type_id' => 1, 'translit' => 'crud-test-updated']);
            self::assertResponseIsSuccessful();
            $client->request('GET', $doc);
            $updatedDocument = $client->getInternalResponse()->toArray()['data'];
            self::assertSame('crud-test-updated', $updatedDocument['translit']);
            self::assertSame($createdDocument['created_at'], $updatedDocument['created_at']);
            self::assertGreaterThan($createdDocument['updated_at'], $updatedDocument['updated_at']);

            $documentData = ['type_id' => 1, 'translit' => 'crud-test-updated'];
            $client->jsonRequest('PUT', $doc, $documentData + ['locales' => ['invalid' => '']]);
            self::assertResponseStatusCodeSame(422);

            $localeContents = ['en' => 'English', 'ru' => 'Русский', 'uk' => 'Українська'];
            $client->jsonRequest('PUT', $doc, $documentData + ['locales' => $localeContents]);
            self::assertResponseIsSuccessful();
            self::assertSame($localeContents, array_column($client->getInternalResponse()->toArray()['data']['locales'], 'doc_content', 'lang_id'));

            $client->request('GET', $doc);
            self::assertResponseIsSuccessful();
            $document = $client->getInternalResponse()->toArray()['data'];
            self::assertSame($localeContents, array_column($document['locales'], 'doc_content', 'lang_id'));
            self::assertSame(array_column($createdDocument['locales'], 'id'), array_column($document['locales'], 'id'));

            $client->request('GET', '/docs/crud-test-updated/UK');
            self::assertResponseIsSuccessful();
            self::assertSame(['uk' => 'Українська'], array_column($client->getInternalResponse()->toArray()['data']['locales'], 'doc_content', 'lang_id'));
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
