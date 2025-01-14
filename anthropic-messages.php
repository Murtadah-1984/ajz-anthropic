<?php

namespace App\Services\Anthropic;

class MessageBuilder
{
    private array $messages = [];
    private ?string $system = null;
    private ?array $tools = null;
    private ?array $toolChoice = null;
    private int $maxTokens = 1024;
    private ?float $temperature = null;

    /**
     * Add a user message
     *
     * @param string|array $content Text or content blocks
     * @return self
     */
    public function user($content): self
    {
        $this->messages[] = [
            'role' => 'user',
            'content' => $content
        ];
        return $this;
    }

    /**
     * Add an assistant message
     *
     * @param string|array $content Text or content blocks
     * @return self
     */
    public function assistant($content): self
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $content
        ];
        return $this;
    }

    /**
     * Add system instructions
     *
     * @param string $system
     * @return self
     */
    public function system(string $system): self
    {
        $this->system = $system;
        return $this;
    }

    /**
     * Add an image to the last message
     *
     * @param string $base64Data
     * @param string $mediaType
     * @return self
     */
    public function withImage(string $base64Data, string $mediaType = 'image/jpeg'): self
    {
        $lastMessage = end($this->messages);
        if (!$lastMessage) {
            throw new \RuntimeException('Add a message before adding an image');
        }

        // Convert string content to array if needed
        if (is_string($lastMessage['content'])) {
            $lastMessage['content'] = [
                ['type' => 'text', 'text' => $lastMessage['content']]
            ];
        }

        // Add image content block
        $lastMessage['content'][] = [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $mediaType,
                'data' => $base64Data
            ]
        ];

        // Update the last message
        $this->messages[count($this->messages) - 1] = $lastMessage;
        return $this;
    }

    /**
     * Add tools that Claude can use
     *
     * @param array $tools Array of Tool objects
     * @return self
     */
    public function withTools(array $tools): self
    {
        $this->tools = array_map(function ($tool) {
            return [
                'name' => $tool->name,
                'description' => $tool->description,
                'input_schema' => $tool->input_schema
            ];
        }, $tools);
        return $this;
    }

    /**
     * Specify tool choice
     *
     * @param string|array $choice 'any', 'none', or specific tool config
     * @return self
     */
    public function withToolChoice($choice): self
    {
        if ($choice === 'any') {
            $this->toolChoice = ['type' => 'any'];
        } elseif ($choice === 'none') {
            $this->toolChoice = ['type' => 'none'];
        } else {
            $this->toolChoice = $choice;
        }
        return $this;
    }

    /**
     * Set max tokens
     *
     * @param int $maxTokens
     * @return self
     */
    public function maxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    /**
     * Set temperature
     *
     * @param float $temperature
     * @return self
     */
    public function temperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    /**
     * Build the message request payload
     *
     * @return array
     */
    public function build(): array
    {
        $payload = [
            'messages' => $this->messages,
            'max_tokens' => $this->maxTokens
        ];

        if ($this->system) {
            $payload['system'] = $this->system;
        }

        if ($this->tools) {
            $payload['tools'] = $this->tools;
        }

        if ($this->toolChoice) {
            $payload['tool_choice'] = $this->toolChoice;
        }

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        return $payload;
    }
}