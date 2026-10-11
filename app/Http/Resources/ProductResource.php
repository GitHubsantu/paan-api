<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'image' => Media::url($this->image),
            'image_url' => Media::url($this->image),
            'thumbnail_url' => Media::thumbnailUrl($this->image),
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'tag' => $this->tag,
            'prep_minutes' => $this->prep_minutes,
            'ingredients' => $this->ingredients ?? [],
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'addons' => AddonResource::collection($this->whenLoaded('addons')),
        ];
    }
}
