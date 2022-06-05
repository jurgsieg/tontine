<?php

namespace Siak\Tontine\Service\Figures;

use Illuminate\Support\Collection;
use Siak\Tontine\Model\Fund;
use stdClass;

use function compact;

trait TableTrait
{
    /**
     * @param mixed $defaultValue
     *
     * @return stdClass
     */
    private function makeFigures($defaultValue): stdClass
    {
        $figures = new stdClass();

        $figures->cashier = new stdClass();
        $figures->cashier->start = $defaultValue;
        $figures->cashier->recv = $defaultValue;
        $figures->cashier->end = $defaultValue;

        $figures->deposit = new stdClass();
        $figures->deposit->count = $defaultValue;
        $figures->deposit->amount = $defaultValue;

        $figures->remittance = new stdClass();
        $figures->remittance->count = $defaultValue;
        $figures->remittance->amount = $defaultValue;

        return $figures;
    }

    /**
     * @param stdClass $figures
     *
     * @return stdClass
     */
    private function formatCurrencies(stdClass $figures): stdClass
    {
        $figures->cashier->start = Fund::format($figures->cashier->start, true);
        $figures->cashier->recv = Fund::format($figures->cashier->recv, true);
        $figures->cashier->end = Fund::format($figures->cashier->end, true);
        $figures->deposit->amount = Fund::format($figures->deposit->amount, true);
        $figures->remittance->amount = Fund::format($figures->remittance->amount, true);

        return $figures;
    }

    /**
     * @param Fund $fund
     * @param Collection $sessions
     * @param Collection $subscriptions
     *
     * @return array
     */
    private function getExpectedFigures(Fund $fund, Collection $sessions, Collection $subscriptions): array
    {
        $cashier = 0;
        $depositCount = $subscriptions->count();
        $depositAmount = $fund->amount * $subscriptions->count();
        $remittanceAmount = $fund->amount * $sessions->filter(function($session) use($fund) {
            return !$session->disabled($fund);
        })->count();

        $expectedFigures = [];
        foreach($sessions as $session)
        {
            if($session->disabled($fund))
            {
                $expectedFigures[$session->id] = $this->makeFigures('');
                continue;
            }

            $figures = $this->makeFigures(0);

            $figures->cashier->start = $cashier;
            $figures->cashier->recv = $cashier + $depositAmount;
            $figures->cashier->end = $cashier + $depositAmount;
            $figures->deposit->count = $depositCount;
            $figures->deposit->amount = $depositAmount;
            while($figures->cashier->end >= $remittanceAmount)
            {
                $figures->remittance->count++;
                $figures->remittance->amount += $remittanceAmount;
                $figures->cashier->end -= $remittanceAmount;
            }

            $cashier = $figures->cashier->end;
            $expectedFigures[$session->id] = $this->formatCurrencies($figures);
        }

        return $expectedFigures;
    }

    /**
     * @param Fund $fund
     * @param Collection $sessions
     * @param Collection $subscriptions
     *
     * @return array
     */
    private function getAchievedFigures(Fund $fund, Collection $sessions, Collection $subscriptions): array
    {
        $cashier = 0;
        $remittanceAmount = $fund->amount * $sessions->filter(function($session) use($fund) {
            return !$session->disabled($fund);
        })->count();

        $achievedFigures = [];
        foreach($sessions as $session)
        {
            if($session->disabled($fund) || $session->pending)
            {
                $achievedFigures[$session->id] = $this->makeFigures('');
                continue;
            }

            $figures = $this->makeFigures(0);

            $figures->cashier->start = $cashier;
            $figures->cashier->recv = $cashier;
            foreach($subscriptions as $subscription)
            {
                if(($subscription->receivables[$session->id]->deposit))
                {
                    $figures->deposit->count++;
                    $figures->deposit->amount += $fund->amount;
                    $figures->cashier->recv += $fund->amount;
                }
            }
            $figures->cashier->end = $figures->cashier->recv;
            foreach($session->payables as $payable)
            {
                if(($payable->remittance))
                {
                    $figures->remittance->count++;
                    $figures->remittance->amount += $remittanceAmount;
                    $figures->cashier->end -= $remittanceAmount;
                }
            }

            $cashier = $figures->cashier->end;
            $achievedFigures[$session->id] = $this->formatCurrencies($figures);
        }

        return $achievedFigures;
    }

    /**
     * Get the receivables of a given fund.
     *
     * Will return basic data on subscriptions.
     *
     * @param Fund $fund
     *
     * @return array
     */
    public function getReceivables(Fund $fund): array
    {
        $sessions = $this->tenantService->round()->sessions()->get();
        $subscriptions = $fund->subscriptions()->with(['member'])->get();
        $figures = new stdClass();
        $figures->expected = $this->getExpectedFigures($fund, $sessions, $subscriptions);

        return compact('fund', 'sessions', 'subscriptions', 'figures');
    }

    /**
     * Get the payables of a given fund.
     *
     * @param Fund $fund
     * @param array $with
     *
     * @return Collection
     */
    private function _getSessions(Fund $fund, array $with = []): Collection
    {
        /*return $this->tenantService->round()->sessions()->with($with)
            ->get()->each(function($session) use($fund) {
                // Keep only the payables of the current fund.
                $session->setRelation('payables', $session->payables->filter(function($payable) use($fund) {
                    return $payable->subscription->fund_id === $fund->id;
                }));
            });*/
        $with['payables'] = function($query) use($fund) {
            $query->join('subscriptions', 'payables.subscription_id', '=', 'subscriptions.id')
                ->where('subscriptions.fund_id', $fund->id);
        };
        return $this->tenantService->round()->sessions()->with($with)->get();
    }

    /**
     * Get the payables of a given fund.
     *
     * @param Fund $fund
     *
     * @return array
     */
    public function getPayables(Fund $fund): array
    {
        $sessions = $this->_getSessions($fund, ['payables.subscription']);
        $subscriptions = $fund->subscriptions()->with(['payable', 'member'])->get();
        $figures = new stdClass();
        $figures->expected = $this->getExpectedFigures($fund, $sessions, $subscriptions);

        // Set the subscriptions that will be pay at each session.
        // Pad with 0's when the beneficiaries are not yet set.
        $sessions->each(function($session) use($figures, $fund) {
            if($session->disabled($fund))
            {
                return;
            }
            // Pick the subscriptions ids, and fill with 0's to the max available.
            $session->beneficiaries = $session->payables->map(function($payable) {
                return $payable->subscription_id;
            })->pad($figures->expected[$session->id]->remittance->count, 0);
        });

        // Separate subscriptions that already have a beneficiary assigned from the others.
        [$subscriptions, $beneficiaries] = $subscriptions->partition(function($subscription) {
            return !$subscription->payable->session_id;
        });
        $beneficiaries = $beneficiaries->pluck('member.name', 'id');
        $subscriptions = $subscriptions->pluck('member.name', 'id');
        $subscriptions->prepend('', 0);

        return compact('fund', 'sessions', 'subscriptions', 'beneficiaries', 'figures');
    }

    /**
     * Get the receivables of a given fund.
     *
     * Will return extended data on subscriptions.
     *
     * @param Fund $fund
     *
     * @return array
     */
    public function getFigures(Fund $fund): array
    {
        $subscriptions = $fund->subscriptions()->with(['member', 'receivables.deposit'])
            ->get()->each(function($subscription) {
                $subscription->setRelation('receivables', $subscription->receivables->keyBy('session_id'));
            });
        $sessions = $this->_getSessions($fund, ['payables.remittance']);
        $figures = new stdClass();
        $figures->expected = $this->getExpectedFigures($fund, $sessions, $subscriptions);
        $figures->achieved = $this->getAchievedFigures($fund, $sessions, $subscriptions);

        return compact('fund', 'sessions', 'subscriptions', 'figures');
    }
}
