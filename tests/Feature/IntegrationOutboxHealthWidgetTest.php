<?php

namespace Tests\Feature;

use App\Filament\Widgets\IntegrationOutboxHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class IntegrationOutboxHealthWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_exposes_outbox_delivery_health_stats(): void
    {
        $widget = new IntegrationOutboxHealth;
        $method = new ReflectionMethod(IntegrationOutboxHealth::class, 'getStats');
        $method->setAccessible(true);

        $stats = $method->invoke($widget);

        $this->assertCount(4, $stats);
    }
}
