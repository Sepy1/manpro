<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpnGroup extends Model
{
    protected $fillable = ['name', 'description', 'allowed_destinations', 'access_schedule'];

    protected function casts(): array
    {
        return ['allowed_destinations' => 'array', 'access_schedule' => 'array'];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(VpnClient::class);
    }
}
