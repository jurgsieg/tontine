<?php

namespace Siak\Tontine\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use Siak\Tontine\Model\Bidding;
use Siak\Tontine\Model\Currency;
use Siak\Tontine\Model\Pool;
use Siak\Tontine\Model\Member;
use Siak\Tontine\Model\Remittance;
use Siak\Tontine\Model\Session;
use Siak\Tontine\Model\Payable;
use Siak\Tontine\Model\Refund;
use stdClass;

class BiddingService
{
    /**
     * @var TenantService
     */
    protected TenantService $tenantService;

    /**
     * @var PlanningService
     */
    protected PlanningService $planningService;

    /**
     * @var RemittanceService
     */
    protected RemittanceService $remittanceService;

    /**
     * @var SubscriptionService
     */
    protected SubscriptionService $subscriptionService;

    /**
     * @param TenantService $tenantService
     * @param PlanningService $planningService
     * @param RemittanceService $remittanceService
     * @param SubscriptionService $subscriptionService
     */
    public function __construct(TenantService $tenantService, PlanningService $planningService,
        RemittanceService $remittanceService, SubscriptionService $subscriptionService)
    {
        $this->tenantService = $tenantService;
        $this->planningService = $planningService;
        $this->remittanceService = $remittanceService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Get a single session.
     *
     * @param int $sessionId    The session id
     *
     * @return Session|null
     */
    public function getSession(int $sessionId): ?Session
    {
        return $this->tenantService->round()->sessions()->find($sessionId);
    }

    /**
     * Get a single pool.
     *
     * @param int $poolId    The pool id
     *
     * @return Pool|null
     */
    public function getPool(int $poolId): ?Pool
    {
        return $this->tenantService->round()->pools()->find($poolId);
    }

    /**
     * Get the unpaid subscriptions of a given pool.
     *
     * @param Pool $pool
     *
     * @return Collection
     */
    public function getSubscriptions(Pool $pool): Collection
    {
        return $pool->subscriptions()->with(['payable', 'member'])->get()
            ->filter(function($subscription) {
                return !$subscription->payable->session_id;
            })->pluck('member.name', 'id');
    }

    /**
     * Get a list of members for the dropdown select component.
     *
     * @return Collection
     */
    public function getMembers(): Collection
    {
        return $this->tenantService->tontine()->members()
            ->orderBy('name', 'asc')->pluck('name', 'id');
    }

    /**
     * Find a member.
     *
     * @param int $memberId
     *
     * @return Member|null
     */
    public function getMember(int $memberId): ?Member
    {
        return $this->tenantService->tontine()->members()->find($memberId);
    }

    /**
     * Create a remittance.
     *
     * @param Pool $pool The pool
     * @param Session $session The session
     * @param int $subscriptionId
     * @param int $amountPaid
     *
     * @return void
     */
    public function createRemittance(Pool $pool, Session $session, int $subscriptionId, int $amountPaid): void
    {
        $subscription = $pool->subscriptions()->find($subscriptionId);
        DB::transaction(function() use($pool, $session, $subscription, $amountPaid) {
            $this->subscriptionService->setPayableSession($session, $subscription);
            $this->remittanceService->createRemittance($pool, $session, $subscription->payable->id, $amountPaid);
        });
    }

    /**
     * Delete a remittance.
     *
     * @param Pool $pool The pool
     * @param Session $session The session
     * @param int $subscriptionId
     *
     * @return void
     */
    public function deleteRemittance(Pool $pool, Session $session, int $subscriptionId): void
    {
        $subscription = $pool->subscriptions()->find($subscriptionId);
        DB::transaction(function() use($pool, $session, $subscription) {
            $this->remittanceService->deleteRemittance($pool, $session, $subscription->payable->id);
            $this->subscriptionService->unsetPayableSession($session, $subscription);
        });
    }

    /**
     * Get the bids for a given session.
     *
     * @param Session $session    The session
     * @param int $page
     *
     * @return Collection
     */
    public function getBiddings(Session $session, int $page = 0): Collection
    {
        $biddings = $session->biddings()->with('member');
        if($page > 0 )
        {
            $biddings->take($this->tenantService->getLimit());
            $biddings->skip($this->tenantService->getLimit() * ($page - 1));
        }
        return $biddings->get();
    }

    /**
     * Create a bidding.
     *
     * @param Session $session The session
     * @param Member $member The member
     * @param int $amountBid
     * @param int $amountPaid
     *
     * @return void
     */
    public function createBidding(Session $session, Member $member, int $amountBid, int $amountPaid): void
    {
        $bidding = new Bidding();
        $bidding->amount_bid = $amountBid;
        $bidding->amount_paid = $amountPaid;
        $bidding->member()->associate($member);
        $bidding->session()->associate($session);
        $bidding->save();
    }

    /**
     * Delete a bidding.
     *
     * @param Session $session The session
     * @param int $biddingId
     *
     * @return void
     */
    public function deleteBidding(Session $session, int $biddingId): void
    {
        $session->biddings()->where('id', $biddingId)->delete();
    }

    /**
     * @param Session $session
     *
     * @return Collection
     */
    private function getSessionPayables(Session $session): Collection
    {
        $sessions = $this->tenantService->round()->sessions;
        return $session->payables->map(function($payable) use($sessions) {
            $payable->amount = $sessions->filter(function($session) use($payable) {
                return $session->enabled($payable->subscription->pool);
            })->count() * $payable->subscription->pool->amount;
            return $payable;
        });
    }

    /**
     * Get the amount available for bidding.
     *
     * @param Session $session    The session
     *
     * @return int
     */
    public function getAmountAvailable(Session $session): int
    {
        // Get the ids of all the sessions until the current one.
        $sessionIds = $this->tenantService->round()->sessions()
            ->where('start_at', '<=', $session->start_at)->pluck('id');
        // The amount available for bidding is the sum of the amounts paid for remittances,
        // the amounts paid in the biddings, and the refunds, for all the sessions.
        $payableIds = Payable::whereIn('session_id', $sessionIds)->pluck('id');

        return Remittance::whereIn('payable_id', $payableIds)->sum('amount_paid') +
            Bidding::whereIn('session_id', $sessionIds)->get()
                ->reduce(function($sum, $bidding) {
                    return $sum + $bidding->amount_paid - $bidding->amount_bid;
                }, 0) +
            Refund::whereIn('session_id', $sessionIds)
                ->with('bidding')->get()->sum('bidding.amount_bid');
    }

    /**
     * Get all the cash biddings of a given session
     *
     * @param Session $session
     * @return array
     */
    public function getSessionBiddings(Session $session): array
    {
        $payables = $this->getSessionPayables($session);

        $paidSum = 0;
        $poolBiddings = $payables->map(function($payable) use(&$paidSum) {
            $amountPaid = $payable->remittance->amount_paid ?? 0;
            $paidSum += $amountPaid;
            return (object)[
                'id' => 0, // $payable->subscription->id,
                'title' => $payable->subscription->member->name,
                'amount' => Currency::format($payable->amount),
                'paid' => Currency::format($amountPaid),
            ];
        });
        $bidSum = 0;
        $cashBiddings = $this->getBiddings($session)->map(function($bidding) use(&$bidSum, &$paidSum) {
            $bidSum += $bidding->amount_bid;
            $paidSum += $bidding->amount_paid;
            return (object)[
                'id' => $bidding->id,
                'title' => $bidding->member->name,
                'amount' => Currency::format($bidding->amount_bid),
                'paid' => Currency::format($bidding->amount_paid),
            ];
        });

        return [$poolBiddings->merge($cashBiddings),
            ['bid' => Currency::format($bidSum), 'paid' => Currency::format($paidSum)]];
    }

    /**
     * @param Pool $pool
     * @param int $sessionId
     *
     * @return array|stdClass
     */
    public function getRemittanceFigures(Pool $pool, int $sessionId = 0)
    {
        return $this->planningService->getRemittanceFigures($pool, $sessionId);
    }
}
