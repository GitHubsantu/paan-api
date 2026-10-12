<?php

namespace App\Models;

use Database\Factories\AppReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppRelease extends Model
{
    /** @use HasFactory<AppReleaseFactory> */
    use HasFactory;

    protected $fillable = ['platform', 'version_name', 'version_code', 'release_notes', 'apk_path', 'apk_original_name', 'apk_size', 'sha256', 'min_android', 'is_published', 'is_current', 'published_at', 'uploaded_by'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_current' => 'boolean', 'published_at' => 'datetime', 'apk_size' => 'integer', 'download_count' => 'integer'];
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    #[Scope]
    protected function current(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
