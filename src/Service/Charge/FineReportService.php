<?php

namespace Siak\Tontine\Service\Charge;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Siak\Tontine\Model\Session;
use Siak\Tontine\Service\LocaleService;
use Siak\Tontine\Service\TenantService;

class FineReportService
{
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
     * @param Session $session
     *
     * @return Collection
     */
    private function getCurrentSessionBills(Session $session): Collection
    {
        // Count the session bills
        return DB::table('fine_bills')
            ->select('charge_id', DB::raw('count(*) as total'), DB::raw('sum(amount) as amount'))
            ->join('bills', 'fine_bills.bill_id', '=', 'bills.id')
            ->where('fine_bills.session_id', $session->id)
            ->groupBy('charge_id')
            ->get();
    }

    /**
     * @param Session $session
     *
     * @return Collection
     */
    private function getPreviousSessionsBills(Session $session): Collection
    {
        // Count the session bills
        $sessionIds = $this->tenantService->round()->sessions()
            ->where('start_at', '<=', $session->start_at)->pluck('id');
        return DB::table('fine_bills')
            ->select('charge_id', DB::raw('count(*) as total'), DB::raw('sum(amount) as amount'))
            ->join('bills', 'fine_bills.bill_id', '=', 'bills.id')
            ->whereIn('fine_bills.session_id', $sessionIds)
            ->groupBy('charge_id')
            ->get();
    }

    /**
     * @param Session $session
     *
     * @return Collection
     */
    private function getCurrentSessionSettlements(Session $session): Collection
    {
        // Count the session bills
        $query = DB::table('settlements')->select('charge_id',
            DB::raw('count(*) as total'), DB::raw('sum(amount) as amount'))
            ->join('bills', 'settlements.bill_id', '=', 'bills.id')
            ->join('fine_bills', 'fine_bills.bill_id', '=', 'bills.id')
            ->where('settlements.session_id', $session->id)
            ->groupBy('charge_id');
        return $query->get();
    }

    /**
     * @param Session $session
     *
     * @return Collection
     */
    private function getPreviousSessionsSettlements(Session $session): Collection
    {
        // Count the session bills
        $sessionIds = $this->tenantService->round()->sessions()
            ->where('start_at', '<=', $session->start_at)->pluck('id');
        $query = DB::table('settlements')->select('charge_id',
            DB::raw('count(*) as total'), DB::raw('sum(amount) as amount'))
            ->join('bills', 'settlements.bill_id', '=', 'bills.id')
            ->join('fine_bills', 'fine_bills.bill_id', '=', 'bills.id')
            ->whereIn('settlements.session_id', $sessionIds)
            ->groupBy('charge_id');
        return $query->get();
    }

    /**
     * Format the amounts in the settlements
     *
     * @param Collection $settlements
     *
     * @return Collection
     */
    private function formatAmounts(Collection $settlements): Collection
    {
        return $settlements->map(function($settlement) {
            $settlement->amount = $this->localeService->formatMoney((int)$settlement->amount);
            return $settlement;
        });
    }

    /**
     * Get the report of bills
     *
     * @param Session $session
     *
     * @return array
     */
    public function getBills(Session $session): array
    {
        $currentBills = $this->getCurrentSessionBills($session);
        $previousBills = $this->getPreviousSessionsBills($session);
        return [
            'total' => [
                'current' => $currentBills->pluck('total', 'charge_id'),
                'previous' => $previousBills->pluck('total', 'charge_id'),
            ],
        ];
    }

    /**
     * Get the report of settlements
     *
     * @param Session $session
     *
     * @return array
     */
    public function getSettlements(Session $session): array
    {
        $currentSettlements = $this->getCurrentSessionSettlements($session);
        $previousSettlements = $this->getPreviousSessionsSettlements($session);
        return [
            'zero' => $this->localeService->formatMoney(0),
            'total' => [
                'current' => $currentSettlements->pluck('total', 'charge_id'),
                'previous' => $previousSettlements->pluck('total', 'charge_id'),
            ],
            'amount' => [
                'current' => $this->formatAmounts($currentSettlements)->pluck('amount', 'charge_id'),
                'previous' => $this->formatAmounts($previousSettlements)->pluck('amount', 'charge_id'),
            ],
        ];
    }
}
