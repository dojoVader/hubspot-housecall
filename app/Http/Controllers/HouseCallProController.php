<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Middleware\HubspotHousecallMiddleware2;

class HouseCallProController extends Controller
{

    public function getHousecallProCustomers()
    {
        $middleware = new HubspotHousecallMiddleware2();
        // $middleware = new HubspotHousecallMiddleware();

        $records = $middleware->fetchHousecallRecords();

        return response()->json($records);
    }
}