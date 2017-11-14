<?php

Route::group([
    'prefix'     => 'admin/search',
    'as'         => 'search::',
    'middleware' => ['web', 'auth.admin'],
    'namespace'  => 'Modules\Search\Http\Controllers\Admin',
], function () {

    Route::get('/test', function () {
        dd(
            search()->of(\App\User::class)->find('My awesome keyword.')
        );
    });

});
