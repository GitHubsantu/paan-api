<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => Media::url($this->image),
            'image_url' => Media::url($this->image),
            'sort_order' => $this->sort_order,
        ];
    }
}
