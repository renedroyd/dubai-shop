<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('Dubai Shop')
            ->assertSee('Compra con estilo.')
            ->assertSee('Tecnología')
            ->assertSee('Pensado para ti, en cada paso.');
    }
}
