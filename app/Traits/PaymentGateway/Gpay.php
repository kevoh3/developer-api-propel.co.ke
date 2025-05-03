<?php 

namespace App\Traits\PaymentGateway;

use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use Illuminate\Support\Carbon;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

trait Gpay{

    private $gpay_access_token;
    private $gpay_gateway_credentials;

    public function gpayInit($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getGpayCredentials($output);
        $get_access_response = $this->getGpayAccessToken($credentials);
        $gpay_access_token = $this->gpay_access_token;

        // $gpay_create_order = $this->gpayCreateOrder($output);
        return $this->gpayCreateOrder($output);

    }

    public function getGpayCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");
        $merchant_code_sample = ['merchant code','merchant','merchant_code','gpay merchant code', 'merchant code gpay','gpay code'];
        $password_sample = ['password','gpay password','merchant password'];
        $sandbox_url_sample = ['sandbox url','gpay sandbox url','sandbox','gpay gateway sandbox','sandbox link'];
        $production_url_sample = ['production url','gpay production url','production','gpay gateway production','production link','live link','live url','gpay live url',];

        $merchant_code = '';
        $outer_break = false;
        foreach($merchant_code_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->gpayPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->gpayPlainText($label);

                if($label == $modify_item) {
                    $merchant_code = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $password = '';
        $outer_break = false;
        foreach($password_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->gpayPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->gpayPlainText($label);

                if($label == $modify_item) {
                    $password = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $sandbox_url = '';
        $outer_break = false;
        foreach($sandbox_url_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->gpayPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->gpayPlainText($label);

                if($label == $modify_item) {
                    $sandbox_url = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $production_url = '';
        $outer_break = false;
        foreach($production_url_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->gpayPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->gpayPlainText($label);

                if($label == $modify_item) {
                    $production_url = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

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
            'production_url'    => $production_url,
            'sandbox_url'       => $sandbox_url,
            'merchant_code'     => $merchant_code,
            'password'          => $password,
            'mode'              => $mode,
        ];

        $this->gpay_gateway_credentials = $credentials;

        return $credentials;

    }

    public function gpayPlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }

    public function getGpayAccessToken($credentials) {
        $production_url = $credentials->production_url ?? "";
        $sandbox_url = $credentials->sandbox_url ?? "";
        $url = $sandbox_url;
        if($credentials->mode == "production") {
            $url = $production_url;
        }
        if($url == "" || $url == null) throw new Exception("Payment gateway URL not found! Please add production/sandbox URL.");

        $auth_endpoint = "/authentication/token";
        $endpoint = $url . $auth_endpoint;

        $merchant_code = $credentials->merchant_code ?? "";
        $password = $credentials->password ?? "";
        if($merchant_code == "" || $password == "") throw new Exception("Gateway credentials not found! Please add merchant credentials.");

        $input_fields = "MerchantCode=".$merchant_code."&password=".$password;

        $options = array(
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER         => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING       => "",       // handle all encodings
            CURLOPT_USERAGENT      => "spider", // who am i
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT        => 120,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => false,     // Disabled SSL Cert checks
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $input_fields,
        );

        $curl      = curl_init($endpoint);
        curl_setopt_array($curl,$options);
        $response = curl_exec($curl);
        curl_close($curl);

        if($response == null || $response == "") {
            throw new Exception("Gateway response failed!");
        }

        try{
            $response = json_decode($response,true);
        }catch(Exception $e) {
            throw new Exception("Gateway response not formatted!");
        }

        if(!isset($response['meta']['code']) || $response['meta']['code'] != 200)  {
            if(!isset($response['meta']['msg'])) $msg =  "Something went wrong! Please try again";
            $msg = $response['meta']['msg'];
            throw new Exception($msg);
        }

        if(!isset($response['response']['token'])) throw new Exception("Failed to generate gateway access token.");
        $this->gpay_access_token = $response['response']['token'];
        return $response['response'];
    }

    public function gpayFilterGatewayCredentials() {
        $gpay_gateway_credentials = $this->gpay_gateway_credentials;
        if($gpay_gateway_credentials == null) {
            throw new Exception("Gateway credentials not found!");
        }

        $gpay_gateway_credentials = json_decode(json_encode($gpay_gateway_credentials),true);

        $return_data = [];
        $return_data['url'] = $gpay_gateway_credentials['sandbox_url'] ?? "";
        if($gpay_gateway_credentials['mode'] == "production") {
            $return_data['url'] = $gpay_gateway_credentials['production_url'] ?? "";
        }
        $return_data['merchant_code']   = $gpay_gateway_credentials['merchant_code'] ?? "";
        $return_data['password']        = $gpay_gateway_credentials['password'] ?? "";

        if($return_data['url'] == "" || $return_data['merchant_code'] == "" || $return_data['merchant_code'] == "") {
            throw new Exception("Merchant credentials not found!");
        }
        return (object) $return_data;
    }

    public function gpayCreateOrder($output = null) {
        if($output == null) $output = $this->output;
        $validated = Validator::make($output['form_data'],[
            'bank'      => "required|string",
        ])->validate();

        $redirection = $this->getRedirection();
        $gpay_gateway_credentials = $this->gpayFilterGatewayCredentials();
        $create_order_endpoint = "/order/init";
        $endpoint = $gpay_gateway_credentials->url . $create_order_endpoint;

        $merchant_code      = $gpay_gateway_credentials->merchant_code;
        $order_id           = Carbon::parse(now())->format("Ymd") . "-" . time();
        $order_amount       = $output['amount']->total_amount;
        $order_currency     = $output['currency']->currency_code;
        // $order_title        = "Test Order";
        // $order_desc         = "Test Order Desc";
        $order_time         = (int) gettimeofday(true) * 1000;
        // $order_item         = '[{"itemid":"","itemname":"","itemprice":"","itemquantity":""}]';
        $bank_code          = $validated['bank'];
        // $customer_name      = "GTEL";
        // $customer_id        = "4455";
        // $phone              = "0989202376";
        // $email              = "gxudev@gmail.com";
        // $address            = "Ha Noi";
        $callback_url       = route($redirection['return_url'],PaymentGatewayConst::G_PAY);
        $webhook_url        = route($redirection['return_url'],PaymentGatewayConst::G_PAY);
        // $use_custom_domain  = false;
        $language           = "vi";
        $service_code       = "PAYMENTGATEWAY";
        // $expired_order      = 600000;
        // $voucher_info       = "KUDHG873456878KHDFKKSD4598";
        $payment_method     = "BANK_ATM";
        $payment_type       = "IMMEDIATE";
        $embed_data         = json_encode(['key' => "value"]);
        $token_id           = ""; // do not have any token id
        $signature_input    = "merchant_code=".$merchant_code."&order_id=".$order_id."&order_amt=".$order_amount."&embed_data=".$embed_data."&order_currency=".$order_currency."&language=".$language;

        $privet_key_file = resource_path("credentials/gpay_privet_key.pem");
        $privet_key_file_data = file_get_contents($privet_key_file);

        $pkeyid = openssl_pkey_get_private($privet_key_file_data);
        openssl_sign($signature_input, $binary_signature, $pkeyid, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($binary_signature);

        $input_data_array = [
            'merchant_code'     => $merchant_code,
            'order_id'          => $order_id,
            'order_amt'         => $order_amount,
            'embed_data'        => $embed_data,
            'order_currency'    => $order_currency,
            'language'          => $language,
            'order_time'        => $order_time,
            'bank_code'         => $bank_code,
            'callback_url'      => $callback_url,
            'webhook_url'       => $webhook_url,
            'service_code'      => $service_code,
            'payment_method'    => $payment_method,
            'payment_type'      => $payment_type,
            'signature'         => $signature,
        ];

        $options = array(
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER         => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT        => 120,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => false,     // Disabled SSL Cert checks
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER => [
                "Authorization:  Bearer " .$this->gpay_access_token,
                "Content-Type: application/json",
            ],
            CURLOPT_POSTFIELDS     => json_encode($input_data_array),
        );

        try{
            $curl       = curl_init($endpoint);
            curl_setopt_array($curl,$options);
            $response   = curl_exec($curl);
            curl_close($curl);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
        
        if($response == null || $response == "") throw new Exception("Something went wrong! Please try again");
        
        try{
            $response = json_decode($response,true);
        }catch(Exception $e) {
            throw new Exception("Gateway response not proper. Please try again");
        }
        
        if(isset($response['meta']) && $response['meta']['code'] == 200) {
            // Response Success
            if(isset($response['response']) && isset($response['response']['order_url'])) {
                // Successfully url generated
                $this->gpayJunkInsert($output,$response);
                if(request()->expectsJson()) {
                    $this->output['redirection_response']   = $response;
                    $this->output['redirect_links']         = [];
                    $this->output['redirect_url']           = $response['response']['order_url'];
                    return $this->get();
                }
                return redirect()->away($response['response']['order_url']);
            }
        }else if(isset($response['meta']) && is_array($response['meta'])) {
            if(array_key_exists("msg",$response['meta'])) {
                throw new Exception($response['meta']['msg']);
            }
        }else {
            if(isset($response['response']) && is_string($response['response'])) {
                throw new Exception($response['response']);
            }
        }

        throw new Exception("Payment gateway not response properly!");
    }

    public function gpayJunkInsert($output,$response) {
        $gpay_trans_id = $response['response']['gpay_trans_id'];
        $signature = $response['response']['signature'];

        $data = [
            'gateway'   => $output['gateway']->id,
            'currency'  => $output['currency']->id,
            'amount'    => json_decode(json_encode($output['amount']),true),
            'response'  => $response,
            'wallet_table'  => $output['wallet']->getTable(),
            'wallet_id'     => $output['wallet']->id,
            'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard' => get_auth_guard(),
        ];

        return TemporaryData::create([
            'type'          => PaymentGatewayConst::TYPEADDMONEY,
            'identifier'    => $gpay_trans_id,
            'data'          => $data,
        ]);
    }

    public function gpayOrderDetails($endpoint) {
        $gpay_gateway_credentials = $this->gpayFilterGatewayCredentials();

        $privet_key_file = resource_path("credentials/gpay_privet_key.pem");
        $privet_key_file_data = file_get_contents($privet_key_file);

        $privet_key_instance = openssl_pkey_get_private($privet_key_file_data);
        openssl_sign("merchant_code=".$gpay_gateway_credentials->merchant_code."&gpay_trans_id=34616916113", $binary_signature, $privet_key_instance, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($binary_signature);

        $input_fields = "merchant_code=".$gpay_gateway_credentials->merchant_code."&gpay_trans_id=34616916113&signature=".$signature;
        $url_data = "?".$input_fields;

        $endpoint .= $url_data;

        $options = array(
            CURLOPT_RETURNTRANSFER => true,     
            CURLOPT_HEADER         => false,    
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER => [
                "Authorization:  Bearer " .$this->gpay_access_token,
            ],
        );


        $curl      = curl_init($endpoint);
        curl_setopt_array($curl,$options);
        $response = curl_exec($curl);
        curl_close($curl);

        // dd($response);
    }

    public static function isGpay($gateway) {
        $search_keyword = ['gpay','g-pay','gpay payment gateway','gpay gateway','gpay payment','gpay domestic gateway','gpay domestic payment gateway','gpay vietnam'];
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

    public static function getBankList() {
        $endpoint = "https://payment.g-pay.vn/api/v3/order/bank-list";

        $options = array(
            CURLOPT_RETURNTRANSFER => true,     
            CURLOPT_HEADER         => false,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "content-type:  application/json",
            ],
        );


        $curl      = curl_init($endpoint);
        curl_setopt_array($curl,$options);
        $response = curl_exec($curl);
        
        try{
            $response_array = json_decode($response,true);
        }catch(Exception $e) {
            return false;
        }

        if(!isset($response_array['meta'])) return false;
        if(!isset($response_array['meta']['code'])) return false;
        if($response_array['meta']['code'] != 200) return false;
        if(!isset($response_array['response'])) return false;
        if(!is_array($response_array['response'])) return false;

        return (object) $response_array['response'];
    }

    public function gpaySuccess($output = null) {
        if(!$output) $output = $this->output;
        $temp_data = $output['tempData'];

        $callback_data = $temp_data['data']->callback_data ?? null;
        if(!$callback_data) throw new Exception("Gateway response is invalid!");

        $response_status = $callback_data->status ?? null;
        if(!$response_status) throw new Exception("Gateway response is invalid!");

        if($response_status != "ORDER_SUCCESS") {
            DB::beginTransaction();
            try{
                DB::table("temporary_datas")->where("id",$temp_data['id'])->delete();
                DB::commit();
            }catch(Exception $e) {
                DB::rollBack();
                // handle error
            }
            throw new Exception("Transaction failed or process cancel");
        }

        // If order is success
        $output['capture'] = $callback_data;
        try{
            $this->createTransaction($output);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }

        return true;
    }
}