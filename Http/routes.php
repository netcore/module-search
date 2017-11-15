<?php

Route::group([
    'prefix'     => 'admin/search',
    'as'         => 'search::',
    'middleware' => ['web', 'auth.admin'],
    'namespace'  => 'Modules\Search\Http\Controllers\Admin',
], function () {

    // No routes available at the moment.

});
