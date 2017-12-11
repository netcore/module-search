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

        $usersTable = config('netcore.module-admin.user.table', 'users');
        $logUserIds = config('netcore.module-search.log_user_ids', false);

        Schema::create('netcore_search__search_logs', function (Blueprint $table) use ($usersTable, $logUserIds) {
            $table->increments('id');

            if (Schema::hasTable($usersTable) && $logUserIds) {
                $table->unsignedInteger('user_id')->nullable();
            }

            $table->string('query');
            $table->unsignedInteger('results_found')->default(0);
            $table->timestamps();

            if (Schema::hasTable($usersTable) && $logUserIds) {
                $table->foreign('user_id', 'uid_foreign')->references('id')->on($usersTable)->onDelete('SET NULL');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('netcore_search__search_logs');
    }
}
