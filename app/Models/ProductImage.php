<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'alt_text', 'sort_order', 'is_primary'];

    protected static function booted(): void
    {
        static::saved(function (self $image): void {
            DB::table('products')->where('id', $image->product_id)->update([
                'external_revision' => DB::raw('external_revision + 1'),
                'updated_at' => now(),
            ]);
        });

        static::deleted(function (self $image): void {
            DB::table('products')->where('id', $image->product_id)->update([
                'external_revision' => DB::raw('external_revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
