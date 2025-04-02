<?php

namespace App\Http\Controllers;

use App\Jobs\PaymentCollectionProcessor;
use App\Jobs\PaymentProcessor;
use App\Models\Currency;
use App\Models\PaymentRequest;
use App\Models\UserWallet;
use App\Models\WithdrawalStage;
use App\Services\DeveloperAuthService;
use App\Services\SendMoneyChargeService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
class SendMoneyController extends Controller
{
    protected $walletService;

    protected SendMoneyChargeService $chargeService;

    public function __construct(WalletService $walletService,SendMoneyChargeService $chargeService)
    {
        $this->walletService = $walletService;
        $this->chargeService = $chargeService;
    }
    public function storeSendToMobileMoneyRequest(Request $request)
    {
        $mobileMoneyChannels = ["00", "63903", "63902", "97","Propel"];
        $validator = Validator::make($request->all(), [
            "MerchantCode" => "required|string",
            "Amount" => "required|numeric",
            "Currency" => "required|string",
            "ReceiverNumber" => "required|string",
            "Channel" => ["required", "string", Rule::in($mobileMoneyChannels)],
            "Reason" => "required|string",
            "InitiatorTransactionReference" => "required|string",
            "CallBackUrl" => "required|url",
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => "input error",
                "detail" => $validator->errors(),
                'ResponseCode'=>'400'
            ], 422);
        }
        $data = $request->get('data'); // Retrieved from middleware
        $accountNumber = $request->input('MerchantCode');
        $walletId=DeveloperAuthService::validateAccountNumber($data, $accountNumber);
        if (!$walletId) {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid Account Number',
                "message" => "Invalid Account Number",
                'ResponseCode'=>'403'
            ], 403);
        }
        // Check if currency is active
        $currencyCode = $request->input('Currency');
        $currency = Currency::where('code', $currencyCode)->where('status', 1)->first();

        if (!$currency) {
            return response()->json([
                "status" => false,
                "message" => "Invalid or inactive currency",
                "detail" => "The specified currency is not available or inactive",
                'ResponseCode'=>'400'
            ], 400);
        }

        Log::info("Currency is valid: " . $currency->code);

        Log::info("wallet id is " . $walletId);
        $walletInfo=UserWallet::find($walletId);
        $charge = $this->chargeService->getCharge($request->Channel,$request->Amount,false);
        $channel_name = $this->walletService->getChannelName($request->Channel);
        if (!$channel_name) {
            return response()->json([
                "status" => false,
                "message" => "Invalid channel code",
                "detail" => "The provided channel code is not recognized.",
                'ResponseCode'=>'400'
            ], 400);
        }
        // Check if Channel is 'Propel'
        if ($request->Channel == 'Propel') {
            // Ensure ReceiverNumber is not the same as AccountNumber
            if ($request->ReceiverNumber == $accountNumber) {
                return response()->json([
                    "status" => false,
                    "message" => "ReceiverNumber and SenderNumber cannot be the same",
                    "detail" => "You cannot transfer to your own account.",
                    "ResponseCode" => "400"
                ], 400);
            }

            // Check if a wallet with the given ReceiverNumber exists
            $walletExists = UserWallet::where('wallet_account', $request->ReceiverNumber)->exists();
            if (!$walletExists) {
                return response()->json([
                    "status" => false,
                    "message" => "Wallet not found",
                    "detail" => "No wallet found with the provided ReceiverNumber.",
                    "ResponseCode" => "404"
                ], 404);
            }
        }
        try {
           $amount_to_dect=$charge+$request->Amount;
            $this->walletService->checkBalanceOrFail($request->MerchantCode, $request->Amount);
            //WalletService::checkBalanceOrFail($request->walletNumber, $request->amount);
        } catch (ValidationException $e) {
            return response()->json([
                "status" => false,
                "detail" => $e->getMessage(),
                "errors" => $e->errors(),
                'ResponseCode'=>'400'
            ], 400);
        }

        $merchantCode=$accountNumber;
        $transactionReference=$request->InitiatorTransactionReference;
        $transactionRefController = "$merchantCode-$transactionReference";
        $existingRequest = PaymentRequest::where('transaction_ref_controller', $transactionRefController)->first();
        if ($existingRequest) {
            return response()->json([
                "status" => false,
                'message' => 'Duplicate InitiatorTransactionReference  detected.',
                'details' => 'use unique InitiatorTransactionReference.',
                'ResponseCode'=>'409'
            ], 409);
        }
        try {
            DB::beginTransaction();
            $checkoutId = Str::uuid();
            $checkoutReference = "$merchantCode-$checkoutId";
            $transactionReference=$request->InitiatorTransactionReference;
            $transactionRefController = "$merchantCode-$transactionReference";
            $receiverNumber=$request->ReceiverNumber;
            // Create Payment Request
            $paymentGateway='Propel';
            $sourceChannel = 'Propel';
            $destinationChannel=$channel_name;
            $destination_channel_code=$request->Channel;
            $remoteUserIp = $request->ip();
            $remoteUserAgent = $request->header('User-Agent');
            $paymentRequest = PaymentRequest::create([
                'payment_gateway' => $paymentGateway,
                'merchant_code' => $accountNumber,
                'source_channel' => $sourceChannel,
                'destination_channel' => $destinationChannel,
                'destination_channel_code' => $destination_channel_code,
                'account_number' => $receiverNumber,
                'destination_account_number' => $receiverNumber,
                'currency' => 'KES',
                'amount' => $request->Amount,
                'transaction_ref' => $transactionReference,
                'transaction_ref_controller' => $transactionRefController,
                'transaction_type' => 'B2C',
                'transaction_description' => $request->Reason,
                'checkout_id' => $checkoutId,
                'checkout_reference' => $checkoutReference,
                'callback_url' => $request->CallBackUrl,
                'remote_user_ip_address' => $remoteUserIp,
                'remote_user_agent' => $remoteUserAgent,
                'source_account_number' => $accountNumber,
                'source_account_name' => $walletInfo->wallet_name,
                'total_amount'=>$amount_to_dect,
                'remittance_purpose'=>$request->Reason,
                'conversion_rate'=>1,
                'propel_transaction_charge'=>$charge

            ]);
            DB::commit(); // Commit transaction after successful insert
            Log::info("Dispatching PaymentProcessor for PaymentRequest ID: " . $paymentRequest->id);
            PaymentProcessor::dispatch($paymentRequest);
            return response()->json([
                "status" => true,
                "detail" => "Transaction is being processed",
                "TrackingID" => $checkoutReference,
//                "ConversationID" => $checkoutId,
                "InitiatorTransactionReference" => $transactionReference,
                "ResponseCode" => "0",
                "ResponseDescription" => "Transaction is being processed"
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return response()->json([
                "status" => false,
                "ResponseCode" => "500",
                "detail" => "Transaction could not be processed."
            ], 400);
        }

    }
    public function storeSendToBankRequest(Request $request)
    {
        $bankChannels = [
            "01", "02", "03", "07", "10", "11", "12", "14", "16", "18", "19", "23", "25",
            "31", "35", "36", "43", "50", "51", "53", "54", "55", "57", "61", "63", "65",
            "66", "68", "70", "72", "74", "75", "76", "78", "89"
        ];

        $validator = Validator::make($request->all(), [
            "MerchantCode" => "required|string",
            "Amount" => "required|numeric",
            "Currency" => "required|string",
            "ReceiverNumber" => "required|string",
            "Channel" => ["required", "string", Rule::in($bankChannels)],
            "Reason" => "required|string",
            "InitiatorTransactionReference" => "required|string",
            "CallBackUrl" => "required|url",
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => "input error",
                "detail" => $validator->errors(),
                'ResponseCode'=>'400'
            ], 422);
        }
        $data = $request->get('data'); // Retrieved from middleware
        $accountNumber = $request->input('MerchantCode');
        $walletId=DeveloperAuthService::validateAccountNumber($data, $accountNumber);
        if (!$walletId) {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid Account Number',
                "message" => "Invalid Account Number",
                'ResponseCode'=>'403'
            ], 403);
        }
        // Check if currency is active
        $currencyCode = $request->input('Currency');
        $currency = Currency::where('code', $currencyCode)->where('status', 1)->first();

        if (!$currency) {
            return response()->json([
                "status" => false,
                "message" => "Invalid or inactive currency",
                "detail" => "The specified currency is not available or inactive",
                'ResponseCode'=>'400'
            ], 400);
        }
        Log::info("Currency is valid: " . $currency->code);
        Log::info("wallet id is " . $walletId);
        $walletInfo=UserWallet::find($walletId);
        $charge = $this->chargeService->getCharge($request->Channel,$request->Amount,false);
        $channel_name = $this->walletService->getChannelName($request->Channel);
        if (!$channel_name) {
            return response()->json([
                "status" => false,
                "message" => "Invalid channel code",
                "detail" => "The provided channel code is not recognized.",
                'ResponseCode'=>'400'
            ], 400);
        }
        // Check if Channel is 'Propel'
        if ($request->Channel == 'Propel') {
            // Ensure ReceiverNumber is not the same as AccountNumber
            if ($request->ReceiverNumber == $accountNumber) {
                return response()->json([
                    "status" => false,
                    "message" => "ReceiverNumber and SenderNumber cannot be the same",
                    "detail" => "You cannot transfer to your own account.",
                    "ResponseCode" => "400"
                ], 400);
            }

            // Check if a wallet with the given ReceiverNumber exists
            $walletExists = UserWallet::where('wallet_account', $request->ReceiverNumber)->exists();
            if (!$walletExists) {
                return response()->json([
                    "status" => false,
                    "message" => "Wallet not found",
                    "detail" => "No wallet found with the provided ReceiverNumber.",
                    "ResponseCode" => "404"
                ], 404);
            }
        }
        try {
           $amount_to_dect=$charge+$request->Amount;
            $this->walletService->checkBalanceOrFail($request->MerchantCode, $request->Amount);
            //WalletService::checkBalanceOrFail($request->walletNumber, $request->amount);
        } catch (ValidationException $e) {
            return response()->json([
                "status" => false,
                "detail" => $e->getMessage(),
                "errors" => $e->errors(),
                'ResponseCode'=>'400'
            ], 400);
        }
        $merchantCode=$accountNumber;
        $transactionReference=$request->InitiatorTransactionReference;
        $transactionRefController = "$merchantCode-$transactionReference";
        $existingRequest = PaymentRequest::where('transaction_ref_controller', $transactionRefController)->first();
        if ($existingRequest) {
            return response()->json([
                "status" => false,
                'message' => 'Duplicate InitiatorTransactionReference  detected.',
                'details' => 'use unique InitiatorTransactionReference.',
                'ResponseCode'=>'409'
            ], 409);
        }
        try {
            DB::beginTransaction();
            $checkoutId = Str::uuid();
            $checkoutReference = "$merchantCode-$checkoutId";
            $transactionReference=$request->InitiatorTransactionReference;
            $transactionRefController = "$merchantCode-$transactionReference";
            $receiverNumber=$request->ReceiverNumber;
            // Create Payment Request
            $paymentGateway='Propel';
            $sourceChannel = 'Propel';
            $destinationChannel=$channel_name;
            $destination_channel_code=$request->Channel;
            $remoteUserIp = $request->ip();
            $remoteUserAgent = $request->header('User-Agent');
            $paymentRequest = PaymentRequest::create([
                'payment_gateway' => $paymentGateway,
                'merchant_code' => $accountNumber,
                'source_channel' => $sourceChannel,
                'destination_channel' => $destinationChannel,
                'destination_channel_code' => $destination_channel_code,
                'account_number' => $receiverNumber,
                'destination_account_number' => $receiverNumber,
                'currency' => 'KES',
                'amount' => $request->Amount,
                'transaction_ref' => $transactionReference,
                'transaction_ref_controller' => $transactionRefController,
                'transaction_type' => 'B2C',
                'transaction_description' => $request->Reason,
                'checkout_id' => $checkoutId,
                'checkout_reference' => $checkoutReference,
                'callback_url' => $request->CallBackUrl,
                'remote_user_ip_address' => $remoteUserIp,
                'remote_user_agent' => $remoteUserAgent,
                'source_account_number' => $accountNumber,
                'source_account_name' => $walletInfo->wallet_name,
                'total_amount'=>$amount_to_dect,
                'remittance_purpose'=>$request->Reason,
                'conversion_rate'=>1,
                'propel_transaction_charge'=>$charge

            ]);
            DB::commit(); // Commit transaction after successful insert
            Log::info("Dispatching PaymentProcessor for PaymentRequest ID: " . $paymentRequest->id);
            PaymentProcessor::dispatch($paymentRequest);
            return response()->json([
                "status" => true,
                "detail" => "Transaction is being processed",
                "TrackingID" => $checkoutReference,
//                "ConversationID" => $checkoutId,
                "InitiatorTransactionReference" => $transactionReference,
                "ResponseCode" => "0",
                "ResponseDescription" => "Transaction is being processed"
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return response()->json([
                "status" => false,
                "ResponseCode" => "500",
                "detail" => "Transaction could not be processed."
            ], 400);
        }
    }
    public function storePaymentRequest(Request $request)
    {
        $mobileMoneyChannels = ["00", "63903", "63902", "97","Propel","68"];
        $validator = Validator::make($request->all(), [
            "MerchantCode" => "required|string",
            "BusinessNumber" => "required|string",
            "AccountNumber" => "Nullable|string",
            "BusinessType"=>"required|string|in:PAYBILL,TILL",
            "Amount" => "required|numeric",
            "Currency" => "required|string",
            "Channel" => ["required", "string", Rule::in($mobileMoneyChannels)],
            "Reason" => "required|string",
            "InitiatorTransactionReference" => "required|string",
            "CallBackUrl" => "required|url",
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => "input error",
                "detail" => $validator->errors(),
                'ResponseCode'=>'400'
            ], 422);
        }
        $data = $request->get('data'); // Retrieved from middleware
        $accountNumber = $request->input('MerchantCode');
        $walletId=DeveloperAuthService::validateAccountNumber($data, $accountNumber);
        if (!$walletId) {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid Account Number',
                "message" => "Invalid Account Number",
                'ResponseCode'=>'403'
            ], 403);
        }
        // Check if currency is active
        $currencyCode = $request->input('Currency');
        $currency = Currency::where('code', $currencyCode)->where('status', 1)->first();
        if (!$currency) {
            return response()->json([
                "status" => false,
                "message" => "Invalid or inactive currency",
                "detail" => "The specified currency is not available or inactive",
                'ResponseCode'=>'400'
            ], 400);
        }
        Log::info("Currency is valid: " . $currency->code);
        Log::info("wallet id is " . $walletId);
        $walletInfo=UserWallet::find($walletId);
        $charge = $this->chargeService->getCharge($request->Channel,$request->Amount,true);
        $channel_name = $this->walletService->getChannelName($request->Channel);
        if (!$channel_name) {
            return response()->json([
                "status" => false,
                "message" => "Invalid channel code",
                "detail" => "The provided channel code is not recognized.",
                'ResponseCode'=>'400'
            ], 400);
        }
        if ($request->Channel == 'Propel') {
            // Ensure ReceiverNumber is not the same as AccountNumber
            if ($request->BusinessNumber == $accountNumber) {
                return response()->json([
                    "status" => false,
                    "message" => "ReceiverNumber and SenderNumber cannot be the same",
                    "detail" => "You cannot transfer to your own account.",
                    "ResponseCode" => "400"
                ], 400);
            }
            // Check if a wallet with the given ReceiverNumber exists
            $walletExists = UserWallet::where('wallet_account', $request->BusinessNumber)->exists();
            if (!$walletExists) {
                return response()->json([
                    "status" => false,
                    "message" => "Wallet not found".$request->BusinessNumber,
                    "detail" => "No wallet found with the provided ReceiverNumber.",
                    "ResponseCode" => "404"
                ], 404);
            }
        }
        try {
            $amount_to_dect=$charge+$request->Amount;
            $this->walletService->checkBalanceOrFail($request->MerchantCode, $request->Amount);
            //WalletService::checkBalanceOrFail($request->walletNumber, $request->amount);
        } catch (ValidationException $e) {
            return response()->json([
                "status" => false,
                "detail" => $e->getMessage(),
                "errors" => $e->errors(),
                'ResponseCode'=>'400'
            ], 400);
        }
        $merchantCode=$accountNumber;
        $transactionReference=$request->InitiatorTransactionReference;
        $transactionRefController = "$merchantCode-$transactionReference";
        $existingRequest = PaymentRequest::where('transaction_ref_controller', $transactionRefController)->first();
        if ($existingRequest) {
            return response()->json([
                "status" => false,
                'message' => 'Duplicate InitiatorTransactionReference  detected.',
                'details' => 'use unique InitiatorTransactionReference.',
                'ResponseCode'=>'409'
            ], 409);
        }
        try {
            DB::beginTransaction();
            $checkoutId = Str::uuid();
            $checkoutReference = "$merchantCode-$checkoutId";
            $transactionReference=$request->InitiatorTransactionReference;
            $transactionRefController = "$merchantCode-$transactionReference";
            $receiverNumber=$request->BusinessNumber;
            // Create Payment Request
            $paymentGateway='Propel';
            $sourceChannel = 'Propel';
            $destinationChannel=$channel_name;
            $destination_channel_code=$request->Channel;
            $remoteUserIp = $request->ip();
            $remoteUserAgent = $request->header('User-Agent');
            $transaction_type='B2B-'.$request->BusinessType;
            $paymentRequest = PaymentRequest::create([
                'payment_gateway' => $paymentGateway,
                'merchant_code' => $accountNumber,
                'source_channel' => $sourceChannel,
                'destination_channel' => $destinationChannel,
                'destination_channel_code' => $destination_channel_code,
                'account_number' => $receiverNumber,
                'destination_account_number' => $receiverNumber,
                'receiving_beneficiary_account_number'=>$request->AccountNumber,
                'currency' => 'KES',
                'amount' => $request->Amount,
                'transaction_ref' => $transactionReference,
                'transaction_ref_controller' => $transactionRefController,
                'transaction_type' => $transaction_type,
                'transaction_description' => $request->Reason,
                'checkout_id' => $checkoutId,
                'checkout_reference' => $checkoutReference,
                'callback_url' => $request->CallBackUrl,
                'remote_user_ip_address' => $remoteUserIp,
                'remote_user_agent' => $remoteUserAgent,
                'source_account_number' => $accountNumber,
                'source_account_name' => $walletInfo->wallet_name,
                'total_amount'=>$amount_to_dect,
                'remittance_purpose'=>$request->Reason,
                'conversion_rate'=>1,
                'propel_transaction_charge'=>$charge
            ]);
            DB::commit(); // Commit transaction after successful insert
            Log::info("Dispatching PaymentProcessor for PaymentRequest ID: " . $paymentRequest->id);
            PaymentProcessor::dispatch($paymentRequest);
            return response()->json([
                "status" => true,
                "detail" => "Transaction is being processed",
                "TrackingID" => $checkoutReference,
                "InitiatorTransactionReference" => $transactionReference,
                "ResponseCode" => "0",
                "ResponseDescription" => "Transaction is being processed"
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return response()->json([
                "status" => false,
                "ResponseCode" => "500",
                "detail" => "Transaction could not be processed."
            ], 400);
        }
    }
    public function storeCollectionRequestFromMobile(Request $request)
    {
        $mobileMoneyChannels = ["00", "63903", "63902", "97","Propel"];
        $validator = Validator::make($request->all(), [
            "MerchantCode" => "required|string",
            "Amount" => "required|numeric",
            "Currency" => "required|string",
            "PhoneNumber" => "required|string",
            "Channel" => ["required", "string", Rule::in($mobileMoneyChannels)],
            "Reason" => "required|string",
            "InitiatorTransactionReference" => "required|string",
            "CallBackUrl" => "required|url",
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => "input error",
                "detail" => $validator->errors(),
                'ResponseCode'=>'400'
            ], 422);
        }
        $data = $request->get('data'); // Retrieved from middleware
        $accountNumber = $request->input('MerchantCode');
        $walletId=DeveloperAuthService::validateAccountNumber($data, $accountNumber);
        if (!$walletId) {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid Account Number',
                "message" => "Invalid Account Number",
                'ResponseCode'=>'403'
            ], 403);
        }
        // Check if currency is active
        $currencyCode = $request->input('Currency');
        $currency = Currency::where('code', $currencyCode)->where('status', 1)->first();
        if (!$currency) {
            return response()->json([
                "status" => false,
                "message" => "Invalid or inactive currency",
                "detail" => "The specified currency is not available or inactive",
                'ResponseCode'=>'400'
            ], 400);
        }
        Log::info("Currency is valid: " . $currency->code);
        Log::info("wallet id is " . $walletId);
        $walletInfo=UserWallet::find($walletId);
        $charge =0; $this->chargeService->getCharge($request->Channel,$request->Amount,false);
        $channel_name = $this->walletService->getChannelName($request->Channel);
        if (!$channel_name) {
            return response()->json([
                "status" => false,
                "message" => "Invalid channel code",
                "detail" => "The provided channel code is not recognized.",
                'ResponseCode'=>'400'
            ], 400);
        }
        // Check if Channel is 'Propel'
        if ($request->Channel == 'Propel') {
            // Ensure ReceiverNumber is not the same as AccountNumber
            if ($request->PhoneNumber == $accountNumber) {
                return response()->json([
                    "status" => false,
                    "message" => "ReceiverNumber and SenderNumber cannot be the same",
                    "detail" => "You cannot transfer to your own account.",
                    "ResponseCode" => "400"
                ], 400);
            }
            // Check if a wallet with the given ReceiverNumber exists
            $walletExists = UserWallet::where('wallet_account', $request->PhoneNumber)->exists();
            if (!$walletExists) {
                return response()->json([
                    "status" => false,
                    "message" => "Wallet not found",
                    "detail" => "No wallet found with the provided ReceiverNumber.",
                    "ResponseCode" => "404"
                ], 404);
            }
        }
        $merchantCode=$accountNumber;
        $transactionReference=$request->InitiatorTransactionReference;
        $transactionRefController = "$merchantCode-$transactionReference";
        $existingRequest = PaymentRequest::where('transaction_ref_controller', $transactionRefController)->first();
        if ($existingRequest) {
            return response()->json([
                "status" => false,
                'message' => 'Duplicate InitiatorTransactionReference  detected.',
                'details' => 'use unique InitiatorTransactionReference.',
                'ResponseCode'=>'409'
            ], 409);
        }
        try {
            DB::beginTransaction();
            $checkoutId = Str::uuid();
            $checkoutReference = "$merchantCode-$checkoutId";
            $transactionReference=$request->InitiatorTransactionReference;
            $transactionRefController = "$merchantCode-$transactionReference";
            $payerNumber=$request->PhoneNumber;
            // Create Payment Request
            $paymentGateway='Propel';
            $sourceChannel = 'Propel';
            $destinationChannel=$channel_name;
            $destination_channel_code=$request->Channel;
            $remoteUserIp = $request->ip();
            $remoteUserAgent = $request->header('User-Agent');
            $paymentRequest = PaymentRequest::create([
                'payment_gateway' => $paymentGateway,
                'merchant_code' => $accountNumber,
                'source_channel' => $sourceChannel,
                'destination_channel' => $destinationChannel,
                'destination_channel_code' => $destination_channel_code,
                'account_number' => $payerNumber,
//                'destination_account_number' => $receiverNumber,
                'currency' => 'KES',
                'amount' => $request->Amount,
                'transaction_ref' => $transactionReference,
                'transaction_ref_controller' => $transactionRefController,
                'transaction_type' => 'C2B',
                'transaction_description' => $request->Reason,
                'checkout_id' => $checkoutId,
                'checkout_reference' => $checkoutReference,
                'callback_url' => $request->CallBackUrl,
                'remote_user_ip_address' => $remoteUserIp,
                'remote_user_agent' => $remoteUserAgent,
                'source_account_number' => $accountNumber,
                'source_account_name' => $walletInfo->wallet_name,
                'total_amount'=>$request->Amount,
                'remittance_purpose'=>$request->Reason,
                'conversion_rate'=>1,
                'propel_transaction_charge'=>$charge,

            ]);
            DB::commit(); // Commit transaction after successful insert
            $invoiceNumber='PC'.$paymentRequest->id;
            $baseUrl =env('PROPEL_CHECKOUT_PAGE_URL');
            $params = [
                'MerchantCode' => $request->MerchantCode,
                'Amount'       => $request->Amount,
                'ChannelCode'  => $request->Channel,
                'InvoiceNumber'=> $invoiceNumber,
                'Environment'=>env('APP_ENV'),
            ];
            $queryString = http_build_query($params);
            // Base64 encode the entire query string
            $encodedQueryString = base64_encode($queryString);
            // Return the payment link with the encoded data as a query parameter (e.g., "data")
            $paymentLink= $baseUrl . '?data=' . urlencode($encodedQueryString);
            $paymentRequest->update(['payment_url' => 'processing']);
            Log::info("Dispatching PaymentProcessor for PaymentRequest ID: " . $paymentRequest->id);
            PaymentCollectionProcessor::dispatch($paymentRequest);
            if($request->Channel=='Propel'){
                return response()->json([
                    "status" => true,
                    "detail" => "OTP sent. Share the code to complete transaction",
                    "TrackingID" => $checkoutReference,
                    "PaymentGateway"=> "Propel",
                    "InitiatorTransactionReference" => $transactionReference,
                    "InvoiceNumber" => $invoiceNumber,
                    "ResponseCode" => "0",
                    "ResponseDescription" => "Success. Request accepted for processing",
                    "PaymentLink" => $paymentLink
                ]);
            }elseif ($request->Channel=='63902'){
                return response()->json([
                    "status" => true,
                    "detail" => "STK Push sent. Enter your PIN to complete transaction",
                    "TrackingID" => $checkoutReference,
                    "PaymentGateway"=> "Propel",
                    "InitiatorTransactionReference" => $transactionReference,
                    "InvoiceNumber" => $invoiceNumber,
                    "ResponseCode" => "0",
                    "ResponseDescription" => "Success. Request accepted for processing",
                    "PaymentLink" => $paymentLink
                ]);
            }
            //{
//                "status": true,
//  "detail": "OTP sent. Share the code to complete transaction",
//  "PaymentGateway": "SasaPay",
//  "MerchantRequestID": "07102440280",
//  "CheckoutRequestID": "b6cb451a-ef9f-443d-8e64-045c210131df",
//  "TransactionReference": "PR52****11",
//  "ResponseCode": "0",
//  "ResponseDescription": "Success. Request accepted for processing",
//  "CustomerMessage": " 1.GO TO SASAPAY APP OR DIAL *626#  2. SELECT 'PAY' 3. SELECT 'LIPA BILL' 4. ENTER BILLER NUMBER: '413554' 5. ENTER ACCOUNT REFERENCE: 'PR1686' 6. ENTER AMOUNT:KSH '1' 7. ENTER YOUR SASAPAY PIN"
//
//
           // }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return response()->json([
                "status" => false,
                "ResponseCode" => "500",
                "detail" => "Transaction could not be processed."
            ], 400);
        }
    }
    public function storeCollectionRequestFromMobileOld(Request $request)
    {
        $mobileMoneyChannels = ["63902","Propel","Card","Open"];

        $validator = Validator::make($request->all(), [
            "MerchantCode" => "required|string",
            "Amount" => "required|numeric",
            "Currency" => "required|string",
            "PhoneNumber" => "required|string",
            "Channel" => ["required", "string", Rule::in($mobileMoneyChannels)],
            "Reason" => "required|string",
            "InitiatorTransactionReference" => "required|string",
            "CallBackUrl" => "required|url",
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => "input error",
                "detail" => $validator->errors(),
            ], 422);
        }
        $data = $request->get('data'); // Retrieved from middleware
        $accountNumber = $request->input('AccountNumber');
        $walletId=DeveloperAuthService::validateAccountNumber($data, $accountNumber);
        if (!$walletId) {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid Account Number',
                "message" => "Invalid Account Number",
            ], 403);
        }
        Log::info("wallet id is " . $walletId);
        $walletInfo=UserWallet::find($walletId);
        $charge =0;// $this->chargeService->getCharge($request->Channel,$request->Amount);
        try {
            DB::beginTransaction();

            $merchant_code = $request->merchant_code;
            $network_code = $request->network_code;
            $customer_mobile = $request->customer_mobile;
            $amount = $request->amount;
            $currency = $request->currency;
            $transaction_description = $request->transaction_description;
            $callback_url = $request->callback_url;
            $remote_user_ip_address = $request->ip();
            $remote_user_agent = $request->header('User-Agent');
            $destination_account_number = $request->AccountNumber;
            $destination_account_name = $request->destination_account_name;
            $merchant = $request->merchant;
            $merchant_application = $request->merchant_application;

            $checkout_id = (string) Str::uuid();
            $checkout_reference = "$merchant_code-$checkout_id";
            $transaction_ref_controller = "$merchant_code-$checkout_id";

            if (in_array($network_code, ['0', '00', 0])) {
                $customer_wallet = DB::table('savings_accounts')->where('account_number', $customer_mobile)->first();
                if (!$customer_wallet) {
                    return response()->json([
                        'status' => false,
                        'ResponseCode' => '404',
                        'detail' => 'User account not found.',
                    ], Response::HTTP_NOT_FOUND);
                }

                $payment_gateway = 'Propel';
                $source_channel = 'Propel';
                $destination_channel = 'Propel';

                $payment_request = PaymentRequest::create([
                    'payment_gateway' => $payment_gateway,
                    'merchant' => $merchant,
                    'merchant_application' => $merchant_application,
                    'merchant_code' => $merchant_code,
                    'source_channel' => $source_channel,
                    'destination_channel' => $destination_channel,
                    'account_number' => $customer_mobile,
                    'currency' => $currency,
                    'amount' => $amount,
                    'transaction_ref' => $checkout_reference,
                    'transaction_ref_controller' => $transaction_ref_controller,
                    'transaction_type' => 'C2B',
                    'transaction_description' => $transaction_description,
                    'checkout_id' => $checkout_id,
                    'checkout_reference' => $checkout_reference,
                    'callback_url' => $callback_url,
                    'remote_user_ip_address' => $remote_user_ip_address,
                    'remote_user_agent' => $remote_user_agent,
                    'destination_account_number' => $destination_account_number,
                    'destination_account_name' => $destination_account_name,
                ]);

                $transaction_code = random_int(100000, 999999);

                $otp_count = CheckoutOTP::where('merchant', $merchant)
                    ->where('mobile_number', $customer_mobile)
                    ->where('created_at', '>', Carbon::now()->subDay())
                    ->count();

                if ($otp_count > 10) {
                    return response()->json([
                        'status' => false,
                        'detail' => 'Sending OTP error. Limit exceeded. Please contact support.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                CheckoutOTP::create([
                    'merchant' => $merchant,
                    'mobile_number' => $customer_mobile,
                    'checkout_id' => $checkout_id,
                    'otp' => $transaction_code,
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'detail' => 'OTP sent. Share the code to complete the transaction.',
                    'PaymentGateway' => 'SasaPay',
                    'MerchantRequestID' => $checkout_reference,
                    'CheckoutRequestID' => $checkout_id,
                    'TransactionReference' => "PR{$payment_request->id}",
                    'ResponseCode' => '0',
                    'ResponseDescription' => 'Success. Request accepted for processing.',
                ], Response::HTTP_OK);
            }

            return response()->json([
                'status' => false,
                'ResponseCode' => '400',
                'detail' => 'Invalid network code',
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('ERROR: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'ResponseCode' => '400',
                'detail' => 'Transaction could not be processed.',
            ], Response::HTTP_BAD_REQUEST);
        }



    }
}
