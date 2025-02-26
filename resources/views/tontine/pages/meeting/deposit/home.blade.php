                  <div class="row align-items-center">
                    <div class="col-auto">
                      <div class="section-title mt-0">{!! __('meeting.titles.deposits') !!}</div>
                    </div>
@if($session->opened)
                    <div class="col">
                      <div class="btn-group float-right ml-2 mb-2" role="group" aria-label="">
                        <button type="button" class="btn btn-primary" id="btn-deposits-refresh"><i class="fa fa-sync"></i></button>
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
                        @include('tontine.pages.meeting.pool.disabled', [
                            'pool' => $pool,
                        ])
@elseif($session->opened)
                        @include('tontine.pages.meeting.pool.opened', [
                            'pool' => $pool,
                            'paid' => $pool->recv_paid,
                            'count' => $pool->recv_count,
                            'tontine' => $tontine,
                            'menuClass' => 'btn-pool-deposits',
                            'menuText' => __('meeting.actions.deposits'),
                        ])
@elseif($session->closed)
                        @include('tontine.pages.meeting.pool.closed', [
                            'pool' => $pool,
                            'paid' => $pool->recv_paid,
                            'count' => $pool->recv_count,
                            'amounts' => $summary['receivables'],
                        ])
@else
                        @include('tontine.pages.meeting.pool.pending', [
                            'pool' => $pool,
                            'paid' => $pool->recv_paid,
                            'count' => $pool->recv_count,
                        ])
@endif
@endforeach
@if($session->closed)
                        <tr>
                          <td colspan="2">{!! __('common.labels.total') !!}</td>
                          <td>{{ $summary['total'] }}</td>
                        </tr>
@endif
                      </tbody>
                    </table>
                  </div> <!-- End table -->
