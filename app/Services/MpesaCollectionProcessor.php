<?php

namespace App\Services;

use App\Constants\PaymentGatewayConst;
use App\Models\PaymentRequest;
use App\Models\TransactionStage;
use App\Models\WithdrawalStage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaCollectionProcessor
{
    public function process(PaymentRequest $payment)
    {
        try {
            Log::info("Starting Mpesa collection process for PaymentRequest ID: {$payment->id}");
            return $this->sendToSmartSwithInvoiceSwitch($payment->destination_channel_code, $payment->amount, $payment->account_number, $payment->id, $payment->merchant_code);

        } catch (\Exception $e) {
            Log::error("MpesaProcessor failed: " . $e->getMessage());
            return $e->getMessage();
        }
    }
    private function sendToSmartSwithInvoiceSwitch($NetworkCode, $Amount, $PhoneNumber, $AccountReference, $transactionReference)
    {
        $sendingRef = 'RM-' . $AccountReference;
        $CallBackURL =env('COLLECTION_CALLBACK_URL');
        if ($NetworkCode == '63902') {
              $posturl = env('SM_BASE_URL').'/sm-mp-generate-invoice';
        } else {
            $posturl = env('SM_BASE_URL').'/api/smartswitch-invoice';
        }
        $response = Http::withHeaders([
            'api-key' =>  env('SMART_SWITCH_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post($posturl, [
//            'MerchantCode' => '356356',
            'MerchantCode' => '777111',
            'NetworkCode' => $NetworkCode,
            'Amount' => $Amount,
            'CallBackURL' => $CallBackURL,
            'PhoneNumber' => $PhoneNumber,
            'TransactionDesc' => 'Request Payment',
            'AccountReference' => $sendingRef,
            'TransactionReference' => $transactionReference,
        ]);
        $paymentRequest = PaymentRequest::find($AccountReference);
        if ($response->successful()) {
            $data = $response->json();
            $majibu = [
                'error' => 'Request failed',
                'status' => $response->status(),
                'body' => $response->body(),
            ];
            Log::info('majibu', $majibu);
            Log::info('heredata', ['response data' => $data]);
            if ($data['status']) {
                $paymentRequest->MerchantRequestID = $data['MerchantRequestID'];
                $paymentRequest->TransactionReference = $data['TransactionReference'];
                $paymentRequest->CheckoutRequestID = $data['CheckoutRequestID'];
                $paymentRequest->CustomerMessage = $data['CustomerMessage'];
                $paymentRequest->reqStatus = 'processing';
                $paymentRequest->save();
            } else {
                $paymentRequest->MerchantRequestID = $data['MerchantRequestID'];
                $paymentRequest->TransactionReference = $data['TransactionReference'];
                $paymentRequest->CheckoutRequestID = $data['CheckoutRequestID'];
                $paymentRequest->CustomerMessage = $data['CustomerMessage'];
                $paymentRequest->reqStatus = 'declined';
                $paymentRequest->save();

            }

        } else {
            $majibu = [
                'error' => 'Request failed',
                'status' => $response->status(),
                'body' => $response->body(),
            ];
            Log::info('majibu', $majibu);
            $paymentRequest->reqStatus = 'failed';
            $paymentRequest->save();
        }
        return $response;


    }
}
