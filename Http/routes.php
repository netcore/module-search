<?php

use DaveJamesMiller\Breadcrumbs\BreadcrumbsGenerator;

Route::group([
    'prefix'     => 'admin/search',
    'as'         => 'search::',
    'middleware' => ['web', 'auth.admin'],
    'namespace'  => 'Modules\Search\Http\Controllers',
], function () {

    Route::get('/', [
        'uses' => 'SearchController@index',
        'as'   => 'index',
    ]);

    Route::get('pagination', [
        'uses' => 'SearchController@pagination',
        'as'   => 'pagination',
    ]);

});

Breadcrumbs::register('admin.search', function(BreadcrumbsGenerator $generator) {
    $generator->parent('admin');
    $generator->push('Search logs', route('search::index'));
});
