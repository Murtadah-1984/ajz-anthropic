<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\ApiKey;

final class ApiKey
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
