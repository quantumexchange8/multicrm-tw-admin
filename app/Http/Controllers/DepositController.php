<?php

namespace App\Http\Controllers;

use App\Exports\DepositsExport;
use App\Models\Payment;
use App\Models\TradingUser;
use App\Models\User;
use App\Services\ChangeTraderBalanceType;
use App\Services\CTraderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class DepositController extends Controller
{
    public function deposit_report(Request $request)
    {
        return Inertia::render('Transaction/DepositReport');
    }

    public function getDepositReport(Request $request)
    {
        $search = $request->search;
        $requestDate = $request->date;
        $type = $request->type;

        $depositsQuery = Payment::query()->with(['ofUser', 'media'])
            ->where('category', 'payment')
            ->where('type', 'Deposit');

        if (auth()->user()->remark == "TW Test Trading Group") {
            $depositsQuery =  $depositsQuery->whereRelation('ofUser', 'remark', "TW Test Trading Group");
        }

        if ($type) {
            $depositsQuery =  $depositsQuery->where('channel', $type);
        }

        if ($requestDate) {
            $start_date = Carbon::createFromFormat('Y-m-d', $requestDate[0])->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $requestDate[1])->endOfDay();
            $depositsQuery->whereBetween('created_at', [$start_date, $end_date]);
        }

        if ($search) {
            $depositsQuery->where(function ($query) use ($search) {
                $query->whereRelation('ofUser', function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhere('payment_id', 'like', "%{$search}%");
            });
        }

        if ($request->has('export')) {
            return Excel::download(new DepositsExport($depositsQuery), Carbon::now() . '-deposits-report.xlsx');
        }

        $paginatedDeposits = clone $depositsQuery;
        $totalDepositQuery = clone $depositsQuery;

         $depositPending = $depositsQuery
            ->where('status', 'Submitted')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $paginatedDeposits =  $paginatedDeposits
            ->whereNot('status', 'Submitted')
            ->orderByDesc('approval_date')
            ->paginate(10)
            ->withQueryString();

        $totalDeposit = $totalDepositQuery->where('status', 'Successful')
            ->get()
            ->sum('amount');

        return response()->json([
            'deposits' => $paginatedDeposits,
            'depositPending' => $depositPending,
            'totalDeposit' => $totalDeposit,
        ]);
    }

    public function deposit_approval(Request $request)
    {
        $payment = Payment::find($request->id);
        $status = $request->status == "approve" ? "Successful" : "Rejected";
        $payment->status = $status;
        $payment->description = $request->comment;
        $payment->approval_date = Carbon::today();
        $payment->save();

        if ($payment->status == "Successful") {
            try {
                $trade = (new CTraderService)->createTrade($payment->to, $payment->amount, $payment->comment, ChangeTraderBalanceType::DEPOSIT);
                $payment->ticket = $trade->getTicket();

                $user = User::find($payment->user_id);
                $user->total_deposit += $payment->amount;
                $user->save();

                return redirect()->back()->with('toast', trans('public.Successfully Approved Deposit Request'));
            } catch (\Throwable $e) {
                if ($e->getMessage() == "Not found") {
                    TradingUser::firstWhere('meta_login', $payment->to)->update(['acc_status' => 'Inactive']);
                }
                \Log::error($e->getMessage() . " " . $payment->payment_id);
            }
        }

        return redirect()->back()->with('toast', trans('public.Successfully Rejected Deposit Request'));

    }
}
