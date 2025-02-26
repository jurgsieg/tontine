                  <div class="row align-items-center">
                    <div class="col-auto">
                      <div class="section-title mt-0">{{ __('meeting.titles.loans') }} ({{ $amountAvailable }})</div>
                    </div>
@if($session->opened)
                    <div class="col">
                      <div class="btn-group float-right ml-2 mb-2" role="group" aria-label="">
                        <button type="button" class="btn btn-primary" id="btn-loan-add"><i class="fa fa-plus"></i></button>
                        <button type="button" class="btn btn-primary" id="btn-loans-refresh"><i class="fa fa-sync"></i></button>
                      </div>
                    </div>
@endif
                  </div>
                  <div class="table-responsive">
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th>{!! __('meeting.labels.member') !!}</th>
                          <th>{!! __('common.labels.amount') !!}</th>
                          <th>&nbsp;</th>
                        </tr>
                      </thead>
                      <tbody>
@foreach ($loans as $loan)
                        <tr>
                          <td>{{ $loan->member->name }}</td>
                          <td>{{ $loan->amount }}<br/>{{ $loan->interest }}</td>
@if ($session->closed || ($loan->remitment_id))
                          <td class="table-item-menu">&nbsp;</td>
@else
                          <td class="table-item-menu" data-loan-id="{{ $loan->id }}">
                            <a href="javascript:void(0)" class="btn-loan-delete"><i class="fa fa-times-circle"></i></a>
                          </td>
@endif
                        </tr>
@endforeach
                      </tbody>
                    </table>
                  </div> <!-- End table -->
