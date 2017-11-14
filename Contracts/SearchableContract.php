<?php

namespace Modules\Search\Contracts;

interface SearchableContract
{
    /**
     * Get the config of search module for building queries.
     *
     * @return array
     */
    public function getSearchConfig(): array;
}