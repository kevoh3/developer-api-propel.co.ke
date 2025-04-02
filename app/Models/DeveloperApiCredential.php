<?php
namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;

class DeveloperApiCredential extends Authenticatable
{


    protected $table = 'developer_api_credentials';

    protected $fillable = [
        'user_id', 'client_id', 'client_secret', 'mode', 'status',
        'wallet_account', 'controller_acc', 'ipn_url',
        'whitelist_ip_enabled', 'application_name'
    ];

    protected $hidden = [
        'client_secret',
    ];
}
