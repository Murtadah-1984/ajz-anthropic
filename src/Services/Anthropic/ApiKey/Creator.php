<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Services\Anthropic\ApiKey;

use DateTime;

final class Creator
{
    public string $id;
    public string $type;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->type = $data['type'];
    }
}
