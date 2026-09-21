<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_root_redirect_destination(): void
    {
        $response = $this->get('/');

        dump($response->headers->get('Location'));

        $response->assertRedirect();
    }
}
