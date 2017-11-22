<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSearchLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!config('netcore.module-search.enable_search_logs')) {
            return;
        }

        Schema::create('netcore_search__search_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('query');
            $table->unsignedInteger('results_found')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!config('netcore.module-search.enable_search_logs')) {
            return;
        }

        Schema::dropIfExists('netcore_search__search_logs');
    }
}
