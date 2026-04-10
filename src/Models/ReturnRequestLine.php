<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PictaStudio\Venditio\Models\Traits\HasHelperMethods;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ReturnRequestLine extends Model
{
    use HasFactory;
    use HasHelperMethods;
    use SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'return_request_id' => 'integer',
            'order_line_id' => 'integer',
            'qty' => 'integer',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(resolve_model('return_request'));
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(resolve_model('order_line'));
    }
}
