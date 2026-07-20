<?php

namespace PalPalych\AiTranslator\Classes\Drivers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\OutputConfig;
use Anthropic\RequestOptions;
use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PalPalych\AiTranslator\Models\Settings;
use PalPalych\AiTranslator\Classes\Dto\TranslationRequestDto;
use PalPalych\AiTranslator\Classes\Dto\TranslationResponseDto;
use PalPalych\AiTranslator\Classes\Contracts\LlmDriver;

class ClaudeDriver implements LlmDriver
{
    protected $apiKey;
    protected string $model;
    protected Client $client;
    protected int $defaultMaxTokens = 4000;

    public function __construct()
    {
        $this->apiKey = Settings::get('anthropic_api_key');
        $this->model = (string) Settings::get(
            'claude_model',
            config('palpalych.aitranslator::claude_model', 'claude-sonnet-4-5-20250929')
        );

        $psr17Factory = new Psr17Factory();
        $this->client = new Client(
            apiKey: $this->apiKey,
            requestOptions: RequestOptions::with(
                transporter: new GuzzleClient([
                    'timeout' => 120,
                    'connect_timeout' => 30,
                ]),
                uriFactory: $psr17Factory,
                streamFactory: $psr17Factory,
                requestFactory: $psr17Factory,
            )
        );
    }

    public function translate(TranslationRequestDto $request): TranslationResponseDto
    {
        $jsonContent = json_encode($request->content->fields, JSON_UNESCAPED_UNICODE);

        // We append the JSON enforcement rule at the very end to override any XML formatting in the custom prompt.
        $systemPrompt = <<<EOT
{$request->customInstructions}

--------------------------------------------------
CRITICAL OUTPUT INSTRUCTIONS:
1. You are an API endpoint. You MUST return valid JSON.
2. Ignore any previous instructions asking for XML, <tags>, or <translated_html>.
3. Your JSON output must be a single object where the keys match the input keys exactly.
4. If you generated "tags" or "adaptation_notes" based on the instructions above, add them as new keys in the JSON object: "ai_tags" and "ai_notes".
5. Do not include markdown formatting (like ```json). Just the raw JSON string.
EOT;

        $requestOptions = (new RequestOptions())->withTimeout(120);

        try {
            $message = $this->client->messages->create(
                maxTokens: $this->getMaxTokens(),
                messages: [
                    [
                        'role' => 'user',
                        'content' => "Translate this JSON content from {$request->sourceLang} to {$request->targetLang}:\n" . $jsonContent,
                    ],
                ],
                model: $this->model,
                outputConfig: OutputConfig::with(
                    format: JSONOutputFormat::with(
                        schema: $this->buildTranslationSchema($request->content->fields)
                    )
                ),
                system: $systemPrompt,
                temperature: 0.3,
                requestOptions: $requestOptions,
            );
        } catch (APIConnectionException $e) {
            throw new \Exception('Claude API Connection Error: ' . $this->formatApiException($e), 0, $e);
        } catch (APIException $e) {
            throw new \Exception('Claude API Error: ' . $e->getMessage(), 0, $e);
        }

        if ($message->stopReason !== 'end_turn') {
            $details = "stop_reason={$message->stopReason}, input_tokens={$message->usage->inputTokens}, output_tokens={$message->usage->outputTokens}";

            if ($message->stopReason === 'max_tokens') {
                throw new \Exception("Claude response was cut off before valid JSON was complete ({$details}). Increase max_tokens or translate smaller chunks.");
            }

            if ($message->stopReason === 'refusal') {
                throw new \Exception("Claude refused the request, so structured JSON was not produced ({$details}).");
            }

            throw new \Exception("Claude did not finish normally ({$details}).");
        }

        $responseText = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $responseText = $block->text ?? '';
                break;
            }
        }

        $cleanJson = $this->extractJson($responseText);
        $decoded = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('AI returned invalid JSON: ' . $cleanJson);
        }

        return new TranslationResponseDto($decoded, $cleanJson);
    }

    private function buildTranslationSchema(array $fields): array
    {
        $properties = [];
        foreach ($fields as $key => $value) {
            $properties[$key] = $this->schemaForValue($value);
        }

        $properties['ai_tags'] = [
            'type' => 'array',
            'items' => [
                'type' => 'string',
            ],
        ];
        $properties['ai_notes'] = [
            'type' => 'string',
        ];

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($fields),
            'additionalProperties' => false,
        ];
    }

    private function schemaForValue($value): array
    {
        if (is_string($value)) {
            return ['type' => 'string'];
        }

        if (is_int($value)) {
            return ['type' => 'integer'];
        }

        if (is_float($value)) {
            return ['type' => 'number'];
        }

        if (is_bool($value)) {
            return ['type' => 'boolean'];
        }

        if (is_array($value)) {
            if (array_keys($value) === range(0, count($value) - 1)) {
                return [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ];
            }

            return [
                'type' => 'object',
                'additionalProperties' => true,
            ];
        }

        return ['type' => 'string'];
    }

    private function getMaxTokens(): int
    {
        $maxTokens = (int) Settings::get('claude_max_tokens', $this->defaultMaxTokens);

        return max(1024, min($maxTokens, 64000));
    }

    private function formatApiException(APIException $exception): string
    {
        $messages = [trim($exception->getMessage())];

        $previous = $exception->getPrevious();
        $depth = 0;

        while ($previous && $depth < 10) {
            $messages[] = get_class($previous) . ': ' . $previous->getMessage();
            $previous = $previous->getPrevious();
            $depth++;
        }

        return implode(' | ', array_filter($messages));
    }

    private function extractJson($text)
    {
        $text = preg_replace('/^```json/', '', $text);
        $text = preg_replace('/^```/', '', $text);
        $text = preg_replace('/```$/', '', $text);
        return trim($text);
    }
}
