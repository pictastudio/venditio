<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PictaStudio\Venditio\Models\Traits\{HasAddresses, HasDiscounts, HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class User extends Authenticatable
{
    use HasAddresses;
    use HasDiscounts;
    use HasHelperMethods;
    use LogsActivity;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function carts(): HasMany
    {
        return $this->hasMany(resolve_model('cart'));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(resolve_model('order'));
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(resolve_model('return_request'));
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => (
                $this->getAttribute('first_name') . ' ' . $this->getAttribute('last_name')
            ),
        );
    }
}
