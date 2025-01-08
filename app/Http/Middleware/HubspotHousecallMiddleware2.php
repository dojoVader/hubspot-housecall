<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HubspotHousecallMiddleware2
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the request has required data for processing
        if (!$request->has(['hubspot_data', 'housecall_pro_data'])) {
            return response()->json(['error' => 'Missing data for integration.'], 400);
        }

        // Extract data from the request
        $hubspotData = $request->input('hubspot_data');
        $housecallData = $request->input('housecall_pro_data');

        // Fetch all existing records from HubSpot and Housecall Pro
        $existingHubSpotRecords = $this->fetchHubSpotRecords();
        $existingHousecallRecords = $this->fetchHousecallRecords();

        // Sync data from HubSpot to Housecall Pro
        $housecallResponse = $this->syncToHousecallPro($hubspotData);

        if ($housecallResponse->failed()) {
            return response()->json(['error' => 'Failed to sync to Housecall Pro'], 500);
        }

        // Sync data from Housecall Pro to HubSpot
        $hubspotResponse = $this->syncToHubSpot($housecallData);

        if ($hubspotResponse->failed()) {
            return response()->json(['error' => 'Failed to sync to HubSpot'], 500);
        }

        // Return the fetched and synced data as a response
        return response()->json([
            'message' => 'Data synced successfully.',
            'existing_hubspot_records' => $existingHubSpotRecords,
            'existing_housecall_records' => $existingHousecallRecords
        ]);
    }

    /**
     * Sync data to Housecall Pro API.
     *
     * @param  array  $data
     * @return \Illuminate\Http\Client\Response
     */
    protected function syncToHousecallPro(array $data)
    {
        $housecallProApiUrl = env('HOUSECALL_PRO_API_URL');
        $housecallProApiKey = env('HOUSECALL_PRO_API_KEY');

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->post($housecallProApiUrl, $data);
    }

    /**
     * Sync data to HubSpot API.
     *
     * @param  array  $data
     * @return \Illuminate\Http\Client\Response
     */
    protected function syncToHubSpot(array $data)
    {
        $hubspotApiUrl = env('HUBSPOT_API_URL');
        $hubspotApiKey = env('HUBSPOT_API_KEY');

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->post($hubspotApiUrl, $data);
    }

    /**
     * Fetch all existing records from HubSpot API.
     *
     * @return array
     */
    // protected function fetchHubSpotRecords()
    public function getHubSpotContacts()
    {
        $hubspotApiUrl = env('HUBSPOT_API_URL');
        $hubspotApiKey = env('HUBSPOT_API_KEY');

        // with SSL
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $hubspotApiKey,
        //     'Content-Type' => 'application/json',
        // ])->get($hubspotApiUrl . '/contacts');

        // if ($response->successful()) {
        //     return $response->json();
        // }

        // return ['error' => 'Failed to fetch HubSpot records'];

        // Bypass SSL
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->get($hubspotApiUrl . '/objects/contacts');

          if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch HubSpot records'];
    }

    

    // public function gethubspotDeals()
    // {
    //     $hubspotApiUrl = env('HIBSPOT_API_URL');

    // }


    // Create Hubspot Contacts
    public function createHubSpotCustomer(array $data)
    {
        $hubspotApiUrl = env('HUBSPOT_API_URL');
        $hubspotApiKey = env('HUBSPOT_API_KEY');

        // Map the data to HubSpot property names
        $properties = [
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'],
        ];

    
        // with SSL
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $hubspotApiKey,
        //     'Content-Type' => 'application/json',
        // ])->post($hubspotApiUrl . '/objects/contacts', [
        //     'properties'=> [
        //         'firstname' => $data['firstname'],
        //         'lastname' => $data['lastname'],
        //         'email' => $data['email'],
        //         'phone' => $data['phone'] ?? '',
        //         'company' => $data['company'] ?? ''

        //     ]
        // ]);

        // without SSL
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->post($hubspotApiUrl . '/objects/contacts', [
            'properties' => [
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? '',
                'company' => $data['company'] ?? ''
            ]
        ]);

        // dd($response->status(), $response->body());

        // \Log::info('HubSpot Response:', [
        //     'status' => $response->status(),
        //     'body' => $response->body(),
        // ]);

        return $response;
        
    }



    

    /**
     * Fetch all existing records from Housecall Pro API.
     *
     * @return array
     */
    public function fetchHousecallRecords()
    {
        $housecallProApiUrl = env('HOUSECALL_PRO_API_URL');
        $housecallProApiKey = env('HOUSECALL_PRO_API_KEY');

        // with SSL
        // $response= Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $housecallProApiKey,
        //     'Content-Type' => 'application/json',
        // ])->get($housecallProApiUrl . '/customers');

        // wihout SSL
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($housecallProApiUrl . '/customers');

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch Housecall Pro records'];
    }
}


?>
