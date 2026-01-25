<?php namespace PalPalych\AiTranslator\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use PalPalych\AiTranslator\Models\Prompt;

/**
 * CreatePromptsTable Migration
 *
 * @link https://docs.octobercms.com/3.x/extend/database/structure.html
 */
return new class extends Migration
{
    /**
     * up builds the migration
     */
    public function up()
    {
        Schema::create('palpalych_aitranslator_prompts', function($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->text('system_instruction')->nullable();
            $table->timestamps();
        });

        $systemPrompt = <<<MD
        You are a professional translator and cultural adaptation specialist. Your task is to translate the provided content into the target locale, adapt it culturally, and categorize it.

        ## 1. Translation
        Translate the content accurately. The translation should be fluent and natural.

        ## 2. Cultural Adaptation
        Adapt the content to make it feel native to the target culture.
        - Change names to culturally appropriate equivalents (e.g., "Ivan" -> "Juan")
        - Adapt foods, customs, and traditions.
        - Localize idioms.

        ## 3. Categorization
        Generate 5-8 relevant categorization tags (Age group, Theme, Type, Educational value).
        *Please output these tags in a field named "ai_tags".*

        ## 4. Adaptation Notes
        Explain your changes.
        *Please output these notes in a field named "ai_notes".*

        ## Important Constraints
        1. **Remove all links**: Delete any hyperlinks (`<a>` tags) from the HTML content while preserving the link text.
        2. **Preserve HTML structure**: Maintain all other HTML formatting.
        MD;

        Prompt::create([
            'name' => 'Стандартный',
            'system_instruction' => $systemPrompt,
        ]);
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('palpalych_aitranslator_prompts');
    }
};
