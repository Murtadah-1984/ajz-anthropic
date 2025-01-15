<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Pipes;

final class OutputFormatting
{
    public function handle(SystemPromptConfig $config, \Closure $next): SystemPromptConfig
    {
        $formatString = "Output Format:\n";

        foreach ($config->outputFormat as $key => $format) {
            $formatString .= "- {$key}: {$format}\n";
        }

        return $config->addComponent('output_format', $formatString);
    }
}
