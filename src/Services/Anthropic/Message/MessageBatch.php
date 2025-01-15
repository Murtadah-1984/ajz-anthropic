<?php

declare(strict_types=1);

namespace App\Services\Message;

use DateTime;
use Generator;
use JsonStreamingParser\Listener\InMemoryListener;
use JsonStreamingParser\Parser;

final class MessageBatch
{
    public string $id;
    public string $type = 'message_batch';
    public string $processing_status;
    public RequestCounts $request_counts;
    public ?DateTime $ended_at;
    public DateTime $created_at;
    public DateTime $expires_at;
    public ?DateTime $archived_at;
    public ?DateTime $cancel_initiated_at;
    public ?string $results_url;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->processing_status = $data['processing_status'];
        $this->request_counts = new RequestCounts($data['request_counts']);
        $this->ended_at = isset($data['ended_at']) ? new DateTime($data['ended_at']) : null;
        $this->created_at = new DateTime($data['created_at']);
        $this->expires_at = new DateTime($data['expires_at']);
        $this->archived_at = isset($data['archived_at']) ? new DateTime($data['archived_at']) : null;
        $this->cancel_initiated_at = isset($data['cancel_initiated_at']) ? new DateTime($data['cancel_initiated_at']) : null;
        $this->results_url = $data['results_url'] ?? null;
    }
}
