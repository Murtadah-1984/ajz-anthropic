<?php

declare(strict_types=1);

/**
 * @OA\Schema(
 *     schema="AI Facade",
 *     title="Anthropic Facade",
 *     description="Laravel Facade for accessing Anthropic services",
 *     @OA\Property(
 *
 *     )
 * )
 */

namespace Ajz\Anthropic\Facades;

use Illuminate\Support\Facades\Facade;
use Ajz\Anthropic\AIAgents\Sessions\BrainstormSession;


class AI extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\AIManager::class;
    }

    /**
     * Get an AI agent instance
     */
    public static function agent(string $type): mixed
    {
        return static::getFacadeRoot()->agent($type);
    }

    /**
     * Get an AI team instance
     */
    public static function team(string $teamId): mixed
    {
        return static::getFacadeRoot()->team($teamId);
    }

    /**
     * Get the message broker instance
     */
    public static function broker(): mixed
    {
        return static::getFacadeRoot()->broker();
    }

    public static function brainstorm(string $topic, array $options = []): BrainstormSession
    {
        return static::getFacadeRoot()->startBrainstorming($topic, $options);
    }

    public static function startSession(string $type, array $options = []): BaseSession
    {
        return static::getFacadeRoot()->createSession($type, $options);
    }
}
