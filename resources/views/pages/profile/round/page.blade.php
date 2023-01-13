                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th>{!! __('common.labels.title') !!}</th>
                          <th>{!! __('common.labels.start') !!}</th>
                          <th>{!! __('common.labels.end') !!}</th>
                          <th class="table-menu"></th>
                        </tr>
                      </thead>
                      <tbody>
@foreach ($rounds as $round)
                        <tr>
                          <td>{{ $round->title }}</td>
                          <td>{{ $round->start }}</td>
                          <td>{{ $round->end }}</td>
                          <td class="table-item-menu">
@include('parts.table.menu', [
  'dataIdKey' => 'data-round-id',
  'dataIdValue' => $round->id,
  'menus' => [[
    'class' => 'btn-round-edit',
    'text' => __('common.actions.edit'),
  ],[
    'class' => $round->pending ? 'btn-round-open' : 'btn-round-enter',
    'text' => $round->pending ? __('tontine.actions.open') : __('tontine.actions.enter'),
  ]],
])
                          </td>
                        </tr>
@endforeach
                      </tbody>
                    </table>
{!! $pagination !!}
