<link rel="stylesheet" href="{{ asset('plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.css') }}">

<!--/span-->

<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
    <h4 class="modal-title"><i class="ti-plus"></i> Contact</h4>
</div>
<div class="modal-body">
    <div class="portlet-body">
        {!! Form::open(['id'=>'contactForm','class'=>'ajax-form','method'=>'POST']) !!}

            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">Contact</label>
                            <input type="text" autocomplete="off" name="contact_date" id="contact_date" class="form-control datepicker" value="">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <button class="btn btn-success" id="postContactForm"  type="button"><i class="fa fa-check"></i> @lang('app.save')</button>

                            <button class="btn btn-danger" data-dismiss="modal" type="button"><i class="fa fa-times"></i> @lang('app.close')</button>
                        </div>
                    </div>
                </div>
            </div>
    {!! Form::hidden('lead_id', $leadID) !!}
        {!! Form::close() !!}
        <!--/row-->
    </div>
</div>
<script src="{{ asset('plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
<script>
    jQuery('#contact_date').datepicker({
        format: '{{ $global->date_picker_format }}',
        autoclose: true,
        todayHighlight: true
    });
    //    update task
    $('#postContactForm').click(function () {
        $.easyAjax({
            url: '{{route('admin.leads.contact-store')}}',
            container: '#contactForm',
            type: "POST",
            data: $('#contactForm').serialize(),
            success: function (response) {
                $('#contactUpModal').modal('hide');
                window.location.reload();
            }
        });

        return false;
    });
</script>