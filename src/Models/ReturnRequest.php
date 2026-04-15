<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
use PictaStudio\Venditio\Events\{ReturnRequestCreated, ReturnRequestDeleted, ReturnRequestUpdated};
use PictaStudio\Venditio\Models\Traits\{HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ReturnRequest extends Model
{
    use HasFactory;
    use HasHelperMethods;
    use LogsActivity;
    use SoftDeletes;

    protected $dispatchesEvents = [
        'created' => ReturnRequestCreated::class,
        'updated' => ReturnRequestUpdated::class,
        'deleted' => ReturnRequestDeleted::class,
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'user_id' => 'integer',
            'return_reason_id' => 'integer',
            'billing_address' => 'json',
            'is_accepted' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(resolve_model('order'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(resolve_model('user'));
    }

    public function returnReason(): BelongsTo
    {
        return $this->belongsTo(resolve_model('return_reason'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(resolve_model('return_request_line'));
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(resolve_model('credit_note'));
    }
}
