          <div class="row align-items-center">
            <div class="col-auto">
              <div class="section-title mt-0">{!! __('meeting.titles.fees') !!}</div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>{!! __('common.labels.title') !!}</th>
                  <th>&nbsp;</th>
                  <th>&nbsp;</th>
                </tr>
              </thead>
              <tbody>
  @foreach ($fees as $charge)
  @if($session->closed)
                @include('pages.meeting.charge.closed', compact('charge', 'session'))
  @elseif($session->pending)
                @include('pages.meeting.charge.pending', compact('charge'))
  @else
                @include('pages.meeting.charge.fee', compact('charge'))
  @endif
  @endforeach
  @if($session->closed)
                <tr>
                  <td colspan="2">{!! __('common.labels.total') !!}</td>
                  <td>{{ $summary['sum']['settlements'] }}</td>
                </tr>
  @endif
              </tbody>
            </table>
          </div> <!-- End table -->
