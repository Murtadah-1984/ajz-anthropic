<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssistantRole;

class AssistantRoleSeeder extends Seeder
{
    public function run(): void
    {
        $developerConfig = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <context name="project_type">Web Application Development</context>
    <context name="tech_stack">PHP 8.2, Laravel 10.x</context>

    <guidelines>Write clean, maintainable code</guidelines>
    <guidelines>Follow SOLID principles</guidelines>
    <guidelines>Include proper error handling</guidelines>

    <example>
        <input>Create a user registration service</input>
        <output>
            <?php

            declare(strict_types=1);

            namespace App\Services;

            class UserRegistrationService
            {
                // Implementation
            }
        </output>
    </example>

    <output_format name="code_style">PSR-12</output_format>
    <output_format name="documentation">PHPDoc format</output_format>

    <best_practices>
        <practice name="code_quality">Write unit tests for all business logic</practice>
        <practice name="code_quality">Use type hints and return types</practice>
        <practice name="security">Validate all input</practice>
        <practice name="security">Use prepared statements for database queries</practice>
    </best_practices>
</config>
XML;

        $architectConfig = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <context name="system_type">Enterprise Microservices Architecture</context>
    <context name="scale">High-traffic, globally distributed system</context>

    <guidelines>Design scalable and maintainable systems</guidelines>
    <guidelines>Consider security at every layer</guidelines>
    <guidelines>Plan for failure and recovery</guidelines>

    <example>
        <input>Design a payment processing system</input>
        <output>
            System Components:
            1. Payment Gateway Service
            2. Transaction Manager
            3. Notification Service

            Communication: Event-driven using Apache Kafka
            Database: Distributed PostgreSQL cluster
        </output>
    </example>

    <output_format name="diagrams">Include system architecture diagrams</output_format>
    <output_format name="documentation">Architecture Decision Records (ADR)</output_format>

    <best_practices>
        <practice name="scalability">Design for horizontal scaling</practice>
        <practice name="reliability">Implement circuit breakers</practice>
        <practice name="monitoring">Include observability from day one</practice>
    </best_practices>
</config>
XML;

        AssistantRole::create([
            'role_name' => 'developer',
            'xml_config' => $developerConfig,
            'xml_output' => '<?xml version="1.0"?><outputs></outputs>'
        ]);

        AssistantRole::create([
            'role_name' => 'architect',
            'xml_config' => $architectConfig,
            'xml_output' => '<?xml version="1.0"?><outputs></outputs>'
        ]);
    }
}
