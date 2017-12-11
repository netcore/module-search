<?php

namespace Modules\Search\Http\Controllers;

use DataTables;
use DB;
use Illuminate\Routing\Controller;
use Modules\Search\Models\SearchLog;
use Schema;

class SearchController extends Controller
{
    /**
     * Display a listing of search query logs.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $userNameColumn = Schema::hasColumn(config('netcore.module-admin.user.table', 'users'), 'first_name') ? 'first_name' : 'name';

        return view('search::index', compact('userNameColumn'));
    }

    /**
     * Search logs pagination.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pagination()
    {
        $userIdLoggingEnabled = search()->logUserId();

        if ($userIdLoggingEnabled) {
            $query = SearchLog::with('user');
        } else {
            $query = SearchLog::query();
        }

        $datatable = DataTables::of($query);

        // Edit user column - display full name.
        if ($userIdLoggingEnabled) {
            $datatable->editColumn('user', function(SearchLog $searchLog) {
                return $searchLog->user->fullName;
            });
        }

        return $datatable->make(true);
    }
}
