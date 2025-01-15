<?php

declare(strict_types=1);

namespace App\Services\Message;

final class RequestCounts
{
    public int $processing;
    public int $succeeded;
    public int $errored;
    public int $canceled;
    public int $expired;

    public function __construct(array $data)
    {
        $this->processing = $data['processing'];
        $this->succeeded = $data['succeeded'];
        $this->errored = $data['errored'];
        $this->canceled = $data['canceled'];
        $this->expired = $data['expired'];
    }
}
