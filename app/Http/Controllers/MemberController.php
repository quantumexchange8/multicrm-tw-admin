<?php

namespace App\Http\Controllers;

use App\Http\Requests\Member\ResetMemberPasswordRequest;
use App\Http\Requests\Member\UpdateMemberRequest;
use App\Models\AccountType;
use App\Models\AccountTypeSymbolGroup;
use App\Models\IbAccountType;
use App\Models\IbAccountTypeSymbolGroupRate;
use App\Models\Payment;
use App\Models\RebateAllocation;
use App\Models\RebateAllocationRate;
use App\Models\RebateAllocationRequest;
use App\Models\SettingCountry;
use App\Models\TradingAccount;
use App\Models\TradingAccountRebateRevenue;
use App\Models\User;
use App\Services\RunningNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;
use function GuzzleHttp\Promise\all;

class MemberController extends Controller
{
    public function member_listing(Request $request)
    {
        $members = User::query()
            ->whereIn('role', ['member', 'ib'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('first_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('tradingAccounts', function ($accQuery) use ($search) {
                        $accQuery->where('meta_login', 'like', "%{$search}%");
                    });
                });
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $role = $request->input('role');
                $query->where('role', $role);
            })
            ->with(['tradingAccounts', 'media', 'upline'])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $countries = SettingCountry::query()
            ->select(['id', 'name_en', 'phone_code'])
            ->get();

        $accountTypes = AccountType::where('id', 1)->first();
        $getMemberSel = User::whereIn('role', ['member', 'ib'])->pluck('email')->toArray();

        return Inertia::render('Member/MemberListing', [
            'members' => $members,
            'countries' => $countries,
            'accountTypes' => $accountTypes,
            'getMemberSel' => $getMemberSel,
            'filters' => \Request::only(['search', 'role']),
        ]);
    }

    public function member_update(UpdateMemberRequest $request)
    {
        $user = User::find($request->user_id);

        $user->update([
            'first_name' => $request->first_name,
            'chinese_name' => $request->chinese_name,
            'dob' => $request->dob,
            'country' => $request->country,
            'phone' => $request->phone,
            'kyc_approval' => $request->kyc_approval,
            'kyc_approval_description' => $request->kyc_approval_description,
        ]);

        return redirect()->back()->with('toast', trans('public.The member’s detail has been edited successfully!'));

    }

    public function getIBAccountTypeSymbolGroupRate(Request $request)
    {
        $user = User::find($request->id);

        $upline = $user->upline_id;
        if ($upline) {
            $rates = IbAccountTypeSymbolGroupRate::whereRelation('ibAccountType', 'user_id', '=', $user->upline_id)
                ->whereRelation('ibAccountType', 'account_type', '=', $request->account_type)
                ->with('symbolGroup')
                ->get();
        } else {
            $rates = AccountTypeSymbolGroup::where('account_type', $request->account_type)
                ->with('symbolGroup')
                ->get();
        }

        return $rates;
    }

    public function getIbDownlineRebateInfo(Request $request)
    {
        $ib = IbAccountType::find($request->id);

        return IbAccountType::whereIn('id', $ib->getIbChildrenIds())
            ->with(['ofUser', 'symbolGroups.symbolGroup', 'accountType', 'ofUser.upline'])
            ->get();
    }

    public function getNewIbRebateInfo(Request $request)
    {
        $user = User::where('email', $request->new_ib)->first();

        return IbAccountTypeSymbolGroupRate::whereRelation('ibAccountType', 'user_id', '=', $user->id)
            ->whereRelation('ibAccountType', 'account_type', '=', 1)
            ->with('symbolGroup')
            ->get();
    }

    public function upgradeIb(Request $request)
    {
        $user = User::find($request->id);

        if ($user->role == 'member') {
            $ib_id = RunningNumberService::getID('broker_id');

            $user->ib_id = $ib_id;
            $user->role = 'ib';
            $user->assignRole('ib');
            $user->save();

            $upline = $user->upline;
            if ($upline) {
                $upline->increment('direct_ib');
                $upline->decrement('direct_client');
                while ($upline) {
                    $upline->increment('total_ib');
                    $upline->decrement('total_client');
                    $upline->save();
                    $upline = $upline->upline;
                }
            }
        }

        $exists = IbAccountType::where('user_id', $request->id)->where('account_type', $request->account_type)->exists();
        if (!$exists) {
            $ib = new IbAccountType;
            $ib->user_id = $request->id;
            $ib->account_type = $request->account_type;
            if ($user->upline_id) {
                $ib_upline = IbAccountType::where('user_id', $user->upline_id)->where('account_type', $request->account_type)->first();
                $ib->upline_id = $ib_upline->id;

                $hierarchyList = null;
                if (!$ib_upline->hierarchyList) {
                    $hierarchyList = "-" . $ib_upline->id . "-";
                } else {
                    $hierarchyList = $ib_upline->hierarchyList . $ib_upline->id . "-";
                }
                $ib->hierarchyList = $hierarchyList;
            }
            $ib->save();

            $symbolGroups = $request->ibGroupRates;
            foreach ($symbolGroups as $id => $value) {

                IbAccountTypeSymbolGroupRate::create([
                    'ib_account_type' => $ib->id,
                    'symbol_group' => $id,
                    'amount' => $value,
                ]);
            }
        } else {
            return response(['success' => false, 'message' => trans('public.Already have this account type')]);
        }
        return redirect()->back()->with('toast', trans('public.New IB Created'));
    }

    public function reset_member_password(ResetMemberPasswordRequest $request)
    {
        $user = User::find($request->user_id);
        $validated = $request->validated();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('toast', trans('public.The member portal password has been reset successfully'));
    }

    public function delete_member(Request $request)
    {
        $user = User::find($request->user_id);

        $user->delete();

        return redirect()->back()->with('toast', trans('public.The member has been deleted successfully'));
    }

    public function trading_account_listing()
    {

        $tradingAccs = TradingAccount::with(['ofUser', 'accountType'])->get();

        return Inertia::render('AccountListing/TradingAccountListing', [
            'tradingAccs' => $tradingAccs,
        ]);
    }

    public function rebate_allocation(Request $request)
    {


        return Inertia::render('Member/RebateAllocation', [
            'getAccountTypeSel' => AccountType::pluck('name', 'id')->toArray(),
            'get_ibs_sel' => User::where('role', 'ib')->pluck('email')->toArray()
        ]);
    }

    public function getIbListing(Request $request): \Illuminate\Http\JsonResponse
    {
        $defaultAccountSymbolGroup = AccountTypeSymbolGroup::query()
            ->when($request->filled('account_type'), function ($query) use ($request) {
                $account_type = $request->input('account_type');
                $query->where(function ($innerQuery) use ($account_type) {
                    $innerQuery->where('account_type', $account_type);
                });
            })
            ->with(['symbolGroup'])
            ->get();

        $accountTypeFilter = $request->input('account_type');

        $directIb = IbAccountType::query()
            ->when($accountTypeFilter, function ($query) use ($accountTypeFilter) {
                $query->where('account_type', $accountTypeFilter);
            })
            ->whereHas('ofUser', function ($query) {
                $query->where('role', 'ib')
                    ->whereNull('upline_id');
            })
            ->count();

        $directMember = IbAccountType::query()
            ->when($accountTypeFilter, function ($query) use ($accountTypeFilter) {
                $query->where('account_type', $accountTypeFilter);
            })
            ->whereHas('ofUser', function ($query) {
                $query->where('role', 'member')
                    ->whereNull('upline_id');
            })
            ->count();

        $totalIb = IbAccountType::query()
            ->when($accountTypeFilter, function ($query) use ($accountTypeFilter) {
                $query->where('account_type', $accountTypeFilter);
            })
            ->whereHas('ofUser', function ($query) {
                $query->where('role', 'ib');
            })
            ->count();

        $totalMember = IbAccountType::query()
            ->when($accountTypeFilter, function ($query) use ($accountTypeFilter) {
                $query->where('account_type', $accountTypeFilter);
            })
            ->whereHas('ofUser', function ($query) {
                $query->where('role', 'member');
            })
            ->count();


        $ibs = IbAccountType::query()
            ->with(['ofUser', 'symbolGroups.symbolGroup', 'accountType', 'ofUser.upline', 'upline.symbolGroups', 'upline.symbolGroups.symbolGroup','ofUser.tradingAccounts'])
            ->when($request->filled('account_type'), function ($query) use ($request) {
                $account_type = $request->input('account_type');
                $query->where(function ($innerQuery) use ($account_type) {
                    $innerQuery->where('account_type', $account_type);
                });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->whereHas('ofUser', function ($innerQuery) use ($search) {
                    $innerQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('ofUser.tradingAccounts', function ($accQuery) use ($search) {
                    $accQuery->where('meta_login', 'like', "%{$search}%");
                });
            })
            ->paginate(10);

        return response()->json([
            'defaultAccountSymbolGroup' => $defaultAccountSymbolGroup,
            'getIbListing' => $ibs,
            'directIb' => $directIb,
            'directMember' => $directMember,
            'totalIb' => $totalIb,
            'totalMember' => $totalMember,
        ]);
    }

    public function updateRebateAllocation(Request $request)
    {
        $curIb = IbAccountType::find($request->user_id);
        $upline = IbAccountType::find($request->upline_id);
        $downline = $curIb->downline;
        $curIbRate = IbAccountTypeSymbolGroupRate::where('ib_account_type', $request->user_id)->get()->keyBy('symbol_group');

        $validationErrors = new MessageBag();

        // Collect the validation errors for ibGroupRates
        foreach ($request->ibGroupRates as $key => $amount) {
            if ($upline) {
                $parent = IbAccountTypeSymbolGroupRate::with('symbolGroup')
                    ->where('ib_account_type', $upline->id)
                    ->where('symbol_group', $key)
                    ->first();
            } else {
                $parent = AccountTypeSymbolGroup::where('account_type', 1)
                    ->with('symbolGroup')
                    ->where('symbol_group', $key)
                    ->first();
            }

            if ($parent && $amount > $parent->amount) {
                $fieldKey = 'ibGroupRates.' . $key;
                $errorMessage = $parent->symbolGroup->name . trans('public. amount cannot be higher than ') . $parent->amount;
                $validationErrors->add($fieldKey, $errorMessage);
            }
        }

        // Collect the validation errors for each downline's ibGroupRates
        foreach ($downline as $child) {
            foreach ($request->ibGroupRates as $key => $amount) {
                $childRate = IbAccountTypeSymbolGroupRate::with('symbolGroup')
                    ->where('ib_account_type', $child->id)
                    ->where('symbol_group', $key)
                    ->first();

                if ($childRate && $amount < $childRate->amount) {
                    $fieldKey = 'ibGroupRates.' . $key;
                    $errorMessage = $childRate->symbolGroup->name . trans('public. amount cannot be lower than ') . $childRate->amount;
                    $validationErrors->add($fieldKey, $errorMessage);
                }
            }
        }

        // If there are validation errors, return them in the response
        if ($validationErrors->count() > 0) {
            return redirect()->back()->withErrors($validationErrors);
        }

        $rebateAllocation = RebateAllocation::create(['from' => $curIb->upline_id, 'to' => $request->user_id]);

        foreach ($request->ibGroupRates as $key => $amount) {

            RebateAllocationRate::create([
                'allocation_id' => $rebateAllocation->id,
                'symbol_group' => $key,
                'old' => $curIbRate[$key]->amount,
                'new' => $amount
            ]);

            $curIbRate[$key]->update(['amount' => $amount]);

        }

        return back()->with('toast', trans('public.The rebate allocation has been saved!'));
    }

    public function updateRebateStructure(Request $request)
    {
        $all = $request->ibGroupRates;
        $ibIds = collect($all)->keys();
        foreach ($all as $ib => $symbol_group) {
            $curIb = IbAccountType::find($ib);

            $exists = RebateAllocation::where('to', $ib)->whereRelation('request', 'status', '=', 'pending')->exists();
            if ($exists)
                return response()->json(['success' => false, 'message' => $ib . trans('public. has a pending request')]);

            $validationErrors = new MessageBag();

            $upline = $curIb->upline;
            $downline = $curIb->downline;
            foreach ($symbol_group as $key => $amount) {
                $parentRate = IbAccountTypeSymbolGroupRate::where('ib_account_type', $upline->id)->where('symbol_group', $key)->first();
                if ($amount > $parentRate->amount) {
                    $fieldKey = 'ibGroupRates.' . $ib . '.' . $key;
                    $errorMessage = 'Cannot higher than ' . $parentRate->amount;
                    $validationErrors->add($fieldKey, $errorMessage);
                }
            }

            foreach ($downline as $child) {
                foreach ($symbol_group as $key => $amount) {
                    $childRate = IbAccountTypeSymbolGroupRate::where('ib_account_type', $child->id)->where('symbol_group', $key)->first();
                    if ($amount < $childRate->amount) {
                        $fieldKey = 'ibGroupRates.' . $ib . '.' . $key;
                        $errorMessage = 'Cannot lower than ' . $childRate->amount;
                        $validationErrors->add($fieldKey, $errorMessage);
                    }
                }
            }

            // If there are validation errors, return them in the response
            if ($validationErrors->count() > 0) {
                return redirect()->back()->withErrors($validationErrors);
            }

        }

        $allocationRequest = RebateAllocationRequest::create([
            'requestBy' => Auth::id(),
            'handleBy' => Auth::id(),
            'description' => 'Edit Rebate Allocation Structure',
            'status' => 'Completed',
        ]);

        foreach ($all as $ib => $symbol_group) {
            $curIb = IbAccountType::find($ib);
            $downline = $curIb->downline;

            $rebateAllocation = RebateAllocation::create([
                'from' => $curIb->upline_id,
                'to' => $ib,
                'request_id' => $allocationRequest->id,
                'status' => 'approve',
            ]);
            $curIbRate = IbAccountTypeSymbolGroupRate::where('ib_account_type', $ib)->get()->keyBy('symbol_group');

            foreach ($symbol_group as $key => $amount) {
                $curAmount = $curIbRate[$key]->amount;
                RebateAllocationRate::create([
                    'allocation_id' => $rebateAllocation->id,
                    'symbol_group' => $key,
                    'old' => $curAmount,
                    'new' => $amount,
                ]);
                $curIbRate[$key]->update(['amount' => $amount]);
            }
        }
        return redirect()->back()->with('toast', trans('public.New rebate allocation has been saved!'));
    }

    public function rebate_payout(Request $request)
    {
        $query = TradingAccountRebateRevenue::query()
            ->whereRelation('ofUser', 'role', 'ib')
            ->whereNot('revenue', 0);

        $search = $request->search;
        $requestDate = $request->date;

        $dateRange = explode(' ~ ', $requestDate);

        if (count($dateRange) === 2) {
            $start_date = Carbon::createFromFormat('Y-m-d', $dateRange[0])->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $dateRange[1])->endOfDay();
            $query->whereBetween('closed_time', [$start_date, $end_date]);
        }

        if ($search) {
            $query->whereRelation('ofUser', function ($query) use ($search) {
                $query->where('first_name', '=', $search)
                    ->orWhere('ib_id', '=', $search);
            });
        }

        $histories = clone $query;

        $lists = $query->where('status', 'pending')
            ->select( DB::raw('DATE(closed_time) as date'), 'trading_account_rebate_revenue.ib_account_types_id', 'trading_account_rebate_revenue.user_id', 'trading_account_rebate_revenue.account_type', 'trading_account_rebate_revenue.meta_login', DB::raw('sum(volume) as total_volume'), DB::raw('sum(revenue) as total_revenue'))
            ->groupBy(['date', 'ib_account_types_id', 'user_id', 'account_type', 'meta_login'])
            ->with(['ofUser', 'ofAccountType'])
            ->paginate(10)
            ->withQueryString();

        $histories = $histories->where('status', 'approve')
            ->select('trading_account_rebate_revenue.ib_account_types_id', 'trading_account_rebate_revenue.user_id', 'trading_account_rebate_revenue.account_type', 'trading_account_rebate_revenue.meta_login', DB::raw('sum(volume) as total_volume'), DB::raw('sum(revenue) as total_revenue'), DB::raw('DATE(closed_time) as date'))
            ->groupBy(['ib_account_types_id', 'user_id', 'account_type', 'date', 'meta_login'])
            ->with(['ofUser', 'ofAccountType'])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Member/RebatePayout', [
            'lists' => $lists,
            'histories' => $histories,
            'filters' => \Request::only(['search', 'date'])
        ]);
    }

    public function getRebatePayoutDetails(Request $request)
    {
        return TradingAccountRebateRevenue::query()
            ->where('ib_account_types_id', $request->id)->whereRelation('ofUser', 'role', 'ib')
            ->where('status', $request->status)
            ->whereNot('revenue', 0)
            ->whereDate('closed_time', '=', $request->date)
            ->groupBy(['ticket_user_id', 'account_type', 'meta_login', 'final_net_rebate'])
            ->select('trading_account_rebate_revenue.ticket_user_id', 'trading_account_rebate_revenue.account_type', 'trading_account_rebate_revenue.meta_login', 'trading_account_rebate_revenue.final_net_rebate',  DB::raw('sum(volume) as total_volume'), DB::raw('sum(revenue) as total_revenue'))
            ->with(['ofTicketUser', 'ofAccountType'])
            ->get();
    }

    private function getUserNameFromId($userId)
    {
        $ibAccountType = IbAccountType::where('id', $userId)->with('ofUser')->first();

        // Check if the IbAccountType exists
        if ($ibAccountType) {
            $user = $ibAccountType->ofUser;

            if ($user) {
                return $user->first_name;
            }
        }

        return null;
    }

    public function approve_rebate_payout(Request $request)
    {
        $requestDate = $request->date;

        $dateRange = explode(' ~ ', $requestDate);

        switch ($request->type)
        {
            case('approve_single'):

                $start_date = count($dateRange) === 2 ? Carbon::createFromFormat('Y-m-d', $dateRange[0])->startOfDay() : Carbon::createFromFormat('Y-m-d', $request->close_date)->startOfDay();
                $end_date = count($dateRange) === 2 ? Carbon::createFromFormat('Y-m-d', $dateRange[1])->endOfDay() : Carbon::createFromFormat('Y-m-d', $request->close_date)->startOfDay();

                $rec = TradingAccountRebateRevenue::where('ib_account_types_id', $request->ib_account_types_id)->where('status', 'pending');

                if ($start_date) {
                    $rec =   $rec->where('closed_time', '>=', $start_date->startOfDay());
                }
                if ($end_date) {
                    $rec =  $rec->where('closed_time', '<=',  $end_date->endOfDay());
                }

                break;

            case('approve_selected'):

                $selectedItems = $request->selected_items;

                // Extract ib_account_types_ids and closed_dates from selected_items
                $ibAccountTypesIds = array_column($selectedItems, 'ib_account_types_id');
                $closedDates = array_column($selectedItems, 'closed_date');

                $closedDateRanges = [];
                foreach ($closedDates as $closedDate) {
                    $startOfDay = Carbon::createFromFormat('Y-m-d', $closedDate)->startOfDay();
                    $endOfDay = Carbon::createFromFormat('Y-m-d', $closedDate)->endOfDay();
                    $closedDateRanges[] = [$startOfDay, $endOfDay];
                }

                $rec = TradingAccountRebateRevenue::whereIn('ib_account_types_id', $ibAccountTypesIds)
                    ->where('status', 'pending')
                    ->where(function ($query) use ($closedDateRanges) {
                        foreach ($closedDateRanges as $range) {
                            $query->orWhereBetween('closed_time', $range);
                        }
                    });

                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => trans('public.Error Updating Rebate Payout'),
                ], 422);
        }

        $record = $rec->get();

        $rec->update(['status' => 'approve']);

        //https://stackoverflow.com/a/62550750
        $groups = $record->groupBy('ib_account_types_id');
        $finalGroup = $groups->mapWithKeys(function ($group, $key) {
            return [
                $key =>
                    [
                        'ib_account_types_id' => $key, // $key is what we grouped by, it'll be constant by each  group of rows
                        'total_revenue' => $group->sum('revenue'),
                        'total_volume' => $group->whereNotIn('revenue', [0])->sum('volume'),
                    ]
            ];
        });

        foreach ($finalGroup as $r) {

            $t = IbAccountType::find($r['ib_account_types_id']);

            $t->rebate_wallet = number_format($t->rebate_wallet + $r['total_revenue'], 2, '.', '');
            $t->trade_lot = number_format($t->trade_lot + $r['total_volume'], 2, '.', '');

            $t->save();

            $payment_id = RunningNumberService::getID('transaction');
            Payment::create([
                'user_id' => $t->user_id,
                'payment_id' => $payment_id,
                'category' => 'rebate_payout',
                'type' => 'RebateEarned',
                'amount' => $r['total_revenue'],
                'status' => 'Successful',
            ]);
        }

        return response()->json(['success' => true, 'message' => trans('public.Rebate payout approved successfully')]);

    }

    public function impersonate(User $user): \Symfony\Component\HttpFoundation\Response
    {
        $dataToHash = $user->first_name . $user->email . $user->id;
        $hashedToken = md5($dataToHash);

        Activity::create([
            'log_name' => 'user', // Specify the log name here
            'description' => Auth::user()->first_name . ' has IMPERSONATE ' . $user->first_name . ' with ID: ' . $user->id,
            'subject_type' => User::class,
            'subject_id' => Auth::id(),
            'causer_type' => get_class(auth()->user()),
            'causer_id' => auth()->id(),
            'event' => 'impersonate',
        ]);

        return Inertia::location("https://login.qcgbroker.com/admin_login/{$hashedToken}");
    }

    public function transfer_upline(Request $request)
    {
        $user = User::find($request->id);
        $newUpline = User::where('email', $request->new_upline);
        $newUpline = $newUpline->first();

        if ($request->new_upline && !$newUpline) {
            throw ValidationException::withMessages(['new_upline' => 'Upline Not Found']);
        }
        $oldUpline = User::find($user->upline_id);

        $role = $user->role;

        if ($role == "member") {
            if ($user->id == $newUpline->id) {
                throw ValidationException::withMessages(['new_upline' => 'Upline cannot be themselves']);
            }

            if ($oldUpline) {

                if ($newUpline->id == $oldUpline->id) {
                    throw ValidationException::withMessages(['new_upline' => 'Upline cannot be the same']);
                }

                $oldUpline->decrement('direct_client');
                $upline = $oldUpline;
                while ($upline) {
                    $upline->total_client -= ($user->total_client + 1);
                    $upline->save();
                    $upline = $upline->upline;
                }
            }
            if ($user->upline_id != $newUpline->id) {

                $newUpline->increment('direct_client');
                $upline = $newUpline;
                while ($upline) {
                    $upline->total_client += ($user->total_client + 1);
                    $upline->save();
                    $upline = $upline->upline;
                }

                if (str_contains($newUpline->hierarchyList, $user->id)) {
                    $newUpline->hierarchyList = $user->hierarchyList;
                    $newUpline->upline_id = $user->upline_id;
                    $newUpline->save();
                }

                if (empty($newUpline->hierarchyList)) {
                    $user_hierarchy = "-" . $newUpline->id . "-";
                } else {
                    $user_hierarchy = $newUpline->hierarchyList . $newUpline->id . "-";
                }


                $this->updateHierarchyList($user, $user_hierarchy, '-' . $user->id . '-');

                $user->hierarchyList = $user_hierarchy;
                $user->upline_id = $newUpline->id;
                $user->save();

                // Update hierarchyList for users with same upline_referral_id
                $sameUplineIdUsers = User::where('upline_id', $newUpline->id)->get();
                if ($sameUplineIdUsers) {
                    foreach ($sameUplineIdUsers as $sameUplineUser) {
                        $new_user_hierarchy = $newUpline->hierarchyList . $newUpline->id . "-";

                        if (!str_starts_with($new_user_hierarchy, '-')) {
                            $new_user_hierarchy = '-' . $new_user_hierarchy;
                        }

                        $this->updateHierarchyList($sameUplineUser, $new_user_hierarchy, "-" . $sameUplineUser->id . "-");
                        $sameUplineUser->hierarchyList = $new_user_hierarchy;
                        $sameUplineUser->upline_id = $newUpline->id;
                        $sameUplineUser->save();
                    }
                }
            }
        }
        return redirect()->back()->with('toast', 'Successfully Transfer');
    }

    private function updateHierarchyList($user, $list, $id)
    {
        $children = $user->downline;
        if (count($children)) {
            foreach ($children as $child) {
                //$child->hierarchyList = substr($list, -1) . substr($child->hierarchyList, strpos($child->hierarchyList, $id) + strlen($id));
                $child->hierarchyList = substr($list, 0, -1) . $id;
                $child->save();
                $this->updateHierarchyList($child, $list, $id . $child->id . '-');
            }
        }
    }
}
