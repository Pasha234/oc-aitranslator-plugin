<?php namespace PalPalych\AiTranslator\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateJobsTable Migration
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
        Schema::create('palpalych_aitranslator_jobs', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');

            $table->string('translatable_type');
            $table->integer('translatable_id');

            $table->string('source_locale', 10)->nullable();
            $table->string('target_locale', 10)->nullable();
            $table->integer('target_site_id')->nullable();

            $table->integer('prompt_id')->nullable();

            $table->tinyInteger('status')->default(0);
            $table->text('error_message')->nullable();

            $table->string('driver')->nullable();
            $table->text('driver_response')->nullable();

            $table->timestamps();

            $table->index(['translatable_type', 'translatable_id'], 'palpalych_aitranslator_jobs_translatable_idx');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('palpalych_aitranslator_jobs');
    }
};
