<?php
namespace Pay\Orderpay\Facades;

use Illuminate\Support\Facades\Facade;

class Orderpay extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'orderpay';
    }
}