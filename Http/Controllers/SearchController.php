<?php

namespace Modules\Search\Http\Controllers;

use DataTables;
use DB;
use Illuminate\Routing\Controller;

class SearchController extends Controller
{
    /**
     * Display a listing of search query logs.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('search::index');
    }

    /**
     * Search logs pagination.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pagination()
    {
        return DataTables::of(
            DB::table('netcore_search__search_logs')
        )->make(true);
    }
}
