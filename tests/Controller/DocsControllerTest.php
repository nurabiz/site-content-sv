<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DocsControllerTest extends WebTestCase
{
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

        try {
            $client->request('GET', '/docs');
            self::assertResponseIsSuccessful();
            self::assertContains('crud-test', array_column($client->getInternalResponse()->toArray()['data'], 'translit'));

            $client->jsonRequest('PUT', $doc, ['type_id' => 1, 'translit' => 'crud-test-updated']);
            self::assertResponseIsSuccessful();
            $client->request('GET', $doc);
            self::assertSame('crud-test-updated', $client->getInternalResponse()->toArray()['data']['translit']);

            $client->jsonRequest('POST', $doc.'/locales', ['lang_id' => 'invalid', 'doc_content' => '']);
            self::assertResponseStatusCodeSame(422);

            $client->jsonRequest('POST', $doc.'/locales', ['lang_id' => 'en', 'doc_content' => 'Initial']);
            self::assertResponseStatusCodeSame(201);
            $locale = $client->getResponse()->headers->get('Location');

            $client->jsonRequest('POST', $doc.'/locales', ['lang_id' => 'EN', 'doc_content' => 'Duplicate']);
            self::assertResponseStatusCodeSame(409);

            $client->jsonRequest('PUT', $locale, ['lang_id' => 'uk', 'doc_content' => 'Updated']);
            self::assertResponseIsSuccessful();
            $client->request('GET', $locale);
            self::assertSame('Updated', $client->getInternalResponse()->toArray()['data']['doc_content']);
            $client->request('GET', $doc.'/locales');
            self::assertSame(['uk'], array_column($client->getInternalResponse()->toArray()['data'], 'lang_id'));

            $client->jsonRequest('POST', $doc.'/locales', ['lang_id' => 'en', 'doc_content' => 'English']);
            self::assertResponseStatusCodeSame(201);
            $client->request('GET', $doc);
            self::assertSame(['uk', 'en'], array_column($client->getInternalResponse()->toArray()['data']['locales'], 'lang_id'));
            $timestamps = array_column($client->getInternalResponse()->toArray()['data']['locales'], 'updated_at', 'lang_id');
            $client->jsonRequest('PUT', $doc, [
                'type_id' => 1,
                'translit' => 'crud-test-updated',
                'locales' => ['uk' => 'Updated', 'en' => 'english', 'de' => 'Deutsch'],
            ]);
            self::assertResponseIsSuccessful();
            $updated = array_column($client->getInternalResponse()->toArray()['data']['locales'], null, 'lang_id');
            self::assertSame($timestamps['uk'], $updated['uk']['updated_at']);
            self::assertGreaterThan($timestamps['en'], $updated['en']['updated_at']);
            self::assertSame('Deutsch', $updated['de']['doc_content']);
            $client->request('DELETE', $doc.'/locales/de');
            self::assertResponseStatusCodeSame(204);

            $client->request('GET', '/docs/translit/crud-test-updated/UK');
            self::assertResponseIsSuccessful();
            self::assertSame(['uk'], array_column($client->getInternalResponse()->toArray()['data']['locales'], 'lang_id'));
            $client->request('GET', '/docs/translit/crud-test-updated/de');
            self::assertResponseStatusCodeSame(404);
            $client->request('GET', '/docs/locales');
            self::assertSame(['en', 'uk'], $client->getInternalResponse()->toArray()['data']);

            $client->jsonRequest('PUT', $doc.'/locales/en', ['doc_content' => 'Updated English']);
            self::assertResponseIsSuccessful();
            self::assertSame('Updated English', $client->getInternalResponse()->toArray()['data']['doc_content']);
            $client->request('DELETE', $doc.'/locales/en');
            self::assertResponseStatusCodeSame(204);
            $client->request('GET', '/docs/translit/crud-test-updated/en');
            self::assertResponseStatusCodeSame(404);

            $client->request('DELETE', $locale);
            self::assertResponseStatusCodeSame(204);
            $client->request('GET', $locale);
            self::assertResponseStatusCodeSame(404);

            $client->jsonRequest('POST', $doc.'/locales', ['lang_id' => 'en', 'doc_content' => 'Remove all']);
            self::assertResponseStatusCodeSame(201);
            $client->request('DELETE', $doc.'/locales');
            self::assertResponseStatusCodeSame(204);
            $client->request('GET', $doc);
            self::assertSame([], $client->getInternalResponse()->toArray()['data']['locales']);

            $client->jsonRequest('POST', $doc.'/locales', ['lang_id' => 'uk', 'doc_content' => 'Cascade']);
            self::assertResponseStatusCodeSame(201);
            $localeId = $client->getInternalResponse()->toArray()['data']['id'];
        } finally {
            $client->request('DELETE', $doc);
            self::assertResponseStatusCodeSame(204);
        }

        $client->request('GET', $doc);
        self::assertResponseStatusCodeSame(404);
        self::assertFalse(self::getContainer()->get('doctrine.dbal.default_connection')->fetchOne(
            'SELECT id FROM documents_content WHERE id = ?',
            [$localeId],
        ));
    }
}
