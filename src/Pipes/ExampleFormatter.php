<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Pipes;

final class ExampleFormatter
{
    public function handle(SystemPromptConfig $config, \Closure $next): SystemPromptConfig
    {
        if (empty($config->examples)) {
            return $next($config);
        }

        $examplesString = "Examples:\n\n";

        foreach ($config->examples as $example) {
            $examplesString .= "Input:\n{$example['input']}\n\n";
            $examplesString .= "Output:\n{$example['output']}\n\n";
        }

        return $config->addComponent('examples', $examplesString);
    }
}
