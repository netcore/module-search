<?php

namespace Modules\Search\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class SearchDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        if (!config('netcore.module-search.enable_search_logs')) {
            return;
        }

        $this->call(MenuTableSeeder::class);
    }
}
