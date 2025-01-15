<?php

namespace Database\Factories;

use App\Models\AssistantRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class AssistantRoleFactory extends Factory
{
    protected $model = AssistantRole::class;

    public function definition(): array
    {
        return [
            'role_name' => $this->faker->unique()->word(),
            'xml_config' => $this->generateSampleXmlConfig(),
            'xml_output' => '<?xml version="1.0"?><outputs></outputs>',
            'metadata' => [
                'version' => '1.0',
                'created_by' => 'system'
            ],
            'is_active' => true,
            'last_used_at' => Carbon::now(),
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false
        ]);
    }

    public function withCustomRole(string $roleName): self
    {
        return $this->state(fn (array $attributes) => [
            'role_name' => $roleName
        ]);
    }

    private function generateSampleXmlConfig(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <context name="project_type">Test Project</context>
    <context name="tech_stack">PHP</context>
    <guidelines>Write clean code</guidelines>
    <example>
        <input>Test input</input>
        <output>Test output</output>
    </example>
    <best_practices>
        <practice name="quality">Write tests</practice>
    </best_practices>
</config>
XML;
    }
}
