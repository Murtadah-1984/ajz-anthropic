<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\Streaming;

final class Message
{
    public string $id;
    public string $type;
    public string $role;
    public string $model;
    public array $content;
    public ?string $stopReason;
    public ?string $stopSequence;
    public array $usage;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->role = $data['role'];
        $this->model = $data['model'];
        $this->content = $data['content'];
        $this->stopReason = $data['stop_reason'];
        $this->stopSequence = $data['stop_sequence'];
        $this->usage = $data['usage'];
    }
}
