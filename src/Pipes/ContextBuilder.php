<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Pipes;

final class ContextBuilder
{
    public function handle(SystemPromptConfig $config, \Closure $next): SystemPromptConfig
    {
        $contextString = "Context and Background:\n";

        foreach ($config->context as $key => $value) {
            $contextString .= "- {$key}: {$value}\n";
        }

        return $config->addComponent('context', $contextString);
    }
}
