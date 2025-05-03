<?php
namespace App\Traits\PaymentGateway;

use Exception;
use App\Models\TemporaryData;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Http\Helpers\PaymentGateway;
use App\Http\Controllers\Api\V1\User\AddMoneyController;
use Illuminate\Http\Request;

trait Mpesa {

    private $mpesa_gateway_credentials;
    private $request_credentials;
    private $mpesa_api_base_url = "https://sandbox.safaricom.co.ke/";
    private $mpesa_api_v1       = "v1";
    private $mpesa_shortcode = "174379";  // Your M-Pesa shortcode (should be retrieved from configuration)
    private $mpesa_shortcode_key = "YourSecretKey"; // Your secret key (should be retrieved from configuration)

    // Initialize the gateway with request data
    public function mpesaInit($output = null) {
        if(!$output) $output = $this->output;
        $request_credentials = $this->getMpesaRequestCredentials($output);
        $this->createMpesaPaymentLink($output, $request_credentials);
    }

    // Register all M-Pesa API endpoints
    public function registerMpesaEndpoints($endpoint_key = null)
    {
        $endpoints = [
            'lipa-na-mpesa-online' => $this->mpesa_api_base_url . "mpesa/onlinepaymentservice/v1/charges",
        ];

        if($endpoint_key) {
            if(!array_key_exists($endpoint_key, $endpoints)) throw new Exception("Endpoint key [$endpoint_key] not registered! Register it in registerMpesaEndpoints() method");
            return $endpoints[$endpoint_key];
        }

        return $endpoints;
    }

    // Create a payment link to M-Pesa
    public function createMpesaPaymentLink($output, $request_credentials) {
        $endpoint = $this->registerMpesaEndpoints('lipa-na-mpesa-online');

        $temp_record_token = generate_unique_string('temporary_datas', 'identifier', 60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel

        $redirection = $this->getRedirection();
        $url_parameter = $this->getUrlParams();

        $user = auth()->guard(get_auth_guard())->user();

        $temp_data = $this->mpesaJunkInsert($temp_record_token); // create temporary information

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request_credentials->token,
        ])->post($endpoint, [
            'Shortcode'     => $this->mpesa_shortcode,
            'Amount'         => $output['amount']->total_amount,
            'Currency'       => $output['currency']->currency_code,
            'PhoneNumber'    => $user->phone_number,  // Assuming phone number is available
            'RedirectURL'    => $this->setGatewayRoute($redirection['return_url'], PaymentGatewayConst::MPESA, $url_parameter),
            'PhoneNumber'    => $user->phone_number,  // Assuming phone number is available
        ])->throw(function ($response, $exception) use ($temp_data) {
            $temp_data->delete();
            throw new Exception($exception->getMessage());
        })->json();

        $response_array = json_decode(json_encode($response), true);

        // Save the response to temporary data
        $temp_data_contains = json_decode(json_encode($temp_data->data), true);
        $temp_data_contains['response'] = $response_array;

        $temp_data->update([
            'data' => $temp_data_contains,
        ]);

        // If JSON response is expected, return it
        if(request()->expectsJson()) {
            $this->output['redirection_response'] = $response_array;
            $this->output['redirect_links'] = [];
            $this->output['redirect_url'] = $response_array['data']['link'];
            return $this->get();
        }

        // Otherwise, redirect to M-Pesa payment link
        return redirect()->away($response_array['data']['link']);
    }

    // Insert temporary transaction data for tracking
    public function mpesaJunkInsert($temp_token) {
        $output = $this->output;

        $data = [
            'gateway'       => $output['gateway']->id,
            'currency'      => $output['currency']->id,
            'amount'        => json_decode(json_encode($output['amount']), true),
            'wallet_table'  => $output['wallet']->getTable(),
            'wallet_id'     => $output['wallet']->id,
            'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard' => get_auth_guard(),
        ];

        return TemporaryData::create([
            'type'          => PaymentGatewayConst::TYPEADDMONEY,
            'identifier'    => $temp_token,
            'data'          => $data,
        ]);
    }

    // Get M-Pesa API credentials
    public function getMpesaCredentials($output)
    {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $public_key_sample = ['public key','test key','sandbox public key','public', 'test public','mpesa public key', 'mpesa public'];
        $secret_key_sample = ['secret','secret key','mpesa secret','mpesa secret key'];
        $encryption_key_sample    = ['encryption','encryption key','mpesa encryption','mpesa encryption key'];

        $public_key    = PaymentGateway::getValueFromGatewayCredentials($gateway, $public_key_sample);
        $secret_key         = PaymentGateway::getValueFromGatewayCredentials($gateway, $secret_key_sample);
        $encryption_key    = PaymentGateway::getValueFromGatewayCredentials($gateway, $encryption_key_sample);

        $mode = $gateway->env;
        $gateway_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => PaymentGatewayConst::ENV_SANDBOX,
            PaymentGatewayConst::ENV_PRODUCTION => PaymentGatewayConst::ENV_PRODUCTION,
        ];

        if(array_key_exists($mode, $gateway_register_mode)) {
            $mode = $gateway_register_mode[$mode];
        } else {
            $mode = PaymentGatewayConst::ENV_SANDBOX;
        }

        $credentials = (object) [
            'public_key'                => $public_key,
            'secret_key'                => $secret_key,
            'encryption_key'            => $encryption_key,
            'mode'                      => $mode
        ];

        $this->mpesa_gateway_credentials = $credentials;

        return $credentials;
    }

    // Get the request credentials for M-Pesa
    public function getMpesaRequestCredentials($output = null)
    {
        if(!$this->mpesa_gateway_credentials) $this->getMpesaCredentials($output);
        $credentials = $this->mpesa_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        if($output['gateway']->env == PaymentGatewayConst::ENV_PRODUCTION) {
            $request_credentials['token'] = $credentials->secret_key;
        } else {
            $request_credentials['token'] = $credentials->secret_key;
        }

        $this->request_credentials = (object) $request_credentials;
        return (object) $request_credentials;
    }

    // Checks if this is an M-Pesa gateway
    public function isMpesa($gateway)
    {
        $search_keyword = ['mpesa','mpesa gateway','gateway mpesa','mpesa payment gateway'];
        $gateway_name = $gateway->name;

        $search_text = Str::lower($gateway_name);
        $search_text = preg_replace("/[^A-Za-z0-9]/","",$search_text);
        foreach($search_keyword as $keyword) {
            $keyword = Str::lower($keyword);
            $keyword = preg_replace("/[^A-Za-z0-9]/","",$keyword);
            if($keyword == $search_text) {
                return true;
                break;
            }
        }
        return false;
    }

    // Handle the success payment response
    public function mpesaSuccess($output) {
        $redirect_response = $output['tempData']['data']->callback_data ?? false;
        if($redirect_response == false) {
            throw new Exception("Invalid response");
        }

        if($redirect_response->status == "cancelled") {
            $identifier = $output['tempData']['identifier'];
            $response_array = json_decode(json_encode($redirect_response), true);

            if(isset($response_array['r-source']) && $response_array['r-source'] == PaymentGatewayConst::APP) {
                if($output['type'] == PaymentGatewayConst::TYPEADDMONEY) {
                    return (new AddMoneyController())->cancel(new Request([
                        'token' => $identifier,
                    ]), PaymentGatewayConst::MPESA);
                }
            }

            $this->setUrlParams("token=" . $identifier); // set Parameter to URL for identifying when return success/cancel
            $redirection = $this->getRedirection();
            $url_parameter = $this->getUrlParams();

            $cancel_link = $this->setGatewayRoute($redirection['cancel_url'], PaymentGatewayConst::MPESA, $url_parameter);
            return redirect()->away($cancel_link);
        }

        if($redirect_response->status == "success" || $redirect_response->status == "successful") {
            $output['capture'] = $output['tempData']['data']->response ?? "";

            try {
                $this->createTransaction($output);
            } catch(Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }
}
