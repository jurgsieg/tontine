<?php

namespace App\Ajax\App\Meeting;

use Siak\Tontine\Service\MeetingService;
use Siak\Tontine\Model\Session as SessionModel;
use App\Ajax\CallableClass;

use function jq;

/**
 * @databag meeting
 * @before getSession
 */
class Fund extends CallableClass
{
    /**
     * @di
     * @var MeetingService
     */
    protected MeetingService $meetingService;

    /**
     * @var SessionModel|null
     */
    protected ?SessionModel $session;

    /**
     * @return void
     */
    protected function getSession()
    {
        $sessionId = $this->bag('meeting')->get('session.id');
        $this->session = $this->meetingService->getSession($sessionId);
    }

    /**
     * @exclude
     */
    public function show($session, $meetingService)
    {
        $this->session = $session;
        $this->meetingService = $meetingService;

        return $this->home();
    }

    public function home()
    {
        $html = $this->view()->render('pages.meeting.fund.home')
            ->with('session', $this->session)
            ->with('funds', $this->meetingService->getFunds($this->session));
        $this->response->html('meeting-funds', $html);

        $fundId = jq()->parent()->attr('data-fund-id');
        $this->jq('.btn-fund-deposits')->click($this->cl(Deposit::class)->rq()->home($fundId));
        $this->jq('.btn-fund-remittances')->click($this->cl(Remittance::class)->rq()->home($fundId));
        $this->jq('.btn-fund-table')->click($this->rq()->table($fundId));

        return $this->response;
    }
}
