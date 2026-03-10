<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\HubspotHousecallMiddleware2;
use App\Http\Controllers\HubSpotController;
use App\Http\Controllers\HouseCallProController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});





Route::post('/sync', function (Request $request) {
    // Your sync logic here (if needed).
})->middleware('hubspot.housecall');

Route::post('/sync-data', function (Request $request) {
    $middleware = new HubspotHousecallMiddleware2();
    return $middleware->handle($request, function () {});
});

Route::get('/hubspot/contacts', [HubSpotcontroller::class, 'getHubSpotContacts']);

Route::post('/hubspot/contacts', [HubSpotController::class, 'createHubSpotContacts']);

Route::get('/housecallpro/customers', [HouseCallProController::class, 'getHousecallProCustomers']);
Route::get('/housecallpro/sync-customers', [HouseCallProController::class, 'syncCustomers']);
Route::get('/housecallpro/sync-estimates', [HouseCallProController::class, 'syncEstimates']);
Route::get("/housecallpro/sync-jobs", [HouseCallProController::class, 'syncJobs']);
Route::get('hubspot/search/{phone}',[HubSpotcontroller::class, 'getMobile']);

Route::post('/housecallpro/webhook', [HouseCallProController::class, 'webhook']);

Route::get('/housecall/customers', function () {
    $middleware = new HubspotHousecallMiddleware2();
    return response()->json($middleware->fetchHousecallRecords());
});

Route::post('/hubspot/customers', function (Request $request){
    $middleware = new HubspotHousecallMiddleware2();
    return $middleware->syncToHubspot($request->all());
});

Route::post('/housecall/customers', function (Request $request){
    $middleware = new HubspotHousecallMiddleware2();
    return $middleware->syncToHousecallPro($request->all());
});
