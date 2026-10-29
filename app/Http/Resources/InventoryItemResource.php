<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'section_id' => $this->section_id,
            'category_id' => $this->category_id,
            'section' => ['id' => $this->section->id, 'name' => $this->section->name],
            'category' => ['id' => $this->category->id, 'name' => $this->category->name],
            'position' => $this->position,
            'serial' => $this->serial,
            'purchased_on' => $this->purchased_on?->format('Y-m-d'),
            'expires_on' => $this->expires_on?->format('Y-m-d'),
            'photo_url' => $this->photo_path
                ? route('items.photo', ['item' => $this->id], false).'?v='.sha1($this->photo_path)
                : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
