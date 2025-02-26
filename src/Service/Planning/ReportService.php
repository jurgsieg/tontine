<?php

namespace Siak\Tontine\Service\Planning;

use Siak\Tontine\Model\Pool;
use Siak\Tontine\Service\LocaleService;
use Siak\Tontine\Service\TenantService;
use Siak\Tontine\Service\Traits\ReportTrait;
use stdClass;

use function collect;
use function compact;

class ReportService
{
    use ReportTrait;

    /**
     * @var LocaleService
     */
    protected LocaleService $localeService;

    /**
     * @var TenantService
     */
    protected TenantService $tenantService;

    /**
     * @param LocaleService $localeService
     * @param TenantService $tenantService
     */
    public function __construct(LocaleService $localeService, TenantService $tenantService)
    {
        $this->localeService = $localeService;
        $this->tenantService = $tenantService;
    }

    /**
     * Get the receivables of a given pool.
     *
     * Will return basic data on subscriptions.
     *
     * @param Pool $pool
     *
     * @return array
     */
    public function getReceivables(Pool $pool): array
    {
        $sessions = $this->tenantService->round()->sessions()->get();
        $subscriptions = $pool->subscriptions()->with(['member'])->get();
        $figures = new stdClass();
        $figures->expected = $this->getExpectedFigures($pool, $sessions, $subscriptions);

        return compact('pool', 'sessions', 'subscriptions', 'figures');
    }

    /**
     * Get the payables of a given pool.
     *
     * @param Pool $pool
     *
     * @return array
     */
    public function getPayables(Pool $pool): array
    {
        $sessions = $this->_getSessions($this->tenantService->round(), $pool, ['payables.subscription']);
        $subscriptions = $pool->subscriptions()->with(['payable', 'payable.session', 'member'])->get();
        $figures = new stdClass();
        $figures->expected = $this->getExpectedFigures($pool, $sessions, $subscriptions);

        // Set the subscriptions that will be pay at each session.
        // Pad with 0's when the beneficiaries are not yet set.
        $sessions->each(function($session) use($figures, $pool) {
            if($session->disabled($pool))
            {
                return;
            }
            // Pick the subscriptions ids, and fill with 0's to the max available.
            $session->beneficiaries = $session->payables->map(function($payable) {
                return $payable->subscription_id;
            })->pad($figures->expected[$session->id]->remitment->count, 0);
        });

        // Separate subscriptions that already have a beneficiary assigned from the others.
        [$beneficiaries, $subscriptions] = $subscriptions->partition(function($subscription) use($pool) {
            $session = $subscription->payable->session;
            return $session !== null && $session->enabled($pool);
        });
        $beneficiaries = $beneficiaries->pluck('member.name', 'id');
        // Show the list of subscriptions only for mutual tontines
        if($this->tenantService->tontine()->is_mutual)
        {
            $subscriptions = $subscriptions->pluck('member.name', 'id');
            $subscriptions->prepend('', 0);
        }
        else // if($this->tenantService->tontine()->is_financial)
        {
            $subscriptions = collect([]);
        }

        return compact('pool', 'sessions', 'subscriptions', 'beneficiaries', 'figures');
    }
}
