<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HubspotHousecallMiddleware
{
    private string $hubspotApiUrl;
    private string $hubspotApiKey;

    private string $housecallProApiUrl;

    private string $housecallProApiKey;

    private int $pageSize = 0;



    public function __construct()
    {
        $this->hubspotApiUrl = env('HUBSPOT_API_URL');
        $this->hubspotApiKey = env('HUBSPOT_API_KEY');
        $this->housecallProApiUrl = env('HOUSECALL_PRO_API_URL');
        $this->housecallProApiKey = env('HOUSECALL_PRO_API_KEY');
        $this->pageSize = env('HUBSPOT_LIMIT_PAGE_SIZE');
    }

    public function batchUpsetHubspotContacts($input)
    {

        // Bypass SSL

        $batchInput = [
            "inputs" => $input
        ];
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->post($this->hubspotApiUrl . 'crm/v3/objects/contacts/batch/upsert', $batchInput);

        if ($response->successful()) {
            return ['success' => ($response->body())];
        }

        return ['error' => ($response->body())];
    }

    public function updateHubspotContact($recordId,$input)
    {

        // Bypass SSL


        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->patch($this->hubspotApiUrl . "crm/v3/objects/contacts/{$recordId}", $input);

        if ($response->successful()) {
            return ['success' => ($response->body())];
        }

        return ['error' => ($response->body())];
    }

    public function searchHubspotCustomers($email,$phone,$housepro_id)
    {

        $data = [
            'filterGroups' => [],
            'properties' => ['firstname', 'lastname', 'email', 'phone', 'housepro_id'],
            'limit' => 5
        ];




            if(isset($phone)){
                $data['filterGroups'][] = ['filters' => [
                    [
                        'propertyName' => 'phone',
                        'operator' => 'EQ',
                        'value' => $phone
                    ]
                ]];
            }
            if(isset($email)){
                $data['filterGroups'][] = ['filters' => [
                    [
                        'propertyName' => 'email',
                        'operator' => 'EQ',
                        'value' => $email
                    ]
                ]];
            }
            if(isset($housepro_id)){
                $data['filterGroups'][] = ['filters' => [
                    [
                        'propertyName' => 'housepro_id',
                        'operator' => 'EQ',
                        'value' => $housepro_id
                    ]
                ]];
            }






        // Bypass SSL

        $searchFilter =
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->post($this->hubspotApiUrl . 'crm/v3/objects/contacts/search', $data);

        if ($response->successful()) {
            return json_decode($response->body());
        }

        return ['error' => ($response->body())];
    }



    /**
     * Fetch all existing records from HubSpot API.
     *
     * @return array
     */
    // protected function fetchHubSpotRecords()
    public function getHubSpotContacts(): array
    {


        // Bypass SSL
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->hubspotApiUrl . 'objects/contacts');

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch HubSpot records'];
    }

    public function isHubspotObjectExist(string $objectName)
    {
        return Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->hubspotApiUrl . "crm-object-schemas/v3/schemas/{$objectName}");
    }

    public function getHubspotProperties(string $objectName): \GuzzleHttp\Promise\PromiseInterface|Response
    {
        return Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->hubspotApiUrl . "crm/v3/properties/contacts/{$objectName}");
    }


    public function hubspotCreateJobDescriptionObject()
    {
        $jobDescriptionSchema = [
            "name" => "job_description",
            "description" => "Job Description for KCR Lead Customer",
            "labels" => [
                "singular" => "Job Description",
                "plural" => "Job Descriptions"
            ],
            "primaryDisplayProperty" => "job_description",
            "secondaryDisplayProperties" => ["job_description"],
            "searchableProperties" => ["job_description"],
            "requiredProperties" => ["job_description"],
            "associatedObjects" => ["CONTACT"],
            "properties" => [
                [
                    "name" => "description",
                    "label" => "Description",
                    "type" => "string",
                    "fieldType" => "text",
                    "formField" => true,
                    "readOnly" => false,
                    "hidden" => false,
                    "calculated" => false,
                    "displayOrder" => 0,
                    "options" => []
                ]]];
        return Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->post($this->hubspotApiUrl . 'crm/v3/schemas', $jobDescriptionSchema);
    }

    public function hubspotCreateCostObject()
    {

        $costObjectSchema = [
            "name" => "estimate_cost",
            "description" => "Estimate Cost for Lead Contact for KCR Customer",
            "labels" => [
                "singular" => "Estimate Cost",
                "plural" => "Estimate Costs"
            ],
            "primaryDisplayProperty" => "cost",
            "secondaryDisplayProperties" => ["cost"],
            "searchableProperties" => ["cost"],
            "requiredProperties" => ["cost"],
            "associatedObjects" => ["CONTACT"],
            "properties" => [
                [
                    "name" => "cost",
                    "label" => "Cost",
                    "type" => "number",
                    "fieldType" => "number",
                    "formField" => true,
                    "readOnly" => false,
                    "hidden" => false,
                    "calculated" => false,
                    "displayOrder" => 0,
                    "options" => []
                ],
                [
                    "name" => "currency",
                    "label" => "Currency",
                    "type" => "enumeration",
                    "fieldType" => "enumeration",
                    "formField" => true,
                    "readOnly" => false,
                    "hidden" => false,
                    "calculated" => false,
                    "displayOrder" => 1,
                    "options" => [
                        [
                            "label" => "USD",
                            "value" => "USD"
                        ],
                        [
                            "label" => "EUR",
                            "value" => "EUR"
                        ],
                        [
                            "label" => "GBP",
                            "value" => "GBP"
                        ],
                        [
                            "label" => "JPY",
                            "value" => "JPY"
                        ],
                        [
                            "label" => "CAD",
                            "value" => "CAD"
                        ],
                        [
                            "label" => "AUD",
                            "value" => "AUD"
                        ],
                        [
                            "label" => "CHF",
                            "value" => "CHF"
                        ],
                        [
                            "label" => "CNY",
                            "value" => "CNY"
                        ],
                        [
                            "label" => "SEK",
                            "value" => "SEK"
                        ],
                        [
                            "label" => "NZD",
                            "value" => "NZD"
                        ],
                        [
                            "label" => "KRW",
                            "value" => "KRW"
                        ],
                        [
                            "label" => "SGD",
                            "value" => "SGD"
                        ],
                        [
                            "label" => "NOK",
                            "value" => "NOK"
                        ],
                        [
                            "label" => "MXN",
                            "value" => "MXN"
                        ],
                        [
                            "label" => "INR",
                            "value" => "INR"
                        ],
                        [
                            "label" => "RUB",
                            "value" => "RUB"
                        ],
                        [
                            "label" => "ZAR",
                            "value" => "ZAR"
                        ],
                        [
                            "label" => "TRY",
                            "value" => "TRY"
                        ],
                        [
                            "label" => "BRL",
                            "value" => "BRL"
                        ],
                        [
                            "label" => "TWD",
                            "value" => "TWD"
                        ]
                    ]
                ]]];
        return Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->post($this->hubspotApiUrl . 'crm-object-schemas/v3/schemas/', $costObjectSchema);
    }

    public function getHubspotContactByPhone(string $phoneNumber)
    {


        // Bypass SSL
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->hubspotApiUrl . 'crm/v3/objects/contacts/v1/search/query?q={$phoneNumber}');

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch HubSpot records'];
    }

    /**
     * @throws ConnectionException
     */
    public function getHubspotContactByEmail(string $email)
    {


        // Bypass SSL
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->hubspotApiUrl . 'crm/v3/objects/contacts/v1/contact/email/{$email}/profile');

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch HubSpot records'];
    }

    public function getHubspotByIdentifier(string $key)
    {


        // Bypass SSL
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->hubspotApiUrl . 'crm/v3/objects/contacts/v1/contact/email/{$email}/profile');

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch HubSpot records'];
    }

    // Create Hubspot Contacts
    public function createHubSpotCustomer(array $contactProperties): \GuzzleHttp\Promise\PromiseInterface|Response
    {

        // without SSL
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->hubspotApiKey,
            'Content-Type' => 'application/json',
        ])->post($this->hubspotApiUrl . 'crm/v3/objects/contacts', $contactProperties);

        if ($response->successful()) {
            return $response;
        }
        return $response;
    }


    /**
     * Fetch all existing records from Housecall Pro API.
     *
     * @return array
     * @throws ConnectionException
     */
    public function getHouseProCallCustomers($page)
    {


        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->housecallProApiUrl . '/customers',[
            'page' => $page,
            'page_size' => $this->pageSize
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch House call Pro records'];
    }

    public function getHouseCallJobs($page)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->housecallProApiUrl . '/jobs',[
            'page' => $page,
            'page_size' => $this->pageSize
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch House call Pro records'];
    }

    public function getHouseCallEstimates($page)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->housecallProApiUrl . '/estimates',
            [
                'page' => $page,
                'page_size' => $this->pageSize
            ]
        );

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch House call Pro records'];
    }

    public function getEstimatesByCustomerId($customerId)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->housecallProApiUrl . '/estimates',
            [
                'customer_id' => $customerId
            ]
        );

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch House call Pro records'];
    }

    public function getJobsByCustomerId($customerId)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->housecallProApiUrl . '/jobs',
            [
                'customer_id' => $customerId
            ]
        );

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch House call Pro records'];
    }

    public function getHouseCallEstimate(string $estimateId)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->housecallProApiUrl . '/estimates/{$estimateId}');

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch House call Pro records'];
    }

    public function getCompanyInformation(){
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->housecallProApiUrl . '/company');

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch House call Pro records'];
    }

    public function getHouseCallJob(string $jobId)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . $this->housecallProApiKey,
            'Content-Type' => 'application/json',
        ])->get($this->housecallProApiUrl . '/jobs/{$jobId}');

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch House call Pro records'];
    }


}



