<?php

namespace Modules\Search\Repositories;

use Illuminate\Database\Query\Builder;
use Modules\Search\Contracts\SearchableContract;
use Modules\Search\Exceptions\InvalidSearchableModelException;

class SearchRepository
{
    /**
     * Records count per page.
     *
     * @var int
     */
    protected $recordsPerPage = 20;

    /**
     * Current page.
     *
     * @var int
     */
    protected $currentPage = 1;

    /**
     * @var array
     */
    protected $searchableModels = [];

    /**
     * Search query/keyword.
     *
     * @var string
     */
    protected $query;

    /**
     * Set the searchable models
     *
     * @param array|mixed $models
     * @return $this
     * @throws InvalidSearchableModelException
     */
    public function of($models)
    {
        $models = is_array($models) ? $models : func_get_args();

        foreach ($models as $model) {
            $this->registerSearchableModel(
                app($model)
            );
        }

        return $this;
    }

    /**
     * Find results by given keyword.
     *
     * @param string $keyword
     * @return array
     */
    public function find(string $keyword): array
    {
        $this->query = $keyword;

        $results = [
            'page'     => $this->currentPage,
            'per_page' => $this->recordsPerPage,
        ];

        foreach ($this->searchableModels as $key => $searchableModel) {
            $results[$key] = $this->getResults($searchableModel);
        }

        return $results;
    }

    /**
     * Register searchable model
     *
     * @param SearchableContract $model
     */
    protected function registerSearchableModel(SearchableContract $model)
    {
        $key = str_plural(strtolower(class_basename($model))); // used as fallback key, if not specified
        $key = array_get($model->getSearchConfig(), 'key', $key);

        $this->searchableModels[$key] = $model;
    }

    /**
     * @param SearchableContract|\Illuminate\Database\Eloquent\Builder $model
     * @return array
     */
    public function getResults(SearchableContract $model): array
    {
        $config = $model->getSearchConfig();
        $query = $model->getQuery();

        $this->buildBaseTableWheres($query, $config);
        $this->buildRelationalWheres($query, $config);

        // Get records for given page
        $records = $query->forPage($this->currentPage, $this->recordsPerPage)->get();

        $totalCount = $query->count();
        $totalPages = (int)ceil($totalCount / $this->recordsPerPage) ?? 1;

        return [
            'total_items' => $totalCount,
            'total_pages' => $totalPages,
            'results'     => $records,
        ];
    }

    /**
     * Build query for base model columns.
     *
     * @param Builder $query
     * @param array $config
     */
    private function buildBaseTableWheres(Builder &$query, array $config = []): void
    {
        $columns = array_get($config, 'columns');

        if (!$columns) {
            return;
        }

        foreach ($columns as $column) {
            $isStrict = substr($column, 0, 1) === '!';
            $operator = $isStrict ? '=' : 'LIKE';
            $binding = $isStrict ? $this->query : '%' . $this->query . '%';

            if ($isStrict) {
                $column = substr($column, 1);
            }

            $query->orWhere($column, $operator, $binding);
        }
    }

    /**
     * Build query for relations.
     *
     * @param Builder $builder
     * @param array $config
     */
    private function buildRelationalWheres(Builder &$builder, array $config = []): void
    {
        $relations = array_get($config, 'relations');

        if (!$relations) {
            return;
        }

        // @TODO ..
    }
}
