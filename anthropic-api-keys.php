<?php

namespace Ajz\Anthropic\Services\Anthropic\Organization;

use DateTime;

class Creator
{
    public string $id;
    public string $type;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->type = $data['type'];
    }
}

class ApiKey
{
    public string $id;
    public string $type = 'api_key';
    public string $name;
    public ?string $workspace_id;
    public DateTime $created_at;
    public Creator $created_by;
    public ?string $partial_key_hint;
    public string $status;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';

    public const VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED
    ];

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->workspace_id = $data['workspace_id'];
        $this->created_at = new DateTime($data['created_at']);
        $this->created_by = new Creator($data['created_by']);
        $this->partial_key_hint = $data['partial_key_hint'];
        $this->status = $data['status'];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}

class ApiKeyList
{
    /** @var ApiKey[] */
    public array $data;
    public bool $has_more;
    public ?string $first_id;
    public ?string $last_id;

    public function __construct(array $data)
    {
        $this->data = array_map(fn($key) => new ApiKey($key), $data['data']);
        $this->has_more = $data['has_more'];
        $this->first_id = $data['first_id'];
        $this->last_id = $data['last_id'];
    }
}

