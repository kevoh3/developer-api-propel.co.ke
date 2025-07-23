<?php

namespace App\Models;

use App\Constants\GlobalConst;
use App\Constants\PaymentGatewayConst;
use App\Models\Currency;

use App\Models\DeveloperApiCredential;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Ownable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Client;

class UserWallet extends Model
{
    use HasFactory;

//    use Ownable;
    public $timestamps = true;
    protected $fillable = ['balance', 'status','user_id','currency_id','created_at','updated_at','earning_percentage_p_a','wallet_account','account_type','wallet_name'];

    protected $casts = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'currency_id'    => 'integer',
        'balance'        => 'double',
        'profit_balance' => 'double',
        'status'         => 'integer',
    ];

    public function scopeAuth($query) {
        return $query->where('user_id',auth()->user()->id);
    }

    public function scopeActive($query) {
        return $query->where("status",true);
    }

//    public function user() {
//        return $this->belongsTo(User::class);
//    }
    public function user()
    {


        return null;
    }

    public function currency() {
        return $this->belongsTo(Currency::class);
    }

//    public function scopeSender($query) {
//        return $query->whereHas('currency',function($q) {
//            $q->where("sender",GlobalConst::ACTIVE);
//        });
//    }
//    public function getStringStatusAttribute() {
//        $status = $this->status;
//        $data = [
//            'class' => "",
//            'value' => "",
//        ];
//        if($status == PaymentGatewayConst::STATUSSUCCESS) {
//            $data = [
//                'class'     => "badge badge--success",
//                'value'     => __("Active"),
//            ];
//        }else if($status == PaymentGatewayConst::STATUSPENDING) {
//            $data = [
//                'class'     => "badge badge--warning",
//                'value'     => __("Pending"),
//            ];
//        }else if($status == PaymentGatewayConst::STATUSHOLD) {
//            $data = [
//                'class'     => "badge badge--warning",
//                'value'     => __("Hold"),
//            ];
//        }else if($status == PaymentGatewayConst::STATUSREJECTED) {
//            $data = [
//                'class'     => "badge badge--danger",
//                'value'     => __("Rejected"),
//            ];
//        }else if($status == PaymentGatewayConst::STATUSWAITING) {
//            $data = [
//                'class'     => "badge badge--danger",
//                'value'     => __("Waiting"),
//            ];
//        }
//
//        return (object) $data;
//    }
//    public static function generateWalletNumner()
//    {
//        $last_wallet = WalletAccountController::orderBy('wallet_account', 'desc')->first();
//        $wallet_account = $last_wallet ? (int)$last_wallet->wallet_account + 1 : 5001;
//        WalletAccountController::create(['wallet_account'=>$wallet_account]);
//        return $wallet_account;
//    }
    public static function AddWallet($user_id, $notification_number, $wallet_name, $currency_id = 10)
    {
        try {
            return DB::transaction(function () use ($user_id, $notification_number, $wallet_name, $currency_id) {
                // Step 1: Generate unique wallet account number
                $last_wallet = WalletAccountController::orderBy('wallet_account', 'desc')->first();
                $wallet_account = $last_wallet ? ((int) $last_wallet->wallet_account + 1) : 5001;
                // Step 2: Log wallet number in tracking table
                WalletAccountController::create([
                    'wallet_account' => $wallet_account,
                ]);
                // Step 3: Insert into user wallets
                $wallet_id = self::insertGetId([
                    'user_id'             => $user_id,
                    'currency_id'         => $currency_id,
                    'balance'             => 0,
                    'status'              => true,
                    'created_at'          => now(),
                    'wallet_account'      => $wallet_account,
                    'notification_number' => $notification_number,
                    'wallet_name'         => $wallet_name,
                    'account_type'        => 'WAAS',
                ]);
                $wallet= self::with(['currency'])->find($wallet_id);
                $user = User::find($user_id);
                $controller_acc = $user_id . '_' .$wallet->wallet_account;
                $whitelist_ip_enabled=0;
                //$whitelist_ip_enabled = !empty($validatedData['ip_whitelist']);
                // Generate credentials
                $clientId = self::generate_unique_string("developer_api_credentials", "client_id", 100);
                $plainClientSecret = self::generate_unique_string("developer_api_credentials", "client_secret", 250);
                $hashedClientSecret = Hash::make($plainClientSecret);
                $ip_whitelist=null;// Store hashed
                //notification_url=
                // Store in `developer_api_credentials`
                DeveloperApiCredential::create([
                    'user_id' => $user->id,
                    'client_id' => $clientId,
                    'client_secret' => $plainClientSecret, // Store hashed
                    'notification_url' =>$user->notification_url, //$validatedData['ipn_url'],
                    'ip_whitelist' => $ip_whitelist,
                    'application_name' => $wallet_name,
                    'controller_acc' => $controller_acc,
                    'wallet_account' => $wallet->wallet_account,
                    'whitelist_ip_enabled' => $whitelist_ip_enabled,
                    'mode' => PaymentGatewayConst::ENV_PRODUCTION,
                    'status' => true,
                    'created_at' => now(),
                ]);

                // Store in Passport's `oauth_clients` for compatibility
                Client::create([
                    'id' => $clientId,
                    'user_id' => $user->id,
                    'name' =>$clientId,
                    'secret' => $hashedClientSecret, // Store hashed
                    'redirect' => '',
                    'personal_access_client' => false,
                    'password_client' => false,
                    'revoked' => false,
                    'created_at' => now(),
                ]);

                // Return credentials (only once)
//                return response()->json([
//                    'success' => true,
//                    'message' => 'Production credential created successfully!',
//                    'client_id' => $clientId,
//                    'client_secret' => $plainClientSecret // Only returned once!
//                ]);
               // return $wallet;//self::with(['currency'])->find($wallet_id);
                return [
                    'wallet' => $wallet,
                    'client_id' => $clientId,
                    'client_secret' => $plainClientSecret, // Only return once
                ];
            });
        } catch (\Exception $e) {
            Log::error("Failed to add wallet for user_id: $user_id", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to create wallet',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public static function generate_random_string($length = 12)
    {
        $characters = 'ABCDEFGHJKMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function generate_random_string_number($length = 12)
    {
        $characters = 'ABCDEFGHJKMNOPQRSTUVWXYZ123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function generate_unique_string($table, $column, $length = 10)
    {
        do {
            $generate_rand_string = self::generate_random_string_number($length);
            $unique = DB::table($table)->where($column, $generate_rand_string)->exists();
            $loop = $unique;
            $unique_string = $generate_rand_string;
        } while ($loop);

        return $unique_string;
    }
}
