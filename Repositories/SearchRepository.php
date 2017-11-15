<?php

namespace Modules\Search\Repositories;

use Illuminate\Database\Eloquent\SoftDeletes;

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
     * Searchable models store.
     *
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

    /**
     * Set the searchable models.
     *
     * @param array|mixed $models
     * @return $this
     */
    public function of($models)
    {
        $models = is_array($models) ? $models : func_get_args();

        foreach ($models as $model) {
            $model = app($model);

            $className = get_class($model);
            $modelConfig = $model->getSearchConfig();

            $resultingKeyName = str_plural(strtolower(camel_case(class_basename($model))));
            $modelConfig['key'] = $resultingKeyName;

            $this->searchableModels[$className] = [
                'model'   => $model,
                'query'   => $model->newQuery(),
                'config'  => $modelConfig,
                'results' => [],
            ];
        }

        return $this;
    }

    /**
     * Get the results.
     *
     * @param $model
     * @return array
     */
    public function getResults($model): array
    {
        $searchable = $this->searchableModels[$model];

        $config = $searchable['config'];
        $query = $searchable['query'];

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

        // Soft deletes disable
        $classUsesSoftDeletes = in_array(SoftDeletes::class ,class_uses_recursive($query->getModel()));

        if (array_get($config, 'with_trashed') && $classUsesSoftDeletes) {
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

                // Columns search
                foreach (array_get($relation, 'columns', []) as $column) {
                    $subq->where($column, 'LIKE', '%' . $this->query . '%');
                }

                // Add strict wheres
                foreach (array_get($relation, 'wheres', []) as $column => $where) {
                    $subq->where($column, $where);
                }

                // Recursive relations
                if ($subRelations = array_get($relation, 'relations')) {
                    $this->buildRelationalWheres($subq, ['relations' => $subRelations]);
                }
            });
        }
    }

    public function where(...$args)
    {
        $path = $args[1];

        if (str_contains($path, '.')) {
            $parts = explode('.', $path);
            $key = '';
            $i = 1;

            foreach ($parts as $part) {
                $isLast = $i == count($parts);

                if ($isLast) {
                    $key .= '.wheres.' . $part;
                } else {
                    $key .= ($i == 1 ? '' : '.' ) . 'relations.' . $part;
                }

                $i++;
            }
        } else {
            $key = 'wheres.' . $path;
        }

        array_set($this->searchableModels, $args[0] . '.config.' . $key, $args[2]);

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
            $results[$searchableModel['config']['key']] = $this->getResults($key);
        }

        return $results;
    }
}
