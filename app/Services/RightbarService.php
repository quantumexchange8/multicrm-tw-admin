<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\TradingAccountRebateRevenue;
use Carbon\Carbon;
use Illuminate\Support\Str;

class RightbarService
{
    protected function getPaymentQuery($category, $type, $status)
    {
        $query = Payment::query()
            ->where('category', $category)
            ->where('type', $type);

        if ($status === 'Submitted') {
            $query->whereDate('created_at', now());
        } elseif ($status === 'Successful') {
            $query->whereDate('approval_date', now());
        }

        return $query->sum('amount');
    }
    public function getTotalApprovedDeposit()
    {
        return $this->getPaymentQuery('payment', 'Deposit', 'Successful');
    }

    public function getTotalPendingDeposit()
    {
        return $this->getPaymentQuery('payment', 'Deposit', 'Submitted');
    }
    public function getTotalApprovedWithdrawal()
    {
        return $this->getPaymentQuery('payment', 'Withdrawal', 'Successful');
    }
    public function getTotalPendingWithdrawal()
    {
        return $this->getPaymentQuery('payment', 'Withdrawal', 'Submitted');
    }

    public function getTotalApprovedRebate()
    {
        return TradingAccountRebateRevenue::query()
            ->where('status', 'approve')
            ->whereDate('created_at', Carbon::now())
            ->sum('revenue');
    }

    public function getTotalPendingRebate()
    {
        return TradingAccountRebateRevenue::query()
            ->where('status', 'pending')
            ->whereDate('created_at', Carbon::now())
            ->sum('revenue');
    }

    public function getPendingWithdrawalCount()
    {
        return Payment::query()
            ->where('category', 'payment')
            ->where('type', 'Withdrawal')
            ->where('status', 'Submitted')
            ->count();
    }
}
