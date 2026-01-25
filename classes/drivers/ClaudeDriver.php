<?php

namespace PalPalych\AiTranslator\Classes\Drivers;

use Http;
use PalPalych\AiTranslator\Models\Settings;
use PalPalych\AiTranslator\Classes\Dto\TranslationRequestDto;
use PalPalych\AiTranslator\Classes\Dto\TranslationResponseDto;
use PalPalych\AiTranslator\Classes\Contracts\LlmDriver;

class ClaudeDriver implements LlmDriver
{
    protected $apiKey;
    protected $model = 'claude-sonnet-4-5-20250929'; // Or generic claude-3-opus

    public function __construct()
    {
        $this->apiKey = Settings::get('anthropic_api_key');
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

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 4000,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Translate this JSON content from {$request->sourceLang} to {$request->targetLang}:\n" . $jsonContent,
                ]
            ],
            'temperature' => 0.3, // Lower temp is better for strict JSON adherence
        ]);

        if (!$response->successful()) {
            throw new \Exception('Claude API Error: ' . $response->body());
        }

        $body = $response->json();

        $responseText = '';
        if (isset($body['content']) && is_array($body['content'])) {
            foreach ($body['content'] as $block) {
                if ($block['type'] === 'text') {
                    $responseText = $block['text'];
                    break;
                }
            }
        }

        $cleanJson = $this->extractJson($responseText);
        $decoded = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('AI returned invalid JSON: ' . $cleanJson);
        }

        return new TranslationResponseDto($decoded, $cleanJson);
    }

    private function extractJson($text)
    {
        $text = preg_replace('/^```json/', '', $text);
        $text = preg_replace('/^```/', '', $text);
        $text = preg_replace('/```$/', '', $text);
        return trim($text);
    }
}
