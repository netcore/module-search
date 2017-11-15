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
     * @param SearchableContract|\Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    public function getResults(SearchableContract $model): array
    {
        $config = $model->getSearchConfig();
        $query = $model->newQuery();

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
     * @param $query
     * @param array $config
     */
    private function buildBaseTableWheres(&$query, array $config = []): void
    {
        // Column wheres
        $columns = array_get($config, 'columns', []);

        foreach ($columns as $column) {
            $isStrict = substr($column, 0, 1) === '!';
            $operator = $isStrict ? '=' : 'LIKE';
            $binding = $isStrict ? $this->query : '%' . $this->query . '%';

            if ($isStrict) {
                $column = substr($column, 1);
            }

            $query->orWhere($column, $operator, $binding);
        }

        // Constant wheres
        $constantWheres = array_get($config, 'wheres', []);

        foreach ($constantWheres as $column => $value) {
            $query->where($column, '=', $value);
        }

        if (array_get($config, 'with_trashed')) {
            $query->withTrashed();
        }
    }

    /**
     * Build query for relations.
     *
     * @param $query
     * @param array $config
     */
    private function buildRelationalWheres(&$query, array $config = []): void
    {
        $relations = array_get($config, 'relations', []);

        foreach ($relations as $relationName => $relation) {
            $query->orWhereHas($relationName, function ($subq) use ($relation) {
                foreach (array_get($relation, 'columns', []) as $column) {
                    $subq->where($column, 'LIKE', '%' . $this->query . '%');
                }

                // Recursive relations
                if ($subRelations = array_get($relation, 'relations')) {
                    $this->buildRelationalWheres($subq, ['relations' => $subRelations]);
                }
            });
        }
    }

    /**
     * Set the current page.
     *
     * @param int $page
     * @return $this
     */
    public function setPage(int $page)
    {
        $this->currentPage = $page;

        return $this;
    }

    /**
     * Set records per page.
     *
     * @param int $perPage
     * @return $this
     */
    public function setRecordsPerPage(int $perPage)
    {
        $this->recordsPerPage = $perPage;

        return $this;
    }
}
