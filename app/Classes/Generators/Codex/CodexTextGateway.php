<?php

declare(strict_types=1);

namespace App\Classes\Generators\Codex;

use App\Support\Integrations\Payload;
use Generator;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Ai\Attributes\CacheInstructions;
use Laravel\Ai\Attributes\CacheToolDefinitions;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\ToolChoice;
use LogicException;

readonly class CodexTextGateway implements StepTextGateway
{
    public function __construct(private CodexProcess $process) {}

    /**
     * @param array<Message> $messages
     * @param array<Tool> $tools
     * @param array<string, Type>|null $schema
     */
    public function generateTextStep(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): StepResponse {
        if ($tools !== [] || $schema !== null || $options?->maxTokens !== null
            || $options?->temperature !== null || $options?->topP !== null || $options?->toolChoice instanceof ToolChoice
            || $options?->cacheInstructions instanceof CacheInstructions || $options?->cacheToolDefinitions instanceof CacheToolDefinitions
            || filled($options?->providerOptions($provider->name()))) {
            throw new InvalidArgumentException('Codex supports plain text without tools, structured output, or sampling options.');
        }

        $conversation = collect($messages)->map(function (Message $message): array {
            if (($message instanceof UserMessage && $message->attachments->isNotEmpty())
                || ($message instanceof AssistantMessage && $message->toolCalls->isNotEmpty())) {
                throw new InvalidArgumentException('Codex supports text messages without attachments or tool calls.');
            }

            return ['role' => $message->role->value, 'content' => $message->content];
        })->all();

        $input = <<<'TEXT'
Generate a text response to the instructions and conversation in the following JSON. Do not use tools, inspect files, execute commands, or access external services. Return the final answer in the text field of the required JSON response.
TEXT . "\n" . json_encode(['instructions' => $instructions, 'messages' => $conversation], JSON_THROW_ON_ERROR);

        $text = $this->process->run($input, [
            'type' => 'object',
            'properties' => ['text' => ['type' => 'string']],
            'required' => ['text'],
            'additionalProperties' => false,
        ], fn(array $response): string => trim(Payload::string(data_get($response, 'text'), 'text')), model: $model, timeout: $timeout);

        return new StepResponse($text, [], FinishReason::Stop, new TextUsage(), new Meta($provider->name(), $model));
    }

    /**
     * @param array<Message> $messages
     * @param array<Tool> $tools
     * @param array<string, Type>|null $schema
     * @return Generator<int, StreamEvent, mixed, StepResponse|null>
     */
    public function generateStreamStep(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): Generator {
        throw new LogicException('Codex supports synchronous text generation only.');
    }
}
