<?php namespace PalPalych\AiTranslator\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateTranslationsTable Migration
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
        Schema::create('palpalych_aitranslator_field_translations', function($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('job_id')->unsigned();

            $table->string('field_name')->nullable();

            $table->longText('original_value')->nullable();
            $table->longText('ai_value')->nullable();
            $table->longText('final_value')->nullable();

            $table->boolean('is_modified')->default(false);

            $table->timestamps();

            $table->foreign('job_id')
                ->references('id')
                ->on('palpalych_aitranslator_jobs')
                ->onDelete('cascade');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('palpalych_aitranslator_field_translations');
    }
};
