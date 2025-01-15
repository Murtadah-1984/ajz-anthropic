<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Pipes;

final class TaskGuidelines
{
    public function handle(SystemPromptConfig $config, \Closure $next): SystemPromptConfig
    {
        $guidelinesString = "Task Guidelines:\n";

        foreach ($config->guidelines as $guideline) {
            $guidelinesString .= "- {$guideline}\n";
        }

        return $config->addComponent('guidelines', $guidelinesString);
    }
}
