<?php

if (!app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}

// Helper function
if (! function_exists('search')) {
    function search() {
        return app(\Modules\Search\Repositories\SearchRepository::class);
    }
}
