<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\SoftDeletes;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;
use Illuminate\\Database\\Eloquent\\Relations\\HasOne;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'sku', 'short_description',
        'description', 'price_amount', 'compare_at_price_amount', 'currency',
        'status', 'is_featured', 'is_visible', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'compare_at_price_amount' => 'integer',
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
        return $this->hasOne(Inventory::class)->whereNull('product_variant_id');
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
