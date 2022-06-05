      <div class="portlet-body form">
        <form class="form-horizontal" role="form" id="fund-form">
          <div class="module-body">
            <div class="form-group row">
              {!! Form::label('title', trans('common.labels.title'), ['class' => 'col-sm-3 col-form-label text-right']) !!}
              <div class="col-md-8">
                {!! Form::text('title', $fund->title, ['class' => 'form-control']) !!}
              </div>
            </div>
            <div class="form-group row">
              {!! Form::label('amount', trans('common.labels.amount'), ['class' => 'col-sm-3 col-form-label text-right']) !!}
              <div class="col-md-6">
                {!! Form::text('amount', $fund->amount, ['class' => 'form-control']) !!}
              </div>
            </div>
            <div class="form-group row">
              {!! Form::label('notes', trans('common.labels.notes'), ['class' => 'col-sm-3 col-form-label text-right']) !!}
              <div class="col-md-8">
                {!! Form::textarea('notes', $fund->notes, ['class' => 'form-control']) !!}
              </div>
            </div>
          </div>
        </form>
      </div>
