<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'division_id',
        'name',
        'code',
        'description',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}










