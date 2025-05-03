<?php

namespace App\Jobs;

use App\Constants\PaymentGatewayConst;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\UserWallet;
use http\Url;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;


class PaymentProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 3;
    public $backoff = 10;

    protected $paymentRequest;

    /**
     * Create a new job instance.
     */
    public function __construct(PaymentRequest $paymentRequest)
    {
        $this->paymentRequest = $paymentRequest;
    }

    /**
     * Execute the job.
     */


    public function handle()
    {
        Log::info("Processing PaymentProcessor for PaymentRequest ID: " . $this->paymentRequest->id);
       // DB::beginTransaction();
        try {
            $payment = $this->paymentRequest;
            $wallet = UserWallet::where('wallet_account', $payment->merchant_code)->first();
            Log::info('wallet details', ['wallet' => $wallet]);
// First condition: Wallet does not exist
            if (!$wallet) {
                Log::error("Wallet {$payment->source_account_number} is not found.");
               // DB::rollBack();
                return;
            }
// Second condition: Wallet exists but status is not 1 (active)
            if ($wallet->status !== 1) {
                Log::error("Wallet {$payment->source_account_number} is inactive.");
                //DB::rollBack();
                return;
            }
            $this->sendToProcessor($payment->id);
            return;

        } catch (\Exception $e) {
           // DB::rollBack(); // Rollback if any error occurs
            Log::error("Payment Processing Failed: " . $e->getMessage());
        }
    }

    function generateTransactionCode($wallet_id)
    {
        $timestamp = time(); // Current timestamp
        $unique_number = $timestamp . $wallet_id; // Concatenate timestamp and wallet ID
        return $this->base62Encode($unique_number); // Convert to Base62
    }

    function base62Encode($number)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($characters);
        $result = '';

        while ($number > 0) {
            $result = $characters[$number % $base] . $result;
            $number = intdiv($number, $base);
        }

        return $result;
    }

    function base62Decode($encoded)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $base = strlen($characters);
        $length = strlen($encoded);
        $number = 0;

        for ($i = 0; $i < $length; $i++) {
            $number = $number * $base + strpos($characters, $encoded[$i]);
        }

        return $number;
    }
    function sendToProcessor($paymentRequestId)
    {
        $url = env('PROPEL_PROCESSOR_URL');

        try {
            $response = Http::post($url, [
                'paymentRequestId' => $paymentRequestId,
            ]);

            $responseData = $response->json();
            Log::info("Processor response", ['info'=>$responseData]);

            if (!$response->ok() || !isset($responseData['status']) || $responseData['status'] !== true) {
                throw new \Exception('Processor responded with an error: ' . json_encode($responseData));
            }

            return $responseData;

        } catch (\Exception $e) {
            Log::error("Processor request failed: " . $e->getMessage());
            throw $e; // Re-throwing allows the job to be retried
        }
    }
//    function sendToProcessor($paymentRequestId)
//    {
//        $url = env('PROPEL_PROCESSOR_URL');
//
//        $maxAttempts = 1;
//        $attempt = 0;
//
//        while ($attempt < $maxAttempts) {
//            try {
//                $attempt++;
//                $response = Http::post($url, [
//                    'paymentRequestId' => $paymentRequestId,
//                ]);
//
//                $responseData = $response->json();
//
//                Log::info("Attempt {$attempt}: Processor response", $responseData);
//
//                // Check if custom status is true and ResponseCode is 200
//                if ($response->ok() && isset($responseData['status']) && $responseData['status'] === true) {
//                    return $responseData; // Success
//                }
//
//                // If custom response indicates failure and retryable
//                if (isset($responseData['ResponseCode']) && in_array($responseData['ResponseCode'], [400])) {
//                    Log::warning("Attempt {$attempt} failed with input error, retrying...");
//                    sleep(1); // wait before retry
//                    continue;
//                }
//
//                // If not retryable error, break and return
//                break;
//
//            } catch (\Exception $e) {
//                Log::error("Exception on attempt {$attempt}: " . $e->getMessage());
//                sleep(1); // wait before retrying on exception
//            }
//        }
//        return [
//            'success' => false,
//            'message' => 'Failed to process after multiple attempts',
//        ];
//    }
}
