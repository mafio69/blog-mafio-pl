<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    public function testPostIndexRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/posts');

        $this->assertResponseRedirects('/login');
    }

    public function testPostNewRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/posts/new');

        $this->assertResponseRedirects('/login');
    }
}
