@extends('layouts.app')

@section('page-title')
    <div class="row bg-title">
        <!-- .page title -->
        <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12">
            <h4 class="page-title"><i class="{{ $pageIcon }}"></i>{{ $pageTitle }}</h4>
        </div>
        <!-- /.page title -->
        <!-- .breadcrumb -->
        <div class="col-lg-6 col-sm-8 col-md-8 col-xs-12 text-right">
            <a href="{{ route('admin.leads.create') }}"
               class="btn btn-outline btn-success btn-sm">@lang('modules.lead.addNewLead') <i class="fa fa-plus"
                                                                                              aria-hidden="true"></i></a>
            <ol class="breadcrumb">
                <li><a href="{{ route('admin.dashboard') }}">@lang('app.menu.home')</a></li>
                <li class="active">{{ $pageTitle }}</li>
            </ol>
        </div>
        <!-- /.breadcrumb -->
    </div>
@endsection

@push('head-script')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.1.1/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/buttons/1.2.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/buttons/1.2.2/css/buttons.dataTables.min.css">
    <style>

        .dashboard-stats .white-box .list-inline {
            margin-bottom: 0;
        }

        .dashboard-stats .white-box {
            padding: 10px;
        }

        .dashboard-stats .white-box .box-title {
            font-size: 13px;
            text-transform: capitalize;
            font-weight: 300;
        }
        #leads-table_wrapper .dt-buttons{
            display: none !important;
        }
    </style>
@endpush

@section('content')

        <div class="row">
		<div class="col-12 col-lg-6 col-xl-3 justify-content-center">
                <div class="card card-border-color card-border-color-success">
                    <div class="card-header">@lang('modules.dashboard.totalLeads')</div>
                    <div class="card-body"><span class="badge badge-pill badge-secondary">{{ $totalLeads }}</span></div>
                </div>
                    </div>
                <div class="col-12 col-lg-6 col-xl-3 justify-content-center">
                <div class="card card-border-color card-border-color-success">
                    <div class="card-header">@lang('modules.dashboard.totalConvertedClient')</div>
                    <div class="card-body"><span class="badge badge-pill badge-secondary">{{ $totalClientConverted }}</span></div>        
                </div>
                    </div>
                <div class="col-12 col-lg-6 col-xl-3 justify-content-center">
                <div class="card card-border-color card-border-color-success">
                    <div class="card-header">@lang('modules.dashboard.totalPendingFollowUps')</div>
                    <div class="card-body"><span class="badge badge-pill badge-secondary">{{ $pendingLeadFollowUps }}</span></div>
                
                </div>
            </div>
        </div>

    <div class="row">

        <div class="col-sm-12">
            <div class="white-box">
                <div class="table-responsive">
                    {!! $dataTable->table(['class' => 'table table-bordered table-hover toggle-circle default footable-loaded footable']) !!}
                </div>
            </div>
        </div>
    </div>
    <!-- .row -->
    {{--Ajax Modal--}}
    <div class="modal fade bs-modal-md in" id="followUpModal" role="dialog" aria-labelledby="myModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-md" id="modal-data-application">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                    <span class="caption-subject font-red-sunglo bold uppercase" id="modelHeading"></span>
                </div>
                <div class="modal-body">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn blue">Save Changes</button>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
    <div class="modal fade bs-modal-md in" id="contactUpModal" role="dialog" aria-labelledby="myModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-md" id="modal-data-application">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                    <span class="caption-subject font-red-sunglo bold uppercase" id="modelHeading"></span>
                </div>
                <div class="modal-body">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn blue">Save Changes</button>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
    {{--Ajax Modal Ends--}}

@endsection

@push('footer-script')
    <script src="{{ asset('plugins/bower_components/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="//cdn.datatables.net/1.10.13/js/dataTables.bootstrap.min.js"></script>
    <script src="//cdn.datatables.net/responsive/2.1.1/js/dataTables.responsive.min.js"></script>
    <script src="//cdn.datatables.net/responsive/2.1.1/js/responsive.bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.0.3/js/dataTables.buttons.min.js"></script>
    <script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>

    {!! $dataTable->scripts() !!}
    <script>
        $(function () {
            $('#reset-filters').click(function () {
                $('#filter-form')[0].reset();
                $('#filter-form').find('.selectpicker').selectpicker('render');
                $.easyBlockUI('#leads-table');
                window.LaravelDataTables["leads-table"].draw();
                $.easyUnblockUI('#leads-table');
            })
            var table;
            $('#apply-filters').click(function () {

                $('#leads-table').on('preXhr.dt', function (e, settings, data) {
                    var client = $('#client').val();
                    var followUp = $('#followUp').val();
                    data['client'] = client;
                    data['followUp'] = followUp;
                });

                $.easyBlockUI('#leads-table');
                window.LaravelDataTables["leads-table"].draw();
                $.easyUnblockUI('#leads-table');
            });
            $('body').on('click', '.sa-params', function () {
                var id = $(this).data('user-id');
                swal({
                    title: "Are you sure?",
                    text: "You will not be able to recover the deleted lead!",
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "Yes, delete it!",
                    cancelButtonText: "No, cancel.",
                    closeOnConfirm: true,
                    closeOnCancel: true
                }, function (isConfirm) {
                    if (isConfirm) {

                        var url = "{{ route('admin.leads.destroy',':id') }}";
                        url = url.replace(':id', id);

                        var token = "{{ csrf_token() }}";

                        $.easyAjax({
                            type: 'POST',
                            url: url,
                            data: {'_token': token, '_method': 'DELETE'},
                            success: function (response) {
                                if (response.status == "success") {
                                    $.easyBlockUI('#leads-table');
                                    window.LaravelDataTables["leads-table"].draw();
                                    $.easyUnblockUI('#leads-table');
                                }
                            }
                        });
                    }
                });
            });


        });

        function changeStatus(leadID, statusID) {
            var url = "{{ route('admin.leads.change-status') }}";
            var token = "{{ csrf_token() }}";

            $.easyAjax({
                type: 'POST',
                url: url,
                data: {'_token': token, 'leadID': leadID, 'statusID': statusID},
                success: function (response) {
                    if (response.status == "success") {
                        $.easyBlockUI('#leads-table');
                        window.LaravelDataTables["leads-table"].draw();
                        $.easyUnblockUI('#leads-table');
                    }
                }
            });
        }

        $('.edit-column').click(function () {
            var id = $(this).data('column-id');
            var url = '{{ route("admin.taskboard.edit", ':id') }}';
            url = url.replace(':id', id);

            $.easyAjax({
                url: url,
                type: "GET",
                success: function (response) {
                    $('#edit-column-form').html(response.view);
                    $(".colorpicker").asColorPicker();
                    $('#edit-column-form').show();
                }
            })
        })

        function followUp(leadID) {

            var url = '{{ route('admin.leads.follow-up', ':id')}}';
            url = url.replace(':id', leadID);

            $('#modelHeading').html('Add Follow Up');
            $.ajaxModal('#followUpModal', url);
        }

        function addContact(leadID) {
            var url = '{{ route('admin.leads.contact', ':id')}}';
            url = url.replace(':id', leadID);

            $('#modelHeading').html('Add Contact');
            $.ajaxModal('#contactUpModal', url);
        }

        $('.toggle-filter').click(function () {
            $('#ticket-filters').toggle('slide');
        })

        function exportData() {

            var client = $('#client').val();
            var followUp = $('#followUp').val();

            var url = '{{ route('admin.leads.export', [':followUp', ':client']) }}';
            url = url.replace(':client', client);
            url = url.replace(':followUp', followUp);

            window.location.href = url;
        }
    </script>
@endpush
