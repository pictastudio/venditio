<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreditNote extends Model
{
    use HasFactory;
    use HasHelperMethods;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime:Y-m-d H:i:s',
            'seller' => 'array',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'references' => 'array',
            'lines' => 'array',
            'totals' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(resolve_model('order'));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(resolve_model('invoice'));
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(resolve_model('return_request'));
    }
}
