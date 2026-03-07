<?php

namespace App\Traits;

use App\Models\SystemAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Boot the Auditable trait for a model.
     *
     * @return void
     */
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            $model->logAudit('created', $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getDirty();
            $original = array_intersect_key($model->getOriginal(), $changes);
            
            // Skip logging if no actual changes occurred
            if (empty($changes)) {
                return;
            }

            $metadata = [
                'old_values' => $original,
                'new_values' => $changes,
            ];

            $model->logAudit('updated', $metadata);
        });

        static::deleted(function (Model $model) {
            $model->logAudit('deleted', $model->getAttributes());
        });
    }

    /**
     * Log the audit event.
     *
     * @param string $action
     * @param array $metadata
     * @return void
     */
    protected function logAudit(string $action, array $metadata = [])
    {
        // Add common metadata
        $metadata['ip_address'] = request()->ip();
        $metadata['user_agent'] = request()->userAgent();
        
        // Try to get facility_id context if available
        if (Auth::check() && !empty(Auth::user()->facility_id)) {
            $metadata['facility_id'] = Auth::user()->facility_id;
        } elseif (method_exists($this, 'getAttribute') && $this->getAttribute('facility_id')) {
             $metadata['facility_id'] = $this->getAttribute('facility_id');
        }

        SystemAudit::create([
            'user_id' => Auth::id(), // Nullable if action performed by system
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Relationship to retrieve audits.
     */
    public function audits()
    {
        return $this->morphMany(SystemAudit::class, 'auditable')->latest();
    }
}
