<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    use SoftDeletes, HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
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
        'deleted_at',
    ];

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()}) && $model->incrementing) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Get the attributes that have been changed since the last sync.
     *
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = parent::getDirty();

        foreach ($this->casts as $key => $cast) {
            if (array_key_exists($key, $dirty) && $cast === 'json') {
                $dirty[$key] = json_decode($dirty[$key], true);
            }
        }

        return $dirty;
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        foreach ($this->casts as $key => $cast) {
            if (array_key_exists($key, $array) && $cast === 'json') {
                $array[$key] = json_decode($array[$key], true);
            }
        }

        return $array;
    }

    /**
     * Get the model's validation rules.
     *
     * @return array
     */
    public static function validationRules(): array
    {
        return [];
    }

    /**
     * Get the model's validation messages.
     *
     * @return array
     */
    public static function validationMessages(): array
    {
        return [];
    }

    /**
     * Get the model's searchable fields.
     *
     * @return array
     */
    public static function searchableFields(): array
    {
        return [];
    }

    /**
     * Get the model's filterable fields.
     *
     * @return array
     */
    public static function filterableFields(): array
    {
        return [];
    }

    /**
     * Get the model's sortable fields.
     *
     * @return array
     */
    public static function sortableFields(): array
    {
        return [];
    }

    /**
     * Scope a query to only include active records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include records created between dates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCreatedBetween($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to search records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        $searchableFields = static::searchableFields();

        return $query->where(function ($query) use ($search, $searchableFields) {
            foreach ($searchableFields as $field) {
                $query->orWhere($field, 'LIKE', "%{$search}%");
            }
        });
    }

    /**
     * Scope a query to filter records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query, array $filters)
    {
        $filterableFields = static::filterableFields();

        foreach ($filters as $field => $value) {
            if (in_array($field, $filterableFields) && !is_null($value)) {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Scope a query to sort records.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @param string $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSort($query, string $field, string $direction = 'asc')
    {
        $sortableFields = static::sortableFields();

        if (in_array($field, $sortableFields)) {
            return $query->orderBy($field, $direction);
        }

        return $query;
    }
}
