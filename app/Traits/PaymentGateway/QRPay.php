<?php

namespace App\Traits\PaymentGateway;

use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use Illuminate\Support\Facades\Http;
use App\Constants\PaymentGatewayConst;

trait QRPay {

    private $qrpay_gateway_credentials;
    private $qrpay_access_token;

    public function qrpayInit($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getQrpayCredentials($output);
        $request_credentials = $this->getQrpayRequestCredentials();
        $access_token = $this->getQrpayAccessToken();

        return $this->qrpayCreateOrder($request_credentials, $access_token);
    }

    public function getQrpayCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $sandbox_url_sample = ['sandbox','sandbox url','sandbox env', 'test url', 'test', 'sandbox environment', 'qrpay sandbox url', 'qrpay sandbox' , 'qrpay test'];
        $production_url_sample = ['live','live url','live env','live environment', 'qrpay live url','qrpay live','production url', 'production link'];

        $client_id_sample = ['client id','client key','primary key','primary id','client token','primary token'];
        $secret_id_sample = ['secret id','secret key','secret token'];

        $sandbox_url = $this->getValueFromGatewayCredentials($gateway,$sandbox_url_sample);
        $production_url = $this->getValueFromGatewayCredentials($gateway,$production_url_sample);
        $client_id = $this->getValueFromGatewayCredentials($gateway,$client_id_sample);
        $secret_id = $this->getValueFromGatewayCredentials($gateway,$secret_id_sample);
        
        $mode = $gateway->env;
        
        $gateway_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "production",
        ];

        if(array_key_exists($mode,$gateway_register_mode)) {
            $mode = $gateway_register_mode[$mode];
        }else {
            $mode = "sandbox";
        }

        $credentials = (object) [
            'sandbox_url'       => $sandbox_url,
            'production_url'    => $production_url,
            'client_id'         => $client_id,
            'secret_id'         => $secret_id,
            'mode'              => $mode,
        ];

        $this->qrpay_gateway_credentials = $credentials;

        return $credentials;
    }

    public function getQrpayRequestCredentials($output = null) {
        $credentials = $this->qrpay_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        if($output['gateway']->env == PaymentGatewayConst::ENV_PRODUCTION) {
            $request_credentials['url']     = $credentials->production_url;
            $request_credentials['client_id']   = $credentials->client_id;
            $request_credentials['secret_id']   = $credentials->secret_id;
        }else {
            $request_credentials['url']     = $credentials->sandbox_url;
            $request_credentials['client_id']   = $credentials->client_id;
            $request_credentials['secret_id']   = $credentials->secret_id;
        }
        $this->qrpay_gateway_credentials = (object) $request_credentials;
        return (object) $request_credentials;
    }

    public function registerQrpayEndpoints() {
        return [
            'accessToken'       => 'authentication/token',
            'initiateOrder'     => 'payment/create',
        ];
    }

    public function getQrpayEndpoint($name) {
        $endpoints = $this->registerQrpayEndpoints();
        if(array_key_exists($name,$endpoints)) {
            return $endpoints[$name];
        }
        throw new Exception("Oops! Request endpoint not registered!");
    }

    public function getQrpayAccessToken() {
        $request_credentials = $this->qrpay_gateway_credentials;
        $request_base_url = $request_credentials->url;

        $request_url = $request_base_url . "/" . $this->getQrpayEndpoint('accessToken');
        $client_id = $request_credentials->client_id;
        $secret_id = $request_credentials->secret_id;

        $response = Http::withHeaders([
            "Content-Type"      => "application/json",
            "Accept"            => "application/json",
        ])->post($request_url,[
            'client_id' => $client_id,
            'secret_id' => $secret_id,
        ]);

        if($response->failed()) {
            $error_response = json_decode($response->body(),true);
            $message = $error_response['message']['error'][0] ?? "";
            throw new Exception($message);
        }

        if($response->successful()) {
            $response_array = json_decode($response->body(),true);

            if(isset($response_array['data']['access_token']) && $response_array['data']['access_token'] != null) {
                $this->qrpay_access_token = $response_array['data']['access_token'];
                return $response_array['data']['access_token'];
            }
        }

        throw new Exception("Something went wrong! Failed to generate access token");
    }

    public function qrpayCreateOrder($credentials, $access_token = null) {

        if($access_token == null) $access_token = $this->qrpay_access_token;
        $request_base_url       = $credentials->url;

        $temp_record_token = generate_unique_string('temporary_datas','identifier',60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel

        $redirection = $this->getRedirection();
        $url_parameter = $this->getUrlParams();
        
        $endpoint = $request_base_url . "/" . $this->getQrpayEndpoint('initiateOrder');

        // dd($this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::QRPAY,$url_parameter));

        $response = Http::withToken($access_token)->post($endpoint,[
            'custom'            => uniqid(),
            'amount'            => (string) $this->output['amount']->total_amount,
            'currency'          => $this->output['amount']->sender_cur_code,
            'cancel_url'        => $this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::QRPAY,$url_parameter),
            'return_url'        => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::QRPAY,$url_parameter),
        ]);

        if($response->failed()) {
            $error_response = json_decode($response->body(),true);
            $message = $error_response['message']['error'][0] ?? "";
            throw new Exception($message);
        }

        if($response->successful()) {
            $response_array = json_decode($response->body(),true);
            if(isset($response_array['data']['payment_url']) && $response_array['data']['payment_url'] != null) {
                // create junk transaction
                $this->qrpayJunkInsert($this->output,$response_array['data'],$temp_record_token);

                if(request()->expectsJson()) {
                    $this->output['redirection_response']   = $response_array['data'];
                    $this->output['redirect_links']         = [];
                    $this->output['redirect_url']           = $response_array['data']['payment_url'];
                    return $this->get();
                }
                return redirect()->away($response_array['data']['payment_url']);
            }
        }

        throw new Exception("Something went wrong! Please try again");
    }

    public function qrpayJunkInsert($output,$response, $identifier_token) {

        $data = [
            'gateway'       => $output['gateway']->id,
            'currency'      => $output['currency']->id,
            'amount'        => json_decode(json_encode($output['amount']),true),
            'response'      => $response,
            'wallet_table'  => $output['wallet']->getTable(),
            'wallet_id'     => $output['wallet']->id,
            'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard' => get_auth_guard(),
        ];

        return TemporaryData::create([
            'type'          => PaymentGatewayConst::TYPEADDMONEY,
            'identifier'    => $identifier_token,
            'data'          => $data,
        ]);
        
    }

    public function qrpaySuccess($output = null) {
        $output['capture']      = $output['tempData']['data']->response ?? "";

        // need to insert new transaction in database
        try{
            $this->createTransaction($output);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function isQrpay($gateway) {
        $search_keyword = ['qrpay','qrpay gateway','qrpay payment','qrpay fait gateway','gateway qrpay'];
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

}