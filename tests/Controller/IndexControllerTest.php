<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IndexControllerTest extends WebTestCase
{
    public function testIndexDisplaysCurrentDate(): void
    {
        $client = self::createClient();
        $before = time();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $client->getResponse()->getContent());
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertGreaterThanOrEqual($before, $date->getTimestamp());
        self::assertLessThanOrEqual(time(), $date->getTimestamp());
    }
}
