<?php

namespace App\Ajax\App\Credit;

use Siak\Tontine\Service\Meeting\LoanService;
use Siak\Tontine\Validation\Meeting\LoanValidator;
use Siak\Tontine\Model\Currency;
use Siak\Tontine\Model\Session as SessionModel;
use App\Ajax\CallableClass;

use function Jaxon\jq;
use function Jaxon\pm;

/**
 * @databag meeting
 * @before getSession
 */
class Loan extends CallableClass
{
    /**
     * @di
     * @var LoanService
     */
    protected LoanService $loanService;

    /**
     * @var LoanValidator
     */
    protected LoanValidator $validator;

    /**
     * @var SessionModel|null
     */
    protected ?SessionModel $session = null;

    /**
     * @return void
     */
    protected function getSession()
    {
        $sessionId = $this->bag('meeting')->get('session.id');
        $this->session = $this->loanService->getSession($sessionId);
    }

    /**
     * @exclude
     */
    public function show(SessionModel $session, LoanService $loanService)
    {
        $this->session = $session;
        $this->loanService = $loanService;

        return $this->home();
    }

    public function home()
    {
        $loans = $this->loanService->getSessionLoans($this->session);
        $amountAvailable = $this->loanService->getFormattedAmountAvailable($this->session);

        $html = $this->view()->render('tontine.pages.meeting.loan.home')
            ->with('loans', $loans)
            ->with('amountAvailable', $amountAvailable);
        $this->response->html('meeting-loans', $html);

        $this->jq('#btn-loans-refresh')->click($this->rq()->home());
        $this->jq('#btn-loan-add')->click($this->rq()->addLoan());
        $loanId = jq()->parent()->attr('data-loan-id')->toInt();
        $this->jq('.btn-loan-delete')->click($this->rq()->deleteLoan($loanId));

        return $this->response;
    }

    public function addLoan()
    {
        $amountAvailable = $this->loanService->getAmountAvailable($this->session);
        if($amountAvailable <= 0)
        {
            return $this->response;
        }

        $members = $this->loanService->getMembers();
        $title = trans('tontine.loan.titles.add');
        $content = $this->view()->render('tontine.pages.meeting.loan.add')
            ->with('members', $members)
            ->with('amount', $amountAvailable);
        $buttons = [[
            'title' => trans('common.actions.cancel'),
            'class' => 'btn btn-tertiary',
            'click' => 'close',
        ],[
            'title' => trans('common.actions.save'),
            'class' => 'btn btn-primary',
            'click' => $this->rq()->saveLoan(pm()->form('loan-form')),
        ]];
        $this->dialog->show($title, $content, $buttons, ['width' => '800']);

        return $this->response;
    }

    /**
     * @di $validator
     */
    public function saveLoan(array $formValues)
    {
        $values = $this->validator->validateItem($formValues);

        $memberId = $values['member'];
        $amount = $values['amount'];
        $interest = $values['interest'];
        $this->loanService->createLoan($this->session, $memberId, $amount, $interest);

        $this->dialog->hide();

        return $this->home();
    }

    public function deleteLoan(int $loanId)
    {
        $this->loanService->deleteLoan($this->session, $loanId);

        return $this->home();
    }
}