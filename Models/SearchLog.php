<?php

namespace Modules\Search\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'netcore_search__search_logs';

    /**
     * Mass assignable fields.
     *
     * @var array
     */
    protected $fillable = [
        'query',
        'results_found',
        'user_id',
    ];

    /**
     * Enable timestamps.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Register model events.
     *
     * @return void
     */
    public static function boot()
    {
        // Set user_id on create.
        self::creating(function (SearchLog $searchLog) {
            $searchLog->user_id = search()->logUserId() ? auth()->id() : null;
        });

        parent::boot();
    }

    /** -------------------- Relations -------------------- **/

    /**
     * Search log entry belongs to user.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('netcore.module-admin.user.model'), 'user_id', 'id');
    }
}
