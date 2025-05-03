<?php

namespace App\Jobs;

use App\Constants\PaymentGatewayConst;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\TransactionStage;
use App\Models\UserWallet;
use App\Services\WalletService;
use App\Services\CardCollectionProcessor;
use App\Services\MpesaCollectionProcessor;
use App\Services\PropelCollectionProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentCollectionProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        $url = env('PROPEL_COLLECTION_PROCESSOR_URL');


        try {
            $response = Http::post($url, [
                'paymentRequestId' => $this->paymentRequest->id,
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

}
