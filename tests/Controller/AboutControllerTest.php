<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

final class AboutControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        // Routes are locale-prefixed (app_about => /fr/qui-est-adem); generate the
        // real URL from the route name rather than hardcoding a path.
        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');
        $url = $router->generate('app_about');
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }
}
