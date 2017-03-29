<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddTranslationsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('locale');
            $table->string('module')->nullable()->default(null);
            $table->string('package')->nullable()->default(null);
            $table->string('vendor')->nullable()->default(null);
            $table->string('group');
            $table->string('name');
            $table->text('value')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();

            $table->index(['locale', 'group']);
            $table->unique(['locale', 'group', 'name', 'package'], 'unique_translate_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('translations');
    }

}
