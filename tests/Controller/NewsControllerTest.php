<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NewsControllerTest extends WebTestCase
{
    public function testRejectsUnsupportedLocale(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/news', [
            'valid' => true,
            'userid' => 17,
            'start_at' => '2026-11-26 12:00:00',
            'locales' => ['ua' => ['header' => 'Новина']],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRestNewsListRejectsNegativeOffset(): void
    {
        $client = self::createClient();
        $client->request('GET', '/news?offset=-1');

        self::assertResponseStatusCodeSame(422);
    }

    public function testNewsListRejectsNegativeOffset(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/graphql', ['query' => '{ newsList(offset: -1) { id } }']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'offset must be non-negative',
            $client->getInternalResponse()->toArray()['errors'][0]['message'],
        );
    }

    public function testNewsLifecycle(): void
    {
        if (!str_ends_with((string) parse_url($_SERVER['DATABASE_URL'] ?? '', PHP_URL_PATH), '_test')) {
            self::markTestSkipped('Set DATABASE_URL to a prepared database with a name ending in _test.');
        }

        $client = self::createClient();
        $news = ['valid' => true, 'userid' => 17, 'start_at' => '2026-11-26 12:00:00'];
        $client->jsonRequest('POST', '/news', $news + [
            'image' => 'news.png',
            'updated_by' => 17,
            'locales' => ['uk' => ['header' => 'Новина', 'text' => 'Текст']],
        ]);

        self::assertResponseStatusCodeSame(201);
        $path = $client->getResponse()->headers->get('Location');
        $created = $client->getInternalResponse()->toArray()['data'];

        try {
            self::assertSame('news.png', $created['image']);
            $locales = array_column($created['locales'], null, 'locale');
            self::assertSame(['en', 'ru', 'uk'], array_keys($locales));
            self::assertNull($locales['en']['header']);

            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSame($created, $client->getInternalResponse()->toArray()['data']);

            $client->request('GET', $path.'/uk');
            self::assertResponseIsSuccessful();
            self::assertSame([$locales['uk']], $client->getInternalResponse()->toArray()['data']['locales']);

            $client->request('GET', $path.'/de');
            self::assertResponseStatusCodeSame(404);

            $client->jsonRequest('POST', '/graphql', [
                'query' => 'query ($id: Int!) { news(id: $id) { locales { locale } } newsLocalized(id: $id, locale: "uk") { locales { locale header text } } }',
                'variables' => ['id' => $created['id']],
            ]);
            self::assertResponseIsSuccessful();
            self::assertSame(['data' => [
                'news' => ['locales' => [['locale' => 'en'], ['locale' => 'ru'], ['locale' => 'uk']]],
                'newsLocalized' => ['locales' => [['locale' => 'uk', 'header' => 'Новина', 'text' => 'Текст']]],
            ]], $client->getInternalResponse()->toArray());

            $client->jsonRequest('PUT', $path, $news + [
                'image' => 'updated.png',
                'updated_by' => 42,
                'locales' => [
                    'en' => ['header' => null],
                    'uk' => ['header' => 'Оновлена новина'],
                ],
            ]);
            self::assertResponseIsSuccessful();
            $updated = $client->getInternalResponse()->toArray()['data'];
            self::assertSame('updated.png', $updated['image']);
            $updatedLocales = array_column($updated['locales'], null, 'locale');
            self::assertSame($locales['en'], $updatedLocales['en']);
            self::assertSame($locales['ru'], $updatedLocales['ru']);
            self::assertSame('Оновлена новина', $updatedLocales['uk']['header']);
            self::assertSame('Текст', $updatedLocales['uk']['text']);
            self::assertSame(42, $updatedLocales['uk']['updated_by']);
        } finally {
            $client->request('DELETE', $path);
            self::assertResponseStatusCodeSame(204);
        }

        $connection = self::getContainer()->get(Connection::class);
        self::assertSame(0, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM news_content WHERE news_id = ?',
            [$created['id']],
        ));
        $client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
        $client->request('DELETE', $path);
        self::assertResponseStatusCodeSame(404);
    }
}
