<?php

namespace App\Http\Controllers;

use App\Models\JobsOffsetTracker;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use Egulias\EmailValidator\Validation\RFCValidation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use App\Http\Middleware\HubspotHousecallMiddleware;

enum JobType: string
{
    case CUSTOMER = 'customer';
    case ESTIMATE = 'estimate';
    case JOB = 'job';
}


class HouseCallProController extends Controller
{

    private $companyInformation;
    private $hubspotHousecallMiddleware;

    public function __construct()
    {
        $this->hubspotHousecallMiddleware = new HubspotHousecallMiddleware();
        $this->companyInformation = $this->hubspotHousecallMiddleware->getCompanyInformation();
    }

    private function getRegionMapping(string $region)
    {
        $mapping = [
            'Kaminskiy Care & Repair San Diego' => 'San Diego',
        ];
        return $mapping[$region] ?? $region;
    }

    public function webhook(Request $request): \Illuminate\Http\JsonResponse
    {

//        try {
//            $timestamp = $request->header('Api-Timestamp');
//            $signatureBody = $timestamp . "." . $request->getContent();
//            //Compute an HMAC with the SHA256 hash function.
//            $signature = hash_hmac('sha256', $signatureBody, env('HOUSECALL_PRO_SECRET'), false);
//            //Compare Signatures: Verify that your calculated HMAC matches the value provided in the Api-Signature header.
//            $signatureHeader = $request->header('Api-Signature');
//            if ($signature !== $signatureHeader) {
//                return response()->json(['message' => 'Invalid signature'], 401);
//            }
//        } catch (\Throwable $e) {
//            $this->dumpToFile($e->getTraceAsString(), 'signature.log');
//            return response()->json(['message' => 'Invalid signature'], 401);
//        }

        // Get the request body
        // Get request in JSON
        $responseData = $request->json()->all();
        $this->dumpToFile($responseData, 'webhook.log');

        if ($request->has('foo')) {
            $this->dumpToFile($responseData, 'webhook.log');
            return response()->json(['message' => 'HCP API Webhook Test Successful'], 200);
        }
        switch ($responseData['event']) {
            case "customer.created":
            case "customer.updated":
                // Update the customer information

                $customer = $responseData['customer'];
                $this->webHookSyncCustomer($customer, $responseData['event']);

                break;

            case 'estimate.created':
            case 'estimate.updated':
            case 'estimate.option.created':
            case 'estimate.sent':
                $this->webHookSyncEstimate($responseData['estimate'], $responseData['event']);
                break;

            case 'job.created':
            case 'job.updated':
            case 'job.started':
                $this->webHookSyncJob($responseData['job'], $responseData['event']);
                break;


        }
        // The Signature is valid, proceed with your logic
        return response()->json(['message' => 'Webhook is working', 'data' => $responseData], 200);
    }

    private function getTag(string $tag)
    {
        $tags = [
            "Kaminskiy Care & Repair San Diego" => "KCR - San Diego",
        ];
        return $tags[$tag];
    }


    public function webHookSyncJob($job, $event)
    {

        $this->dumpToFile($job, 'jobs.log');
        // Get the current customer from the job
        $customer = $job['customer'];
        // fetch the details of if the user already exists
        $filterResult = $this->hubspotHousecallMiddleware->searchHubspotCustomers($customer['email'], $customer['mobile_number'], $customer['id']);
        $response_estimates = $this->hubspotHousecallMiddleware->getEstimatesByCustomerId($customer['id']);
        $job_estimates = $this->hubspotHousecallMiddleware->getJobsByCustomerId($customer['id']);
        $jobs = $job_estimates['jobs'];
        $jobCount = count($jobs);
        // Create the properties
        $contactProperties = [
            "properties" => []
        ];
        $region = $this->getRegionMapping($this->companyInformation['name']);
        $contactProperties['properties']['estimate_cost'] = $this->getTotalCost($response_estimates['estimates']);
        $contactProperties['properties']['total_estimate_cost'] = count($response_estimates['estimates']);
        $contactProperties['properties']['area'] = $region;
        $contactProperties['properties']['firstname'] = $customer['first_name'];
        $contactProperties['properties']['lastname'] = $customer['last_name'];

        if ($filterResult->total > 0) {
            // We need to get the estimates
            $result = $filterResult->results[0];
            $contactProperties['properties']['housepro_id'] = $customer['id'];
            $contactProperties['properties']['total_jobs'] = $this->handleJobAmount($jobs);
            $contactProperties['properties']['total_job_count'] = $jobCount;
            if (isset($customer['email'])) {
                $email = $customer['email'];
                $contactProperties['properties']['email'] = $this->isValidEmail($email) ? $email : null;
            }
            if (isset($customer['mobile_number'])) {
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
            }
            $this->hubspotHousecallMiddleware->updateHubspotContact($result->id, $contactProperties);

        } else {
            // Create the Data in Hubspot

            $contactProperties['properties']['firstname'] = $customer['first_name'];
            $contactProperties['properties']['lastname'] = $customer['last_name'];
            $contactProperties['properties']['phone'] = $customer['mobile_number'];
            $contactProperties['properties']['housepro_id'] = $customer['id'];
            $contactProperties['properties']['total_job_count'] = $jobCount;
            $contactProperties['properties']['total_jobs'] = $this->handleJobAmount($jobs);
            if (isset($customer['email'])) {
                $email = $customer['email'];
                $contactProperties['properties']['email'] = $this->isValidEmail($email) ? $email : null;
            }
            if (isset($customer['mobile_number'])) {
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
            }
            $this->hubspotHousecallMiddleware->createHubSpotCustomer($contactProperties);
        }

        return response()->json(['message' => 'Webhook Job Synced Successfully', "data" => $job]);
    }

    public function webHookSyncEstimate($estimate, $event)
    {
        $this->dumpToFile($estimate, 'estimates.log'); // Dump the estimates
        // Get the customer from the payload
        $customer = $estimate['customer'];
        // Get the existing customer record from Hubspot
        $filterResult = $this->hubspotHousecallMiddleware->searchHubspotCustomers($customer['email'], $customer['mobile_number'], $customer['id']);
        $response_estimates = $this->hubspotHousecallMiddleware->getEstimatesByCustomerId($customer['id']);
        // Create the properties
        $contactProperties = [
            "properties" => []
        ];
        $region = $this->getRegionMapping($this->companyInformation['name']);
        $contactProperties['properties']['estimate_cost'] = $this->getTotalCost($response_estimates['estimates']);
        $contactProperties['properties']['total_estimate_cost'] = count($response_estimates['estimates']);
        $contactProperties['properties']['area'] = $region;
        $contactProperties['properties']['housepro_id'] = $customer['id'];

        if ($filterResult->total > 0) {
            $result = $filterResult->results[0];
            $contactProperties['properties']['firstname'] = $customer['first_name'];
            $contactProperties['properties']['lastname'] = $customer['last_name'];
            $contactProperties['properties']['phone'] = $customer['mobile_number'];
            if (isset($customer['email'])) {
                $contactProperties['properties']['email'] = $this->isValidEmail($customer['email']) ? $customer['email'] : null;
            }

            $contactProperties['properties']['housepro_id'] = $customer['id'];

            if (isset($customer['mobile_number'])) {
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
            }
            // Update the customer
            $this->hubspotHousecallMiddleware->updateHubspotContact($result->id, $contactProperties);
        } else {
            $contactProperties['properties']['firstname'] = $customer['first_name'];
            $contactProperties['properties']['lastname'] = $customer['last_name'];
            $contactProperties['properties']['phone'] = $customer['mobile_number'];
            if (isset($customer['email'])) {
                $contactProperties['properties']['email'] = $this->isValidEmail($customer['email']) ? $customer['email'] : null;
            }
            if (isset($customer['mobile_number'])) {
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
            }
            // Create the customer
            $this->hubspotHousecallMiddleware->createHubSpotCustomer($contactProperties);
        }

        return response()->json(['message' => 'Webhook Estimate Synced Successfully', "data" => $estimate]);
    }

    private function dumpToFile($data, $fileName)
    {
        $filePath = storage_path('logs/' . $fileName);
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT), FILE_APPEND);
    }

    public function syncJobs()
    {
        $customerJob = JobsOffsetTracker::where('type', 'job')->first();
        // IF already finished
        if ($customerJob !== null) {
            if ($customerJob->total_pages == $customerJob->page_offset) {
                return response()->json(['message' => 'Jobs finished processing...']);
            }
        }

        if ($customerJob === null) {
            $pageCount = 0;
            $customerJob = new JobsOffsetTracker();
        } else {
            $pageCount = intval($customerJob->page_offset);
        }
        $this->hubspotHousecallMiddleware = new HubspotHousecallMiddleware();
        $batchUpset = [
            "input" => []
        ];
        $houseCallCustomers = $this->hubspotHousecallMiddleware->getHouseProCallCustomers($pageCount);
        // Update the result
        $customerJob->total_items = $houseCallCustomers['total_items'];
        $customerJob->total_pages = $houseCallCustomers['total_pages'];
        $customerJob->page_size = env('HUBSPOT_LIMIT_PAGE_SIZE');
        $customerJob->page_offset = $pageCount;
        $customerJob->type = JobType::JOB;
        $customerJob->last_synced_at = now();
        $customerJob->completed = $houseCallCustomers['total_pages'] == $pageCount;
        $customerJob->save();
        $customers = $houseCallCustomers['customers'];
        foreach ($customers as $customer) {
            // Check if customer has email or phone
            $idProperty = null;
            $id = null;
            $contactProperties = [
                "properties" => []
            ];
            //Set the region
            $region = $this->getRegionMapping($this->companyInformation['name']);
            // convert to date
            $createdData = date('Y-m-d H:i:s', strtotime($customer['created_at']));
            // If not created in 2024 continue
            if ($createdData < '2024-01-01 00:00:00') {
                continue;
            }
            //Validate the email
            $emailValidator = new EmailValidator();
            $email = $customer['email'];
            if (isset($customer['email']) && $this->isValidEmail($email)) {
                $idProperty = 'email';
                $id = $customer['email'];
            } else if (isset($customer['mobile_number'])) {
                $idProperty = 'phone';
                $id = $customer['mobile_number'];
            }

            // Checking if they exists in Hubspot
            $filterResult = $this->hubspotHousecallMiddleware->searchHubspotCustomers($customer['email'], $customer['mobile_number'], $customer['id']);

            $response_estimates = $this->hubspotHousecallMiddleware->getJobsByCustomerId($customer['id']);
            $jobs = $response_estimates['jobs'];
            $jobCount = count($jobs);
            if ($filterResult->total > 0) {
                // We need to get the estimates
                $result = $filterResult->results[0];

                $contactProperties['properties']['firstname'] = $customer['first_name'];
                $contactProperties['properties']['lastname'] = $customer['last_name'];
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
                $contactProperties['properties']['area'] = $region;
                if ($result->properties && $result->properties->housepro_id === null) {
                    $contactProperties['properties']['housepro_id'] = $customer['id'];
                }

                $contactProperties['properties']['total_jobs'] = $this->handleJobAmount($jobs);
                $contactProperties['properties']['total_job_count'] = $jobCount;
                if (isset($customer['email'])) {
                    $contactProperties['properties']['email'] = $this->isValidEmail($email) ? $email : null;
                }
                if (isset($customer['mobile_number'])) {
                    $contactProperties['properties']['phone'] = $customer['mobile_number'];
                }
                $this->hubspotHousecallMiddleware->updateHubspotContact($result->id, $contactProperties);

            } else {
                // Create the Data in Hubspot

                $contactProperties['properties']['firstname'] = $customer['first_name'];
                $contactProperties['properties']['lastname'] = $customer['last_name'];
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
                $contactProperties['properties']['housepro_id'] = $customer['id'];
                $contactProperties['properties']['total_job_count'] = $jobCount;
                $contactProperties['properties']['total_jobs'] = $this->handleJobAmount($jobs);
                if (isset($customer['email'])) {
                    $contactProperties['properties']['email'] = $this->isValidEmail($email) ? $email : null;
                }
                if (isset($customer['mobile_number'])) {
                    $contactProperties['properties']['phone'] = $customer['mobile_number'];
                }
                $this->hubspotHousecallMiddleware->createHubSpotCustomer($contactProperties);
            }
        }
        //Update the page count
        $customerJob->update(['page_offset' => $pageCount + 1]);
        $customerJob->save();
        return response()->json(['message' => 'Job Synced Successfully', "data" => $customers]);
    }

    private function handleJobAmount(array $jobs): float
    {
        $totalAmount = 0;
        foreach ($jobs as $job) {
            $totalAmount += (floatval($job['total_amount']) / 100);
        }
        return $totalAmount;
    }

    private function handleDuplicateEstimates($array): array
    {
        $newBatch = [];
        $estimateArray = [];
        foreach ($array as $index => $estimate) {
            // Check if an existing estimate exists
            if (isset($estimateArray[$estimate['id']])) {
                // Get the estimate from key array
                $estimateInArray = $estimateArray[$estimate['id']];
                $newTotal = intval($estimate['properties']['estimate_cost']);
                $previousTotal = intval($estimateArray[$estimate['id']]['properties']['estimate_cost']);
                $previousJobCount = intval($estimateArray[$estimate['id']]['properties']['total_job_count']);
                $newJobCount = intval($estimate['properties']['total_job_count']);
                // Save the new total
                foreach ($newBatch as $batch) {
                    if ($batch['id'] == $estimate['id']) {
                        $batch['properties']['estimate_cost'] = $newTotal + $previousTotal;
                        $batch['properties']['total_job_count'] = $newJobCount + $previousJobCount;

                    }
                }


            } else {
                $estimateArray[$estimate['id']] = $estimate;
                $newBatch[] = $estimate;
            }
        }
        return $newBatch;
    }

    private function getTotalCost(array $options)
    {
        $totalCost = 0;

        foreach ($options as $optionVal) {
            $optionSet = $optionVal['options'];
            foreach ($optionSet as $option) {
                $totalCost += (intval($option['total_amount']) / 100);
            }
        }

        return $totalCost;
    }


    private function isValidEmail($email)
    {
        // Basic email validation using filter_var
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Extract domain from email
        $domain = substr(strrchr($email, "@"), 1);

        // List of valid top-level domains (TLDs)
        $validTLDs = ["com", "net", "org", "edu", "gov", "mil", "io", "us", "uk"]; // Add more as needed

        // Extract TLD from domain
        $tld = substr(strrchr($domain, "."), 1);

        // Check if TLD is in the allowed list
        return in_array($tld, $validTLDs);
    }

    /**
     * @throws ConnectionException
     */
    public function syncCustomers()
    {
        $customerJob = JobsOffsetTracker::where('type', 'customer')->first();
        // IF already finished
        if ($customerJob !== null) {
            if ($customerJob->total_pages == $customerJob->page_offset) {
                return response()->json(['message' => 'Jobs finished processing...']);
            }
        }

        if ($customerJob === null) {
            $pageCount = 0;
            $customerJob = new JobsOffsetTracker();
        } else {
            $pageCount = intval($customerJob->page_offset);
        }
        $this->hubspotHousecallMiddleware = new HubspotHousecallMiddleware();
        $batchUpset = [
            "input" => []
        ];
        $houseCallCustomers = $this->hubspotHousecallMiddleware->getHouseProCallCustomers($pageCount);
        // Update the result
        $customerJob->total_items = $houseCallCustomers['total_items'];
        $customerJob->total_pages = $houseCallCustomers['total_pages'];
        $customerJob->page_size = env('HUBSPOT_LIMIT_PAGE_SIZE');
        $customerJob->page_offset = $pageCount;
        $customerJob->type = JobType::CUSTOMER;
        $customerJob->last_synced_at = now();
        $customerJob->completed = $houseCallCustomers['total_pages'] == $pageCount;
        $customerJob->save();
        $customers = $houseCallCustomers['customers'];
        foreach ($customers as $customer) {
            // Check if customer has email or phone
            $idProperty = null;
            $id = null;

            // Set the Region for this call
            $region = $this->getRegionMapping($this->companyInformation['name']);
            $contactProperties = [
                "properties" => []
            ];
            // convert to date
            $createdData = date('Y-m-d H:i:s', strtotime($customer['created_at']));
            // If not created in 2024 continue
            if ($createdData < '2024-01-01 00:00:00') {
                continue;
            }
            //Validate the email
            $emailValidator = new EmailValidator();
            $email = $customer['email'];
            if (isset($customer['email']) && $this->isValidEmail($email)) {
                $idProperty = 'email';
                $id = $customer['email'];
            } else if (isset($customer['mobile_number'])) {
                $idProperty = 'phone';
                $id = $customer['mobile_number'];
            }

            // Checking if they exists in Hubspot
            $filterResult = $this->hubspotHousecallMiddleware->searchHubspotCustomers($customer['email'], $customer['mobile_number'], $customer['id']);
            $response_estimates = $this->hubspotHousecallMiddleware->getEstimatesByCustomerId($customer['id']);
            $estimates = $response_estimates['estimates'];
            $jobCount = count($estimates);
            $contactProperties['properties']['area'] = $region;
            if ($filterResult->total > 0) {
                // We need to get the estimates
                $result = $filterResult->results[0];

                $contactProperties['properties']['firstname'] = $customer['first_name'];
                $contactProperties['properties']['lastname'] = $customer['last_name'];
                $contactProperties['properties']['phone'] = $customer['mobile_number'];

                if ($result->properties && $result->properties->housepro_id === null) {
                    $contactProperties['properties']['housepro_id'] = $customer['id'];
                }
                $contactProperties['properties']['estimate_cost'] = $this->getTotalCost($estimates);
                $contactProperties['properties']['total_estimate_cost'] = $jobCount;

                if (isset($customer['email'])) {
                    // Check that new email is different


                    if ($result != null) {
                        if ($filterResult->results[0]->properties->email !== $customer['email']) {
                            $contactProperties['properties']['email'] = $this->isValidEmail($email) ? $email : null;
                        }
                    }


                }
                if (isset($customer['mobile_number'])) {
                    $contactProperties['properties']['phone'] = $customer['mobile_number'];
                }
                $this->hubspotHousecallMiddleware->updateHubspotContact($result->id, $contactProperties);

            } else {
                // Create the Data in Hubspot

                $contactProperties['properties']['firstname'] = $customer['first_name'];
                $contactProperties['properties']['lastname'] = $customer['last_name'];
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
                $contactProperties['properties']['housepro_id'] = $customer['id'];
                $contactProperties['properties']['estimate_cost'] = $this->getTotalCost($estimates);
                $contactProperties['properties']['total_estimate_cost'] = $jobCount;


                if (isset($customer['mobile_number'])) {
                    $contactProperties['properties']['phone'] = $customer['mobile_number'];
                }
                $this->hubspotHousecallMiddleware->createHubSpotCustomer($contactProperties);
            }
        }
        //Update the page count
        $customerJob->update(['page_offset' => $pageCount + 1]);
        return response()->json(['message' => 'Customers Synced Successfully', "data" => $customers]);
    }

    private function handleDuplicates($records){
        /*
         * To handle duplicates we need to handle the following:
         * 1. Check which is the Original Source and the Offline sources
         * 2. Compare the records to see which has the Housecall pro id
         * 3. Mark the property that has the offline sources property as the duplicate
         * */
    }

    public function webHookSyncCustomer($customer, $event)
    {

        $id = null;

        // Set the Region for this call
        $region = $this->getRegionMapping($this->companyInformation['name']);
        $contactProperties = [
            "properties" => []
        ];
        $email = $customer['email'];
        if (isset($customer['email']) && $this->isValidEmail($email)) {
            $idProperty = 'email';
            $id = $customer['email'];
        } else if (isset($customer['mobile_number'])) {
            $idProperty = 'phone';
            $id = $customer['mobile_number'];
        }

        // Checking if they exists in Hubspot
        $filterResult = $this->hubspotHousecallMiddleware->searchHubspotCustomers($customer['email'], $customer['mobile_number'], $customer['id']);
        $response_estimates = $this->hubspotHousecallMiddleware->getEstimatesByCustomerId($customer['id']);

        // Dump the estimate
        $this->dumpToFile($response_estimates, "{$customer['first_name']}_estimates.log");

        $estimates = $response_estimates['estimates'];
        $jobCount = count($estimates);
        $contactProperties['properties']['area'] = $region;

        if ($event == 'customer.created') {
            $customer['properties']['housepro_id'] = $customer['id'];
            $contactProperties['properties']['firstname'] = $customer['first_name'];
            $contactProperties['properties']['lastname'] = $customer['last_name'];
            $contactProperties['properties']['phone'] = $customer['mobile_number'];
            $contactProperties['properties']['housepro_id'] = $customer['id'];
            $contactProperties['properties']['total_job_count'] = $jobCount;
            $contactProperties['properties']['estimate_cost'] = $this->getTotalCost($estimates);
            // Check that Hubspot email is different or has an email at least
            if (isset($customer['email'])) {
                $hcpEmail = $customer['email'];
                $hubspotEmail = $filterResult->results[0]->properties->email ?? null;
                if (isset($hubspotEmail) && $hubspotEmail !== $hcpEmail) {
                    $contactProperties['properties']['email'] = $this->isValidEmail($email) ? $email : null;
                }
                // If there is no email in Hubspot, then we can set it
                if (!isset($hubspotEmail)) {
                    $contactProperties['properties']['email'] = $this->isValidEmail($email) ? $email : null;
                }

            }
            if (isset($customer['mobile_number'])) {
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
            }
            // It's possible to be in Hubspot even though it's created for the first time on HCP
            if ($filterResult->total > 0) {
                $result = $filterResult->results[0];
                $this->hubspotHousecallMiddleware->updateHubspotContact($result->id, $contactProperties);
            } else {
                $this->hubspotHousecallMiddleware->createHubSpotCustomer($contactProperties);
            }

        }

        if ($event == 'customer.updated') {


            $customer['properties']['housepro_id'] = $customer['id'];
            $contactProperties['properties']['firstname'] = $customer['first_name'];
            $contactProperties['properties']['lastname'] = $customer['last_name'];
            $contactProperties['properties']['phone'] = $customer['mobile_number'];
            $contactProperties['properties']['housepro_id'] = $customer['id'];
            $contactProperties['properties']['total_job_count'] = $jobCount;
            $contactProperties['properties']['estimate_cost'] = $this->getTotalCost($estimates);
            if (isset($customer['email'])) {
                // Check that new email is different
                if ($filterResult->total > 0) {
                    $result = $filterResult->results[0];
                    if ($result != null) {
                        if ($filterResult->results[0]->properties->email !== $customer['email']) {
                            $contactProperties['properties']['email'] = $this->isValidEmail($email) ? $email : null;
                        }
                    }

                }


            }
            if (isset($customer['mobile_number'])) {
                $contactProperties['properties']['phone'] = $customer['mobile_number'];
            }
            // It's possible to be on HCP but not on Hubspot CRM for some reason, then we should create or update
            // depending on the total
            if ($filterResult->total > 0) {
                $result = $filterResult->results[0];
                $this->hubspotHousecallMiddleware->updateHubspotContact($result->id, $contactProperties);
            } else {
                $this->hubspotHousecallMiddleware->createHubSpotCustomer($contactProperties);
            }

        }

        $this->dumpToFile($customer, 'customer.log');
        return response()->json(['message' => 'Webhook Customer Synced Successfully', "data" => $customer]);
    }
}








