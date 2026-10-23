<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_root_redirects_to_the_services_site(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/services');
    }
}
