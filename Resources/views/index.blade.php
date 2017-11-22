@extends('admin::layouts.master')

@section('content')
    {!! Breadcrumbs::render('admin.search') !!}

    <div class="page-header">
        <h1>
            <span class="text-muted font-weight-light">
                <i class="page-header-icon fa fa-search"></i> Search logs
            </span>
        </h1>
    </div>

    @if(search()->logsEnabled())
        <div class="table-primary">
            <table class="table table-bordered datatable" id="search-logs-datatable">
                <thead>
                    <tr>
                        <th>Search query</th>
                        <th>Results found</th>
                        <th>Logged at</th>
                    </tr>
                </thead>
            </table>
        </div>
    @else
        <div class="alert alert-danger">Search logs are disabled!</div>
    @endif
@endsection

@section('scripts')
    <script type="text/javascript">
        var init = init || [];

        init.push(function() {
            var datatable = $('#search-logs-datatable');

            if(! datatable.length) {
                return false;
            }

            datatable.dataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('search::pagination') }}',
                responsive: true,
                columns: [
                    {data: 'query', name: 'query', orderable: true, searchable: true},
                    {data: 'results_found', name: 'results_found', orderable: true, searchable: true},
                    {data: 'created_at', name: 'created_at', orderable: true, searchable: true},
                ],
                order: [[2, 'desc']]
            });

            $('.dataTables_wrapper .dataTables_filter input').attr('placeholder', 'Find query');
        });
    </script>
@endsection
