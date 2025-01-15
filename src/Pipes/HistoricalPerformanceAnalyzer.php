<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Pipes;

final class HistoricalPerformanceAnalyzer
{
    public function handle(SystemPromptConfig $config, \Closure $next)
    {
        // This pipe is handled in finalizePrompt to access the role model
        return $next($config);
    }
}
