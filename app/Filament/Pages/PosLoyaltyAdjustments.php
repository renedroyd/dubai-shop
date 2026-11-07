<?php

namespace App\Filament\Pages;

use App\Models\PosLoyaltyEntry;
use App\Models\PosLoyaltyWallet;
use App\Models\User;
use App\Services\Pos\PosLoyaltyService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PosLoyaltyAdjustments extends Page
{
    protected static string | \\BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.pages.pos-loyalty-adjustments';

    public string $customerSearch = '';
    public ?int $customerUserId = null;
    public string $customerName = '';
    public string $adjustmentCurrency = 'CUP';
    public string $adjustmentPoints = '';
    public string $adjustmentReason = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAdminPermission('pos_loyalty.adjust') ?? false;
    }

    public static function getNavigationGroup(): string | \\UnitEnum | null { return __('Punto de venta'); }
    public static function getNavigationLabel(): string { return __('Ajustes de puntos'); }
    public function getTitle(): string { return __('Ajustes manuales de fidelización'); }

    public function customerMatches(): Collection
    {
        $term = trim($this->customerSearch);
        if (mb_strlen($term) < 2) return collect();

        return User::query()
            ->where('is_admin', false)
            ->where('admin_access_enabled', false)
            ->where(fn (Builder $query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
            ->orderBy('name')->limit(8)->get(['id', 'name', 'email']);
    }

    public function selectCustomer(int $userId): void
    {
        $customer = User::query()->whereKey($userId)
            ->where('is_admin', false)->where('admin_access_enabled', false)
            ->firstOrFail(['id', 'name']);
        $this->customerUserId = $customer->id;
        $this->customerName = $customer->name;
        $this->customerSearch = $customer->name;
    }

    public function clearCustomer(): void
    {
        $this->customerUserId = null;
        $this->customerName = '';
        $this->customerSearch = '';
    }

    public function customerWallets(): Collection
    {
        if (! $this->customerUserId) return collect();

        return PosLoyaltyWallet::query()->where('user_id', $this->customerUserId)
            ->orderBy('currency')->get()->each(function (PosLoyaltyWallet $wallet): void {
                $wallet->setAttribute('available_points', $wallet->availablePoints());
                $wallet->setAttribute('expiring_points', $wallet->expiringPointsWithin(30));
            });
    }

    public function recentEntries(): Collection
    {
        if (! $this->customerUserId) return collect();

        return PosLoyaltyEntry::query()->whereHas('wallet', fn (Builder $query) => $query->where('user_id', $this->customerUserId))
            ->with(['wallet', 'order', 'actor'])->latest()->limit(20)->get();
    }

    public function submitAdjustment(): void
    {
        $this->validate([
            'customerUserId' => ['required', 'integer', 'exists:users,id'],
            'adjustmentCurrency' => ['required', 'in:CUP,USD,EUR'],
            'adjustmentPoints' => ['required', 'integer', 'not_in:0'],
            'adjustmentReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        app(PosLoyaltyService::class)->adjustWallet(
            (int) $this->customerUserId,
            $this->adjustmentCurrency,
            (int) $this->adjustmentPoints,
            $this->adjustmentReason,
            (int) auth()->id(),
        );

        $this->adjustmentPoints = '';
        $this->adjustmentReason = '';

        Notification::make()->success()->title(__('Ajuste de puntos registrado'))->body(__('El movimiento quedó auditado en el libro de fidelización.'))->send();
    }
}
