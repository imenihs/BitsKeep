<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_path_redirects_to_components(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/components');
    }
}
