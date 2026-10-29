<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'quantity',
        'section_id',
        'category_id',
        'position',
        'serial',
        'purchased_on',
        'expires_on',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'section_id' => 'integer',
            'category_id' => 'integer',
            'purchased_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
