<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'name',
        'code',
        'description',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function assistants(): HasMany
    {
        return $this->hasMany(AIAssistant::class);
    }
}
