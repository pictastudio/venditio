<?php

namespace PictaStudio\Venditio\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use PictaStudio\Venditio\Events\{ReturnReasonCreated, ReturnReasonDeleted, ReturnReasonUpdated};
use PictaStudio\Venditio\Models\Traits\{HasHelperMethods, LogsActivity};

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ReturnReason extends Model
{
    use HasFactory;
    use HasHelperMethods;
    use LogsActivity;
    use SoftDeletes;

    protected $dispatchesEvents = [
        'created' => ReturnReasonCreated::class,
        'updated' => ReturnReasonUpdated::class,
        'deleted' => ReturnReasonDeleted::class,
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
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(resolve_model('return_request'));
    }
}
