<?php

namespace Ajz\Anthropic\Exceptions;

use Exception;
use Throwable;

class InvalidConfigurationException extends Exception
{
    /**
     * The configuration key that caused the error
     *
     * @var string|null
     */
    protected ?string $configKey = null;

    /**
     * The validation errors
     *
     * @var array
     */
    protected array $validationErrors = [];

    /**
     * Create a new InvalidConfigurationException instance.
     *
     * @param string $message
     * @param string|null $configKey
     * @param array $validationErrors
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = "",
        ?string $configKey = null,
        array $validationErrors = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->configKey = $configKey;
        $this->validationErrors = $validationErrors;
    }

    /**
     * Get the configuration key that caused the error.
     *
     * @return string|null
     */
    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Create an instance for a missing configuration value.
     *
     * @param string $key
     * @return static
     */
    public static function missingConfig(string $key): self
    {
        return new static(
            "Missing required configuration value for '{$key}'",
            $key,
            ['required' => "The {$key} configuration is required"]
        );
    }

    /**
     * Create an instance for an invalid configuration value.
     *
     * @param string $key
     * @param string $reason
     * @return static
     */
    public static function invalidConfig(string $key, string $reason): self
    {
        return new static(
            "Invalid configuration value for '{$key}': {$reason}",
            $key,
            ['invalid' => $reason]
        );
    }

    /**
     * Create an instance for an invalid agent configuration.
     *
     * @param string $agentName
     * @param string $reason
     * @return static
     */
    public static function invalidAgentConfig(string $agentName, string $reason): self
    {
        return new static(
            "Invalid agent configuration for '{$agentName}': {$reason}",
            "agents.{$agentName}",
            ['invalid_agent' => $reason]
        );
    }

    /**
     * Create an instance for an invalid team configuration.
     *
     * @param string $teamName
     * @param string $reason
     * @return static
     */
    public static function invalidTeamConfig(string $teamName, string $reason): self
    {
        return new static(
            "Invalid team configuration for '{$teamName}': {$reason}",
            "teams.{$teamName}",
            ['invalid_team' => $reason]
        );
    }

    /**
     * Create an instance for an invalid type.
     *
     * @param string $key
     * @param string $expectedType
     * @param string $actualType
     * @return static
     */
    public static function invalidType(string $key, string $expectedType, string $actualType): self
    {
        return new static(
            "Invalid type for '{$key}': expected {$expectedType}, got {$actualType}",
            $key,
            ['invalid_type' => "Expected {$expectedType}, got {$actualType}"]
        );
    }

    /**
     * Create an instance for an invalid value range.
     *
     * @param string $key
     * @param mixed $min
     * @param mixed $max
     * @param mixed $actual
     * @return static
     */
    public static function invalidRange(string $key, $min, $max, $actual): self
    {
        return new static(
            "Invalid value for '{$key}': must be between {$min} and {$max}, got {$actual}",
            $key,
            ['invalid_range' => "Must be between {$min} and {$max}, got {$actual}"]
        );
    }

    /**
     * Get a readable error message including all validation errors.
     *
     * @return string
     */
    public function getReadableMessage(): string
    {
        $message = $this->getMessage();

        if (!empty($this->validationErrors)) {
            $message .= "\nValidation errors:\n";
            foreach ($this->validationErrors as $key => $error) {
                $message .= "- {$error}\n";
            }
        }

        return $message;
    }
}
