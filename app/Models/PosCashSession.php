<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCashSession extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'pos_terminal_id', 'opened_by', 'closed_by', 'status', 'currency',
        'opening_cash_amount', 'expected_cash_amount', 'counted_cash_amount',
        'difference_amount', 'opening_note', 'closing_note', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_cash_amount' => 'integer',
            'expected_cash_amount' => 'integer',
            'counted_cash_amount' => 'integer',
            'difference_amount' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function terminal(): BelongsTo { return $this->belongsTo(PosTerminal::class, 'pos_terminal_id'); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function movements(): HasMany { return $this->hasMany(PosCashMovement::class, 'pos_cash_session_id'); }
    public function orders(): HasMany { return $this->hasMany(Order::class, 'pos_cash_session_id'); }
    public function refunds(): HasMany { return $this->hasMany(PosRefund::class, 'pos_cash_session_id'); }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->closed_at === null;
    }
}