<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Pipes;

final class BestPracticesEnforcer
{
    public function handle(SystemPromptConfig $config, \Closure $next): SystemPromptConfig
    {
        $practicesString = "Best Practices to Follow:\n";

        foreach ($config->bestPractices as $category => $practices) {
            $practicesString .= "{$category}:\n";
            foreach ($practices as $practice) {
                $practicesString .= "- {$practice}\n";
            }
        }

        return $config->addComponent('best_practices', $practicesString);
    }
}
