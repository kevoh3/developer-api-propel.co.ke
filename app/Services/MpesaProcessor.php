<?php
    namespace App\Services;

    use App\Constants\PaymentGatewayConst;
    use App\Models\PaymentRequest;
    use App\Models\Transaction;
    use App\Models\WithdrawalStage;
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\Log;


    class MpesaProcessor
    {
    public function process(PaymentRequest $payment,Transaction $transaction_Original)
    {
        try {
            Log::info("Starting Mpesa withdrawal process for PaymentRequest ID: {$payment->id}");
            // Insert record into withdrawal staging
            $withdrawal = WithdrawalStage::create([
                'trx_id' => $transaction_Original->trx_id, // Generate a unique transaction ID
                'type' => PaymentGatewayConst::TYPEWITHDRAW,
                'attribute' => 'M-PESA',
                'user_type' => 'MERCHANT',
                'user_id' => $transaction_Original->user_id,
//                'merchant_id' => $payment->merchant_id,
//                'merchant_wallet_id' => $payment->wallet_id,
                'wallet_id' => $transaction_Original->wallet_id,
                'available_balance' => $transaction_Original->available_balance,
                'request_amount' => $transaction_Original->request_amount,
                'request_currency' => $transaction_Original->request_currency,
                'exchange_rate' => $transaction_Original->exchange_rate,
                'total_charge' => $transaction_Original->total_charge,
                'total_payable' => $transaction_Original->total_payable,
                'receive_amount' => $transaction_Original->receive_amount,
                'payment_currency' => $transaction_Original->payment_currency,
                'destination_currency' => $transaction_Original->destination_currency,
                'details' => json_encode($payment),
                'status'                        => PaymentGatewayConst::STATUSPENDING,
                'receiver_account_number'=>$payment->destination_account_number,
                'channel_code'=>$payment->destination_channel_code,
                'transaction_charge'=>$transaction_Original->fixed_charge,
                'sms_charge'=>$transaction_Original->sms_charge,
                'created_at'                    => now(),
                'is_what_transfer'=>'M-PESA',
                'part_b_account' => $transaction_Original->part_b_account,
                'part_b_name' =>  $transaction_Original->part_b_name,
                'party_b_plateform' => $transaction_Original->party_b_plateform,
            ]);
            Log::error("transaction_type: {$payment->transaction_type}");
            if ($payment->transaction_type == 'B2C') {
                return $this->sendPaymentData($withdrawal->id, $transaction_Original->request_amount, $payment->destination_account_number, $payment->destination_channel_code);
            } elseif ($payment->transaction_type == 'B2B-TILL') {
                $payment_type='TILL';
                Log::info("payment type: {$payment_type}");
                return $this->sendB2BPaymentData($withdrawal->id, $transaction_Original->request_amount, $payment->destination_account_number, $payment->destination_channel_code,$payment_type,$payment->receiving_beneficiary_account_number);
            }elseif($payment->transaction_type == 'B2B-PAYBILL'){
                $payment_type='PAYBILL';
                Log::info("payment type: {$payment_type}");
                return $this->sendB2BPaymentData($withdrawal->id, $transaction_Original->request_amount, $payment->destination_account_number, $payment->destination_channel_code,$payment_type,$payment->receiving_beneficiary_account_number);
            }
        } catch (\Exception $e) {
            Log::error("MpesaProcessor failed: " . $e->getMessage());
            return $e->getMessage();
        }
    }
        private function sendPaymentData($merchantTransactionReference, $amount, $receiverNumber, $channel)
        {
            $amount = (string) ((int) floatval($amount));
           // $receiverNumber = $payment->destination_account_number;
            Log::info('reiver number');
            //$channel = $payment->destination_channel_code;
            $callbackUrl = env('MPESA_B2C_CALLBACK_URL');
            if ($channel == '63902') {
                $receiverNumber = '254' . substr($receiverNumber, -9);
                $posturl = env('SM_BASE_URL').'/sm-transfer-money';
            } else {
                $posturl =  env('SM_BASE_URL').'/transfer-money';
            }
            $sendingRef = 'ref-' . $merchantTransactionReference;
            $data = [
                "MerchantTransactionReference" => $sendingRef,
                "Amount" => $amount,
                "ReceiverNumber" => $receiverNumber,
                "Channel" => $channel,
                "CallBackURL" => $callbackUrl
            ];
            Log::info('sending data', ['data' => $data]);
            $response = Http::withHeaders([
                'api-key' => env('SMART_SWITCH_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post($posturl, $data);
            Log::info('Mpesa API Response', ['response' => $response->json()]);
            // Step 3: Handle Response
            if ($response->successful() && $response->json('status') === true) {
                $merchantRequestID = $response->json('MerchantRequestID');
                $checkoutRequestID = $response->json('CheckoutRequestID');
                $transactionReference = $response->json('TransactionReference');
                $detail = $response->json('detail');
                $responseCode = $response->json('ResponseCode', 'Unknown');
                WithdrawalStage::where('id', $merchantTransactionReference)
                    ->update([
                        'MerchantRequestID' => $merchantRequestID,
                        'reqStatus' => 'processing',
                        'CheckoutRequestID' => $checkoutRequestID,
                        'TransactionReference' => $transactionReference,
                        'detail' => $detail,
                        'response_code' => $responseCode,
                    ]);
                Log::info("Mpesa withdrawal processing: {$transactionReference}");
            } else {
                $responseCode = $response->json('ResponseCode', 'Unknown');
                $detail = $response->json('detail', 'No details provided');
                WithdrawalStage::where('id', $merchantTransactionReference)
                    ->update([
                        'MerchantRequestID' => $sendingRef,
                        'reqStatus' => 'failed',
                        'detail' => $detail,
                        'response_code' => $responseCode,
                    ]);

                Log::error("Mpesa withdrawal failed: {$detail}");
            }
            return $response;
        }
        private  function sendB2BPaymentData($merchantTransactionReference, $amount, $receiverNumber, $channel,$payment_type,$payment_account_number)
        {
            $callbackUrl = env('MPESA_B2B_CALLBACK_URL');
            if ($payment_type=='TILL'){

                $posturl= env('SM_INITIATE_TILL_URL');

            }else{
                $posturl=env('SM_INITIATE_PAYBILL_URL');
            }
            $sendingRef = 'ref-' . $merchantTransactionReference;
            Log::info($posturl);
            $amount = (string) ((int) floatval($amount));
            $data = [
                "MerchantTransactionReference" => $sendingRef,
                "Amount" => $amount,
                "ReceiverNumber" => $receiverNumber,
                "Channel" => $channel,
                "CallBackURL" => $callbackUrl,
                'AccountType'=>$payment_type,
                'AccountReference'=>$payment_account_number
            ];
            Log::info('data ya kwa b2b',$data);
            $response = Http::withHeaders([
                'api-key' =>env('SMART_SWITCH_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post($posturl, $data);
            Log::info('b2bhapa',['response' => $response]);
            if ($response->successful() && $response->json('status') === true) {
                $merchantRequestID = $response->json('MerchantRequestID');
                $checkoutRequestID = $response->json('CheckoutRequestID');
                $transactionReference = $response->json('TransactionReference');
                $detail = $response->json('detail');
                Log::info('TransactionReference'.$transactionReference);
                $responseCode = $response->json('ResponseCode', 'Unknown');
                WithdrawalStage::where('id', $merchantTransactionReference)
                    ->update([
                        'MerchantRequestID' => $merchantRequestID,
                        'reqStatus' => 'processing',
                        'CheckoutRequestID' => $checkoutRequestID,
                        'TransactionReference' => $transactionReference,
                        'detail' => $detail,
                        'response_code' => $responseCode,
                    ]);
                Log::info("Mpesa b2b processing: {$transactionReference}");
            } else {
                $responseCode = $response->json('ResponseCode', 'Unknown');
                $detail = $response->json('detail', 'No details provided');
                WithdrawalStage::where('id', $merchantTransactionReference)
                    ->update([
                        'MerchantRequestID' => $sendingRef,
                        'reqStatus' => 'failed',
                        'detail' => $detail,
                        'response_code' => $responseCode,
                    ]);
                Log::error("Mpesa b2b failed: {$detail}");
            }
            return $response;
        }
}
