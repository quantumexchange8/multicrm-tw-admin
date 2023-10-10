<?php

namespace App\Console\Commands;

use App\Models\TradingUser;
use App\Services\CTraderService;
use Illuminate\Console\Command;

class UpdateTradingUserCommand extends Command
{
    protected $signature = 'update:trading-user';

    protected $description = 'Update trading user and trading account table every 30 minutes';

    public function handle(): void
    {
        try {
            $tradingUsers = TradingUser::all();
            (new CTraderService)->getUserInfo($tradingUsers);
        } catch (\Exception $e) {
            \Log::error('CTrader Error');
        }
    }
}
