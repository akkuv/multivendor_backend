<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiprocketSetting extends Model
{
    protected $fillable = [
        'enabled',
        'environment',
        'base_url',
        'email',
        'password',
        'token',
        'webhook_secret',
        'default_pickup_location',
        'pickup_locations',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'password' => 'encrypted',
        'token' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'pickup_locations' => 'array',
    ];

    /**
     * The settings table is a singleton, so a freshly created model must never
     * hand out null where a boolean or a known environment is expected.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => false,
        'environment' => 'test',
        'pickup_locations' => '[]',
    ];

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function hasToken(): bool
    {
        return filled($this->token);
    }
}
