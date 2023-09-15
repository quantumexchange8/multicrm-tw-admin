<?php

namespace App\Services\MetaTrader5\Order;

use App\Models\User as UserModel;
use App\Services\MetaTrader5\BaseApi;
use AleeDhillon\MetaFive\Facades\Client;
class FetchOrderHistory extends BaseApi
{
    public function execute($ticket)
    {
        return Client::getOrderHistory($ticket);
    }
}
