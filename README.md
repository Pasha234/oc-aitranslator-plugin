# AI Translator Plugin for October CMS

This plugin provides an automated translation workflow for October CMS models using AI (LLMs like Anthropic Claude). It is designed to work seamlessly with the **Multisite** feature, allowing you to translate content from one site locale to another with a human-in-the-loop review process.

## Features

*   **AI Integration:** Uses LLMs to translate content while preserving HTML structure.
*   **Multisite Support:** Automatically detects available sites and propagates translations to the target site context.
*   **Review Workflow:** Translations are not applied immediately. They generate a "Job" which allows an admin to review and edit the AI's output in a popup before applying.
*   **Custom Prompts:** Configurable system instructions (prompts) to control tone, style, or specific translation rules.
*   **Batch Processing:** Console command to translate records in bulk via cron/scheduler.

---

## Configuration

### 1. Plugin Settings
Go to **Settings > Translations > Translations** in the Backend.

*   **Claude API Key:** Enter your Anthropic API Key.
*   **Default Driver:** Choose the AI driver (e.g., `Claude` or `Dummy` for testing).
*   **Default Prompt:** Select which system instruction to use by default for new jobs.

### 2. Config File
You can override defaults in `config/palpalych/aitranslator/config.php`:
*   `drivers`: List of registered AI drivers.

---

## Integration

To enable AI translation for a specific resource (e.g., a Blog Post or a Story), you need to modify the **Model** and the **Controller**.

### 1. Preparing the Model

Add the `AiTranslatableModel` behavior and define which fields should be sent to the AI.

```php
<?php namespace Author\Plugin\Models;

use Model;

class Post extends Model
{
    public $implement = [
        // 1. Add the behavior
        \PalPalych\AiTranslator\Behaviors\AiTranslatableModel::class
    ];

    // 2. Define translatable fields
    public $aiTranslatable = [
        'title',
        'slug',
        'excerpt',
        'content' // Rich text / HTML is supported
    ];
}
```

### 2. Preparing the Controller

Add the `TranslatableController` behavior to handle the AJAX actions and popup rendering.

```php
<?php namespace Author\Plugin\Controllers;

use Backend\Classes\Controller;

class Posts extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        // 1. Add the behavior
        \PalPalych\AiTranslator\Behaviors\TranslatableController::class,
    ];

    // ...
}
```

### 3. Adding the Button to the Toolbar

Open your controller's update view (usually `controllers/posts/update.htm` or `update.php`) and add the button renderer inside the toolbar area.

```php
<?php Block::put('form-contents') ?>
    <div class="layout-row min-size">
        <?= $this->formRenderOutsideFields() ?>

        <div class="form-buttons">
            <!-- Save Buttons ... -->

            <!-- ADD THIS LINE -->
            <?= $this->renderAiTranslateButton() ?>

            <!-- Delete Button ... -->
        </div>
    </div>
    <!-- ... -->
<?php Block::endPut() ?>
```

---

## Usage

### Manual Translation (Backend)
1.  Open a record (e.g., a Story) in the Primary Site context.
2.  Click the **AI Translate** dropdown button in the toolbar.
3.  Select the **Target Site** (language) you want to translate to.
4.  The system creates a background Job and sends data to the AI.
5.  A popup appears showing the **Original** text and the **AI Generated** text.
6.  Edit the translation if needed using the Rich Editor.
7.  Click **Approve & Apply**. The record is now saved/created in the target site.

### Batch Translation (Console)
You can run bulk translations via the command line. This is useful for translating old content or automating via Cron.

```bash
# Syntax:
# php artisan aitranslator:batch "ModelNamespace" TargetSiteID --limit=Records

# Example: Translate 10 Stories to Site ID 2
php artisan aitranslator:batch "PalPalych\Stories\Models\Story" 2 --limit=10

# Queue translation jobs, then apply and publish from the worker
php artisan aitranslator:batch "PalPalych\Stories\Models\Story" 2 --limit=10 --auto-publish
```

The batch command puts jobs into the `Review` status. You must go to the **AI Translations > Jobs** menu in the backend to approve them.

With `--auto-publish`, the command still queues translation jobs. When each queued job completes, the worker applies the translation to the target site record and calls `publishAiTranslation()` on that target record. The model must implement `PalPalych\AiTranslator\Classes\Contracts\PublishesAiTranslations`.

---

## Managing Prompts

Go to **Settings > Translations > Prompts**.

You can create custom prompts to guide the AI.
*   *Example:* "You are a professional translator for children's books. Keep language simple and engaging."
*   The system ensures the AI always returns valid JSON, regardless of the prompt content.
