<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Workspace;

use DateTime;

final class Workspace
{
    public string $id;
    public string $type = 'workspace';
    public string $name;
    public DateTime $created_at;
    public ?DateTime $archived_at;
    public string $display_color;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->created_at = new DateTime($data['created_at']);
        $this->archived_at = isset($data['archived_at']) ? new DateTime($data['archived_at']) : null;
        $this->display_color = $data['display_color'];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
