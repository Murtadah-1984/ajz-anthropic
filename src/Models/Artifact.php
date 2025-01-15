<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Artifact extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'content',
        'mime_type',
        'size',
        'path',
        'metadata',
        'artifactable_type',
        'artifactable_id',
        'user_id',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'size' => 'integer',
        'metadata' => 'json',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'path',
    ];

    /**
     * Get the model's validation rules.
     *
     * @return array
     */
    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'content' => ['required_without:path', 'string'],
            'mime_type' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:0'],
            'path' => ['required_without:content', 'string', 'max:1024'],
            'metadata' => ['array'],
            'artifactable_type' => ['required', 'string', 'max:255'],
            'artifactable_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * Get the model's searchable fields.
     *
     * @return array
     */
    public static function searchableFields(): array
    {
        return [
            'name',
            'type',
            'mime_type',
        ];
    }

    /**
     * Get the model's filterable fields.
     *
     * @return array
     */
    public static function filterableFields(): array
    {
        return [
            'type',
            'mime_type',
            'artifactable_type',
            'user_id',
        ];
    }

    /**
     * Get the model's sortable fields.
     *
     * @return array
     */
    public static function sortableFields(): array
    {
        return [
            'name',
            'type',
            'size',
            'created_at',
            'expires_at',
        ];
    }

    /**
     * Get the owning artifactable model.
     *
     * @return MorphTo
     */
    public function artifactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user that created the artifact.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the artifact's storage disk.
     *
     * @return string
     */
    public function getDisk(): string
    {
        return config('anthropic.storage.artifacts.disk', 'local');
    }

    /**
     * Get the artifact's storage path.
     *
     * @return string
     */
    public function getStoragePath(): string
    {
        return config('anthropic.storage.artifacts.path', 'artifacts');
    }

    /**
     * Get the artifact's full path.
     *
     * @return string
     */
    public function getFullPath(): string
    {
        return $this->getStoragePath() . '/' . $this->path;
    }

    /**
     * Get the artifact's URL.
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        if (!$this->path) {
            return null;
        }

        return Storage::disk($this->getDisk())->url($this->getFullPath());
    }

    /**
     * Get the artifact's content.
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        if ($this->content) {
            return $this->content;
        }

        if (!$this->path) {
            return null;
        }

        return Storage::disk($this->getDisk())->get($this->getFullPath());
    }

    /**
     * Store the artifact's content.
     *
     * @param string $content
     * @return bool
     */
    public function storeContent(string $content): bool
    {
        $path = $this->generatePath();
        $success = Storage::disk($this->getDisk())->put(
            $this->getStoragePath() . '/' . $path,
            $content
        );

        if ($success) {
            $this->path = $path;
            $this->content = null;
            $this->size = strlen($content);
            return $this->save();
        }

        return false;
    }

    /**
     * Generate a unique path for the artifact.
     *
     * @return string
     */
    protected function generatePath(): string
    {
        $extension = pathinfo($this->name, PATHINFO_EXTENSION);
        return sprintf(
            '%s/%s/%s.%s',
            date('Y/m/d'),
            $this->artifactable_type,
            md5(uniqid($this->name, true)),
            $extension
        );
    }

    /**
     * Check if the artifact has expired.
     *
     * @return bool
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Delete the artifact's file.
     *
     * @return bool
     */
    public function deleteFile(): bool
    {
        if (!$this->path) {
            return true;
        }

        return Storage::disk($this->getDisk())->delete($this->getFullPath());
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        parent::booted();

        static::deleting(function ($artifact) {
            $artifact->deleteFile();
        });
    }
}
