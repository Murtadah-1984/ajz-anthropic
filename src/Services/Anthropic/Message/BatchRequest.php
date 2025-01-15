<?php

declare(strict_types=1);

namespace App\Services\Message;

final class BatchRequest
{
    public string $custom_id;
    public array $params;

    public function __construct(string $custom_id, array $params)
    {
        $this->custom_id = $custom_id;
        $this->params = $params;
    }
}
