<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Events;

use App\Models\AssistantRole;
use Illuminate\Foundation\Events\Dispatchable;

final class AssistantOutputGenerated
{
    use Dispatchable;

    public function __construct(
        public readonly AssistantRole $role,
        public readonly string $output,
        public readonly int $feedbackScore
    ) {}
}
