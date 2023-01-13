                  <div class="row align-items-center">
                    <div class="col-auto">
                      <div class="section-title mt-0">{!! __('meeting.titles.remitments') !!}</div>
                    </div>
@if($session->opened)
                    <div class="col">
                      <div class="btn-group float-right ml-2 mb-2" role="group" aria-label="">
                        <button type="button" class="btn btn-primary" id="btn-remitments-refresh"><i class="fa fa-sync"></i></button>
                      </div>
                    </div>
@endif
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
@foreach($pools as $pool)
@if($session->disabled($pool))
                        @include('pages.meeting.pool.disabled', [
                            'pool' => $pool,
                        ])
@elseif($session->opened)
                        @include('pages.meeting.pool.opened', [
                            'pool' => $pool,
                            'paid' => $pool->pay_paid,
                            'count' => $pool->pay_count,
                            'tontine' => $tontine,
                            'menuClass' => 'btn-pool-remitments',
                            'menuText' => __('meeting.actions.remitments'),
                        ])
@elseif($session->closed)
                        @include('pages.meeting.pool.closed', [
                            'pool' => $pool,
                            'paid' => $pool->pay_paid,
                            'count' => $pool->pay_count,
                            'report' => $report['payables'],
                        ])
@else
                        @include('pages.meeting.pool.pending', [
                            'pool' => $pool,
                            'paid' => $pool->pay_paid,
                            'count' => $pool->pay_count,
                        ])
@endif
@endforeach
@if($session->closed)
                        <tr>
                          <td colspan="2">{!! __('common.labels.total') !!}</td>
                          <td>{{ $report['sum']['payables'] }}</td>
                        </tr>
@endif
                      </tbody>
                    </table>
                  </div> <!-- End table -->
