<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use PictaStudio\Venditio\Models\Traits\{HasDefault, HasHelperMethods};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class Currency extends Model
{
    use HasDefault;
    use HasFactory;
    use HasHelperMethods;
    use SoftDeletes;

    protected static bool $syncingDefaultCurrency = false;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $currency): void {
            if (!$currency->is_default && !static::query()->where('is_default', true)->exists()) {
                $currency->is_default = true;
            }
        });

        static::saved(function (self $currency): void {
            static::syncDefaultCurrency($currency);
        });

        static::deleted(function (self $currency): void {
            if (!$currency->getOriginal('is_default')) {
                return;
            }

            static::ensureOneDefaultCurrency();
        });
    }

    private static function syncDefaultCurrency(self $currency): void
    {
        if (static::$syncingDefaultCurrency) {
            return;
        }

        static::$syncingDefaultCurrency = true;

        try {
            if ($currency->is_default) {
                static::query()
                    ->whereKeyNot($currency->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);

                return;
            }

            static::ensureOneDefaultCurrency($currency);
        } finally {
            static::$syncingDefaultCurrency = false;
        }
    }

    private static function ensureOneDefaultCurrency(?self $preferredCurrency = null): void
    {
        if (static::query()->where('is_default', true)->exists()) {
            return;
        }

        $currencyId = $preferredCurrency?->getKey()
            ?? static::query()->value('id');

        if (!$currencyId) {
            return;
        }

        static::query()
            ->whereKey($currencyId)
            ->update(['is_default' => true]);
    }

    public function countries(): HasMany
    {
        return $this->hasMany(resolve_model('country'));
    }
}
