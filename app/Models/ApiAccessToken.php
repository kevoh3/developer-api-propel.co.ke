<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiAccessToken extends Model
{
use HasFactory;

protected $fillable = [
'developer_api_credential_id',
'access_token',
'refresh_token',
'expires_at',
];

protected $casts = [
'expires_at' => 'datetime',
];

public function developerCredential()
{
return $this->belongsTo(DeveloperApiCredential::class);
}
}
