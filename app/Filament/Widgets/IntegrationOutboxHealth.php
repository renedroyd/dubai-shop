<?php

namespace App\Filament\Widgets;

use App\Models\IntegrationEventOutbox;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;
use Throwable;

class IntegrationOutboxHealth extends BaseWidget
{
    protected static ?int $sort = 9;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Integración con SocialPublicator';

    protected ?string $description = 'Estado de entrega de eventos de catálogo e inventario.';

    protected function getStats(): array
    {
        try {
            if (! Schema::hasTable('integration_event_outbox')) {
                return [
                    Stat::make('Outbox', 'No disponible')
                        ->description('La tabla de integración no existe')
                        ->descriptionIcon('heroicon-m-minus-circle')
                        ->color('gray'),
                ];
            }

            $pendingQuery = IntegrationEventOutbox::query()
                ->whereNull('delivered_at')
                ->whereNull('failed_at');
            $pending = (clone $pendingQuery)->count();
            $locked = (clone $pendingQuery)
                ->whereNotNull('locked_at')
                ->where('locked_at', '>', now()->subMinutes(10))
                ->count();
            $overdue = (clone $pendingQuery)
                ->whereNotNull('available_at')
                ->where('available_at', '<=', now()->subMinutes(10))
                ->where(function ($query): void {
                    $query->whereNull('locked_at')->orWhere('locked_at', '<=', now()->subMinutes(10));
                })
                ->count();
            $failed = IntegrationEventOutbox::query()->whereNotNull('failed_at')->count();
            $delivered = IntegrationEventOutbox::query()
                ->whereNotNull('delivered_at')
                ->where('delivered_at', '>=', now()->subDay())
                ->count();
            $oldestPending = (clone $pendingQuery)->oldest('created_at')->value('created_at');

            $oldestDescription = $oldestPending
                ? 'Evento más antiguo: '.\Illuminate\Support\Carbon::parse($oldestPending)->diffForHumans()
                : 'Sin eventos pendientes';

            return [
                Stat::make('Eventos pendientes', number_format($pending))
                    ->description($oldestDescription.' · '.$overdue.' atrasados')
                    ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-inbox')
                    ->color($overdue > 0 ? 'warning' : 'success'),
                Stat::make('En tránsito', number_format($locked))
                    ->description('Eventos reservados por workers')
                    ->descriptionIcon('heroicon-m-arrow-path')
                    ->color($locked > 0 ? 'info' : 'gray'),
                Stat::make('Entregados / 24 h', number_format($delivered))
                    ->description('Confirmados por SocialPublicator')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),
                Stat::make('Fallidos', number_format($failed))
                    ->description('Requieren revisión o reencolado manual')
                    ->descriptionIcon($failed > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                    ->color($failed > 0 ? 'danger' : 'success'),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                Stat::make('Integración', 'Error')
                    ->description('No se pudo consultar el outbox de SocialPublicator')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color('danger'),
            ];
        }
    }
}
