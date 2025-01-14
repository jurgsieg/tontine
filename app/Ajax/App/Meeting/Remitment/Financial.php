<?php

namespace App\Ajax\App\Meeting\Remitment;

use App\Ajax\CallableClass;
use Siak\Tontine\Model\Pool as PoolModel;
use Siak\Tontine\Model\Session as SessionModel;
use Siak\Tontine\Service\Meeting\PoolService;
use Siak\Tontine\Service\Meeting\RemitmentService;
use Siak\Tontine\Service\Meeting\ReportService;
use Siak\Tontine\Validation\Meeting\RemitmentValidator;

use function Jaxon\jq;
use function Jaxon\pm;
use function trans;

/**
 * @databag meeting
 * @before getPool
 */
class Financial extends CallableClass
{
    /**
     * @di
     * @var RemitmentService
     */
    protected RemitmentService $remitmentService;

    /**
     * @var ReportService
     */
    protected ReportService $reportService;

    /**
     * @var PoolService
     */
    protected PoolService $poolService;

    /**
     * @var RemitmentValidator
     */
    protected RemitmentValidator $validator;

    /**
     * @var SessionModel|null
     */
    protected ?SessionModel $session = null;

    /**
     * @var PoolModel|null
     */
    protected ?PoolModel $pool = null;

    /**
     * @return void
     */
    protected function getPool()
    {
        $sessionId = $this->bag('meeting')->get('session.id');

        // No pool id on the "home" page
        if($this->target()->method() === 'home')
        {
            $this->session = $this->poolService->getSession($sessionId);
            return;
        }

        $this->session = $this->remitmentService->getSession($sessionId);
        $poolId = $this->target()->method() === 'pool' ?
            $this->target()->args()[0] : $this->bag('meeting')->get('pool.id');
        $this->pool = $this->remitmentService->getPool($poolId);
        if($this->session->disabled($this->pool))
        {
            $this->notify->error(trans('tontine.session.errors.disabled'), trans('common.titles.error'));
            $this->pool = null;
        }
    }

    /**
     * @exclude
     */
    public function show(SessionModel $session, PoolService $poolService)
    {
        $this->session = $session;
        $this->poolService = $poolService;

        return $this->home();
    }

    /**
     * @di $poolService
     */
    public function home()
    {
        $tontine = $this->poolService->getTontine();
        $html = $this->view()->render('tontine.pages.meeting.remitment.home')
            ->with('tontine', $tontine)
            ->with('session', $this->session)
            ->with('pools', $this->poolService->getPoolsWithPayables($this->session));
        if($this->session->closed)
        {
            $html->with('summary', $this->poolService->getPayablesSummary($this->session));
        }
        $this->response->html('meeting-remitments', $html);

        $this->jq('#btn-remitments-refresh')->click($this->rq()->home());
        $poolId = jq()->parent()->attr('data-pool-id')->toInt();
        $this->jq('.btn-pool-remitments')->click($this->rq()->pool($poolId));

        return $this->response;
    }

    /**
     * @param int $poolId
     *
     * @return mixed
     */
    public function pool(int $poolId)
    {
        $this->bag('meeting')->set('pool.id', $poolId);

        $html = $this->view()->render('tontine.pages.meeting.remitment.pool', [
            'pool' => $this->pool,
        ]);
        $this->response->html('meeting-remitments', $html);

        $this->jq('#btn-remitments-back')->click($this->rq()->home());

        return $this->page();
    }

    public function page()
    {
        $html = $this->view()->render('tontine.pages.meeting.remitment.financial', [
            'payables' => $this->remitmentService->getPayables($this->pool, $this->session),
        ]);
        $this->response->html('meeting-pool-remitments', $html);

        $payableId = jq()->parent()->attr('data-payable-id')->toInt();
        $this->jq('.btn-add-remitment')->click($this->rq()->addRemitment());
        $this->jq('.btn-del-remitment')->click($this->rq()->deleteRemitment($payableId));

        return $this->response;
    }

    public function addRemitment()
    {
        $members = $this->remitmentService->getSubscriptions($this->pool);
        $title = trans('tontine.loan.titles.add');
        $content = $this->view()->render('tontine.pages.meeting.remitment.add')
            ->with('members', $members);
        $buttons = [[
            'title' => trans('common.actions.cancel'),
            'class' => 'btn btn-tertiary',
            'click' => 'close',
        ],[
            'title' => trans('common.actions.save'),
            'class' => 'btn btn-primary',
            'click' => $this->rq()->saveRemitment(pm()->form('remitment-form')),
        ]];
        $this->dialog->show($title, $content, $buttons, ['width' => '800']);

        return $this->response;
    }

    /**
     * @di $validator
     * @param array $formValues
     *
     * @return mixed
     */
    public function saveRemitment(array $formValues)
    {
        $values = $this->validator->validateItem($formValues);

        $this->remitmentService->saveFinancialRemitment($this->pool,
            $this->session, $values['payable'], $values['amount']);
        $this->dialog->hide();
        // $this->notify->success(trans('session.remitment.created'), trans('common.titles.success'));

        return $this->page();
    }

    /**
     * @param int $payableId
     *
     * @return mixed
     */
    public function deleteRemitment(int $subscriptionId)
    {
        $this->remitmentService->deleteFinancialRemitment($this->pool, $this->session, $subscriptionId);
        // $this->notify->success(trans('session.remitment.deleted'), trans('common.titles.success'));

        return $this->page();
    }
}
