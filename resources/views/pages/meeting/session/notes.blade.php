      <div class="portlet-body form">
        <form>
          <div class="form-group">
            {!! Form::label('notes', trans('common.labels.title')) !!}
            {!! Form::textarea('notes', $notes, ['class' => 'form-control', 'id' => 'text-notes']) !!}
          </div>
        </form>
      </div>
