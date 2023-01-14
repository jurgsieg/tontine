                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th>{!! __('common.labels.name') !!}</th>
                          <th class="table-menu"></th>
                        </tr>
                      </thead>
                      <tbody>
@foreach ($tontines as $tontine)
                        <tr>
                          <td>{{ $tontine->name }}</td>
                          <td class="table-item-menu">
@include('tontine.parts.table.menu', [
  'dataIdKey' => 'data-tontine-id',
  'dataIdValue' => $tontine->id,
  'menus' => [[
    'class' => 'btn-tontine-edit',
    'text' => __('common.actions.edit'),
  ],[
    'class' => 'btn-tontine-rounds',
    'text' => __('tontine.actions.rounds'),
  ],[
    'class' => 'btn-tontine-enter',
    'text' => __('tontine.actions.enter'),
  ]],
])
                          </td>
                        </tr>
@endforeach
                      </tbody>
                    </table>
{!! $pagination !!}
