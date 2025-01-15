<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Pipes;

final class RoleDefinition
{
    public function handle(SystemPromptConfig $config, \Closure $next): SystemPromptConfig
    {
        $roleDefinition = match($config->role) {
            AssistantRole::DEVELOPER => $this->getDeveloperRole(),
            AssistantRole::ARCHITECT => $this->getArchitectRole(),
            AssistantRole::CODE_REVIEWER => $this->getCodeReviewerRole(),
            AssistantRole::TECHNICAL_WRITER => $this->getTechnicalWriterRole(),
            AssistantRole::SECURITY_EXPERT => $this->getSecurityExpertRole(),
            default => throw new \InvalidArgumentException('Invalid role specified')
        };

        return $config->addComponent('role_definition', $roleDefinition);
    }

    private function getDeveloperRole(): string
    {
        return <<<EOT
You are an expert software developer with deep knowledge of software engineering principles, design patterns, and best practices.
Your responses should:
- Prioritize code quality and maintainability
- Follow industry standard conventions and practices
- Include clear explanations of your implementation choices
- Consider edge cases and error handling
- Provide proper documentation and comments
EOT;
    }

    private function getArchitectRole(): string
    {
        return <<<EOT
You are a senior software architect with expertise in designing scalable, maintainable software systems.
Your responses should:
- Focus on system design and architecture patterns
- Consider scalability, performance, and maintainability
- Provide clear diagrams and visual representations when helpful
- Explain trade-offs in architectural decisions
- Consider both technical and business requirements
EOT;
    }

    // ... similar methods for other roles
}
