<?php

namespace App\Traits\PaymentGateway;

use Exception;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use App\Http\Helpers\Api\Helpers;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Models\Admin\PaymentGateway as PaymentGatewayModel;
use Illuminate\Support\Facades\Log;

trait PaystackTrait
{
    public function paystackInit($output = null) {  
        if(!$output) $output = $this->output;
        $credentials = $this->getPaystackCredentials($output);
        return  $this->setupPaystackInitAddMoney($output,$credentials);
    }
    public function setupPaystackInitAddMoney($output,$credentials){
        $amount = $output['amount']->total_amount ? number_format($output['amount']->total_amount,2,'.','') : 0;
        $currency = $output['currency']['currency_code']??"USD";
        if(auth()->guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
            $user_email = $user->email;
        }
        $temp_record_token = generate_unique_string('temporary_datas', 'identifier', 60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel
        $redirection = $this->getRedirection();
        $url_parameter = $this->getUrlParams();
        $url = "https://api.paystack.co/transaction/initialize";

        $fields             = [
            'email'         => $user_email,
            'amount'        => get_amount($amount, null, 2) * 100,
            'currency'        => $currency,
            'callback_url'  => $this->setGatewayRoute($redirection['return_url'], PaymentGatewayConst::PAYSTACK, $url_parameter),
            'reference'     => $temp_record_token,
        ];

        $fields_string = http_build_query($fields);
        //open connection
        $ch = curl_init();
        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $credentials->secret_key",
            "Cache-Control: no-cache",
        ));
        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        //execute post
        $result = curl_exec($ch);
        $response   = json_decode($result);
        if($response->status == true) {
            $this->paystackJunkInsert($response,$temp_record_token,$credentials);
            if(request()->expectsJson()) { 
                $this->output['redirection_response']   = $response;
                $this->output['redirect_url']           = $response->data->authorization_url;
                $this->output['redirect_links']         = $response->data->authorization_url;
                return $this->get(); 
            }
            return redirect($response->data->authorization_url)->with('output',$output);
        }else{
            throw new Exception($response->message??" "."Something Is Wrong, Please Contact With Owner");
        } 
    }
    public function getPaystackCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception(__("Payment gateway not available"));
        $public_key_sample = ['public_key','Public Key','public-key'];
        $secret_key_sample = ['secret_key','Secret Key','secret-key'];

        $public_key = '';
        $outer_break = false;
        foreach($public_key_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->paystackPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->paystackPlainText($label);

                if($label == $modify_item) {
                    $public_key = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        } 
        $secret_key = '';
        $outer_break = false;
        foreach($secret_key_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->paystackPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->paystackPlainText($label);

                if($label == $modify_item) {
                    $secret_key = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        } 
        $mode = $gateway->env; 
        $paypal_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "live",
        ];
        if(array_key_exists($mode,$paypal_register_mode)) {
            $mode = $paypal_register_mode[$mode];
        }else {
            $mode = "sandbox";
        } 
        return (object) [
            'public_key'     => $public_key,
            'secret_key' => $secret_key,
            'mode'          => $mode, 
        ]; 
    }

    public function paystackPlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }

    public function paystackJunkInsert($response,$temp_record_token,$credentials) {
        $output = $this->output; 
        $data = [
            'gateway'       => $output['gateway']->id,
            'currency'      => $output['currency']->id,
            'amount'        => json_decode(json_encode($output['amount']),true),
            'response'      => $response,
            'credentials'   => $credentials,
            'wallet_table'  => $output['wallet']->getTable(),
            'wallet_id'     => $output['wallet']->id,
            'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard' => get_auth_guard(),
        ];
        return TemporaryData::create([
            'type'          => PaymentGatewayConst::TYPEADDMONEY,
            'identifier'    => $temp_record_token,
            'data'          => $data,
        ]);
    }

    public function paystackSuccess($output = null) {
        if(!$output) $output = $this->output;
        $token = $this->output['tempData']['identifier'] ?? "";
        if(empty($token)) throw new Exception(__("Transaction Failed. The record didn't save properly. Please try again"));
        $response = $output['tempData'];
        return $this->createTransactionPaystack($response,$output);
    }

    public function createTransactionPaystack($response,$output) {
        // payment successfully captured record saved to database
        $output['capture'] = $response;
        try{
            $this->createTransaction($output);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
        return true;
    }
    public function isPayStack($gateway)
    {
        $search_keyword = ['Paystack','paystack','payStack','pay-stack','paystack gateway', 'paystack payment gateway'];
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
    /**
     * paystack webhook response
     * @param array $response_data
     * @param \App\Models\Admin\PaymentGateway $gateway
     */
    public function paystackCallbackResponse(array $response_data, $gateway)
    { 
        try{
            $output = $this->output;
            $event_type = $response_data['event'] ?? "";

            if ($event_type == "charge.success") {
                $reference = $response_data['data']['reference'];
                // temp data
                $temp_data = TemporaryData::where('identifier', $reference)->first();
                // if transaction is already exists need to update status, balance & response data
                $transaction = Transaction::where('callback_ref', $reference)->first();
            
                $status = PaymentGatewayConst::STATUSSUCCESS;

                if ($temp_data) {
                    $gateway_currency_id = $temp_data->data->currency ?? null;
                    $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
                    if ($gateway_currency) {

                        $requested_amount = $temp_data['data']->amount->requested_amount ?? 0;
                        $validator_data = [
                            $this->currency_input_name  => $gateway_currency->alias,
                            $this->amount_input         => $requested_amount,
                            $this->wallet_currency_input => $temp_data['data']->amount->receiver_cur_code,
                        ];

                        $get_wallet_model = PaymentGatewayConst::registerWallet()[$temp_data->data->creator_guard];
                        $user_wallet = $get_wallet_model::find($temp_data->data->wallet_id);
                        $this->predefined_user_wallet = $user_wallet;
                        $this->predefined_guard = $user_wallet->user->modelGuardName();
                        $this->predefined_user = $user_wallet->user;

                        $this->output['tempData'] = $temp_data;
                    }

                    $this->request_data = $validator_data;
                    $this->gateway();
                }

                $output                     = $this->output;
                $output['callback_ref']     = $reference;
                $output['capture']          = $response_data;

                if ($transaction && $transaction->status != PaymentGatewayConst::STATUSSUCCESS) {

                    $update_data                        = json_decode(json_encode($transaction->details), true);
                    $update_data['gateway_response']    = $response_data;

                    // update information
                    $transaction->update([
                        'status'    => $status,
                        'details'   => $update_data
                    ]);
                    // update balance
                    $this->updateWalletBalance($output);
                }
                if(!$transaction) {
                    // create new transaction with success
                    $this->createTransaction($output, $status);
                }
            }
        }catch(Exception $e) {
            Log::info($e);
        }
    }
}
