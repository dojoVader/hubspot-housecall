<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Middleware\HubspotHousecallMiddleware;

class HubSpotController extends Controller
{



    public function getHubSpotContacts()
    {
        $middleware = new HubSpotHousecallMiddleware();
        $records = $middleware->getHubSpotContacts();

        return response()->json($records);
    }

    public function getContact(string $propertyKey, string $propertyValue){

    }

    public function getMobile(Request $request,$phone): \Illuminate\Http\JsonResponse
    {

        $middleware = new HubspotHousecallMiddleware();
        $data = $middleware->getHubspotContactByPhone($phone);
        return response()->json($data);
    }


    public function createHubSpotContacts(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255'
        ]);

        // prepare data for the hubspot API
        $hubspotData = [
            'firstname' => $validatedData['firstname'],
            'lastname' => $validatedData['lastname'],
            'email' => $validatedData['email'],
            'phone' => $validatedData['phone'] ?? null,
            'company' => $validatedData['company'] ?? null,
        ];

        // call the middleware method to create the customer
        // $response = $middleware->createHubSpotCustmer($hubspotData);
        $response = app(HubSpotHousecallMiddleware::class)->createHubSpotCustomer($hubspotData);


        if ($response->successful()) {
            return response()->json([
                'message' => 'customer created Successfully in Hubspot.',
                'data' => $response->json(),
            ]);
        }

        return response()->json([
            'message'=> 'Failed to create customer in Hubspot. ',
            'error' => $response->json(),
        ], $response->status());


    }
}
