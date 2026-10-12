<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppReleaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'version_name' => $this->version_name, 'version_code' => $this->version_code, 'release_notes' => $this->release_notes, 'size' => $this->apk_size, 'sha256' => $this->sha256, 'min_android' => $this->min_android, 'is_published' => $this->is_published, 'is_current' => $this->is_current, 'published_at' => $this->published_at?->toISOString(), 'download_count' => $this->download_count, 'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => $this->uploadedBy?->name), 'created_at' => $this->created_at?->toISOString()];
    }
}
