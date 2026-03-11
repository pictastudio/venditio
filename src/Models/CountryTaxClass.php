<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, Pivot};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CountryTaxClass extends Pivot
{
    public $incrementing = true;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(resolve_model('country'));
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(resolve_model('tax_class'));
    }
}
