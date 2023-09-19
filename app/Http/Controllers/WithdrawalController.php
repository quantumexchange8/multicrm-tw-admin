<?php

namespace App\Http\Controllers;

use App\Http\Requests\WithdrawalApprovalRequest;
use App\Models\GatewayExchangeRate;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\TradingUser;
use App\Models\User;
use App\Services\ChangeTraderBalanceType;
use App\Services\CTraderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WithdrawalController extends Controller
{
    public function withdrawal_report(Request $request)
    {
        return Inertia::render('Transaction/WithdrawalReport');
    }

    public function getPendingTransaction(Request $request)
    {
        $search = $request->search;
        $requestDate = $request->date;
        $type = $request->type;

        $withdrawals = Payment::query()->with('ofUser')
            ->where('category', 'payment')
            ->where('type', 'Withdrawal');

        if (auth()->user()->remark == "vietnam plan") {
            $withdrawals =  $withdrawals->whereRelation('ofUser', 'remark', "vietnam plan");
        }

        if ($type) {
            $withdrawals =  $withdrawals->where('channel', $type);
        }

        if ($requestDate) {
            $start_date = Carbon::createFromFormat('Y-m-d', $requestDate[0])->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $requestDate[1])->endOfDay();
            $withdrawals->whereBetween('created_at', [$start_date, $end_date]);
        }

        if ($search) {
            $withdrawals->whereRelation('ofUser', function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $histories = clone $withdrawals;
        $withdrawals =  $withdrawals
            ->where('status', 'Submitted')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $histories =  $histories
            ->whereNot('status', 'Submitted')
            ->orderByDesc('approval_date')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'withdrawals' => $withdrawals,
            'histories' => $histories
        ]);
    }

    public function withdrawal_approval(WithdrawalApprovalRequest $request)
    {
        $payment = Payment::find($request->id);

        $status = $request->status == "approve" ? "Successful" : "Rejected";
        $payment->status = $status;
        $payment->description = $request->comment;
        $payment->approval_date = Carbon::today();
        $payment->save();

        if ($payment->status == "Successful") {
            $real_amount = $payment->amount;
            $payment->update([
                'real_amount' => $real_amount,
                'payment_charges' => null
            ]);

            return redirect()->back()->with('toast', 'Successfully Updated Withdrawal Status');
        } else {
            $user = User::find($payment->user_id);
            $user->cash_wallet += $payment->amount;
            $user->save();
        }
        return redirect()->back()->with('toast', 'Successfully Rejected Withdrawal Request');
    }
}
