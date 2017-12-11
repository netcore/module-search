<?php

namespace Modules\Search\Repositories;

use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Modules\Search\Models\SearchLog;

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
     * Determine if sql output is needed.
     *
     * @var bool
     */
    protected $logSqlQueries = false;

    /**
     * Searchable models store.
     *
     * @var array
     */
    protected $searchableModels = [];

    /**
     * Determine if we need to return results as collection.
     *
     * @var bool
     */
    protected $returnAsCollection = false;

    /**
     * Determine if we need to return paginator instance.
     *
     * @var bool
     */
    protected $withPaginator = false;

    /**
     * Determine if search query logging is enabled.
     *
     * @var bool
     */
    protected $searchQueryLogging;

    /**
     * Total counter of found results.
     *
     * @var int
     */
    protected $totalResultsFound = 0;

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
     * Enable queries logging.
     *
     * @return $this
     */
    public function withLoggedQueries()
    {
        $this->logSqlQueries = true;

        return $this;
    }

    /**
     * Set to return data as collection.
     *
     * @return $this
     */
    public function returnAsCollection()
    {
        $this->returnAsCollection = true;

        return $this;
    }

    /**
     * Set to return with paginator instance.
     *
     * @return $this
     */
    public function withPaginator()
    {
        $this->withPaginator = true;

        return $this;
    }

    /**
     * Disable search query logging to DB.
     *
     * @return $this
     */
    public function disableSearchQueryLogging()
    {
        $this->searchQueryLogging = false;

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
     * @return array|Collection
     */
    protected function getResults($model)
    {
        $searchable = $this->searchableModels[$model];

        $config = $searchable['config'];

        /** @var $query Builder */
        $query = $searchable['query'];
        $this->buildBaseTableWheres($query, $config);

        // Log SQL Query with bindings
        $sqlQuery = $query->toSql();
        $sqlBindings = $query->getBindings();

        foreach ($sqlBindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
            $sqlQuery = preg_replace('/\?/', $value, $sqlQuery, 1);
        }

        // Fetch records
        if ($this->withPaginator) {
            $records = $query->paginate($this->recordsPerPage, ['*'], 'page', $this->currentPage);
        } else {
            $records = $query->forPage($this->currentPage, $this->recordsPerPage)->get();
        }

        $totalCount = $query->count();
        $totalPages = (int)ceil($totalCount / $this->recordsPerPage) ?? 1;

        $this->totalResultsFound += $totalCount;

        $data = [];

        if ($this->logSqlQueries) {
            $data['query'] = $sqlQuery;
        }

        $data += [
            'total_items' => $totalCount,
            'total_pages' => $totalPages,
            'results'     => $records,
        ];

        return $this->returnAsCollection ? collect($data) : $data;
    }

    /**
     * Build query for base model columns.
     *
     * @param Builder $query
     * @param array $config
     */
    private function buildBaseTableWheres(Builder &$query, array $config = []): void
    {
        // Constant wheres
        $constantWheres = array_get($config, 'wheres', []);

        foreach ($constantWheres as $column => $value) {
            $query->where($column, '=', $value);
        }

        // Column wheres
        $columns = array_get($config, 'columns', []);

        // Wrap "or where's"
        if (count($columns)) {
            $query->where(function (Builder $whereSubQuery) use ($columns, $config) {
                foreach ($columns as $column) {
                    $isStrict = substr($column, 0, 1) === '!';
                    $operator = $isStrict ? '=' : 'LIKE';
                    $binding = $isStrict ? $this->query : '%' . $this->query . '%';

                    if ($isStrict) {
                        $column = substr($column, 1);
                    }

                    $whereSubQuery->orWhere($column, $operator, $binding);
                }

                // Relational wheres
                $this->buildRelationalWheres($whereSubQuery, $config);

                return $whereSubQuery;
            });
        }

        // Soft deletes disable
        $classUsesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($query->getModel()));

        if (array_get($config, 'with_trashed') && $classUsesSoftDeletes) {
            $query->withTrashed();
        }
    }

    /**
     * Build query for relations.
     *
     * @param Builder $query
     * @param array $config
     */
    private function buildRelationalWheres(Builder &$query, array $config = []): void
    {
        $relations = array_get($config, 'relations', []);

        foreach ($relations as $relationName => $relation) {
            $query->orWhereHas($relationName, function (Builder $subQuery) use ($relation) {

                // Strict wheres
                foreach (array_get($relation, 'wheres', []) as $column => $where) {
                    $subQuery->where($column, $where);
                }

                // Columns and relations
                $subQuery->where(function (Builder $wrappedSubQuery) use ($relation) {
                    foreach (array_get($relation, 'columns', []) as $column) {
                        $wrappedSubQuery->orWhere($column, 'LIKE', '%' . $this->query . '%');
                    }

                    // Nested relations
                    if ($subRelations = array_get($relation, 'relations')) {
                        $this->buildRelationalWheres($wrappedSubQuery, ['relations' => $subRelations]);
                    }

                    return $wrappedSubQuery;
                });
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
                    $key .= ($i == 1 ? '' : '.') . 'relations.' . $part;
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
     * @return array|Collection
     */
    public function find(string $keyword)
    {
        $this->query = $keyword;

        $results = [
            'page'     => $this->currentPage,
            'per_page' => $this->recordsPerPage,
        ];

        foreach ($this->searchableModels as $key => $searchableModel) {
            $results[$searchableModel['config']['key']] = $this->getResults($key);
        }

        // Log search query
        if ($this->logsEnabled() && is_null($this->searchQueryLogging)) {
            SearchLog::create([
                'query'         => $keyword,
                'results_found' => $this->totalResultsFound,
            ]);
        }

        return $this->returnAsCollection ? collect($results) : $results;
    }

    /**
     * Determine if search query logging is enabled.
     *
     * @return bool
     */
    public function logsEnabled(): bool
    {
        return (bool)config('netcore.module-search.enable_search_logs');
    }

    /**
     * Determine if user_id logging is enabled.
     *
     * @return bool
     */
    public function logUserId()
    {
        return (bool)config('netcore.module-search.log_user_ids');
    }
}
