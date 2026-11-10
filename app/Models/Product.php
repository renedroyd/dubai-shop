<?php

namespace App\Models;

use App\Models\Concerns\TransactionalModelWrites;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes, TransactionalModelWrites;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'sku', 'barcode', 'short_description',
        'description', 'price_amount', 'compare_at_price_amount', 'currency',
        'status', 'is_featured', 'is_visible', 'meta_title', 'meta_description',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            $product->external_id ??= (string) Str::uuid();
        });

        static::updating(function (self $product): void {
            $product->external_revision = ((int) $product->external_revision) + 1;
        });
    }

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'compare_at_price_amount' => 'integer',
            'external_revision' => 'integer',
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class)->whereNull('product_variant_id')->whereHas('warehouse', fn ($query) => $query->where('is_primary', true));
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class)->whereNull('product_variant_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('is_visible', true);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_amount / 100, 2).' '.$this->currency;
    }
}
