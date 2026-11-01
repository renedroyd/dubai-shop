<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        Category::query()->create([
            'name' => 'Tecnología',
            'slug' => 'tecnologia',
            'description' => 'Dispositivos y accesorios.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Dubai Shop')
            ->assertSee('Compra con estilo.')
            ->assertSee('Tecnología')
            ->assertSee('Pensado para ti, en cada paso.');
    }
}
