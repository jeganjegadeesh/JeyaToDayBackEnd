<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Shared behaviour for every master & transaction table per the project's
 * database conventions:
 *  - is_deleted flag instead of physical delete (soft delete via flag)
 *  - created_by / updated_by auto-populated from the authenticated user
 *  - company_id auto-populated & auto-scoped (multi-company support)
 *
 * Only Admin can actually delete (enforced in controllers via Policy/Gate),
 * this trait only provides the mechanics.
 */
trait HasAudit
{
    protected static function bootHasAudit(): void
    {
        // Default query scope: hide soft-deleted rows unless explicitly asked.
        static::addGlobalScope('notDeleted', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable().'.is_deleted', 0);
        });

        // Auto scope every query to the authenticated user's company,
        // unless the model is exempt (e.g. Company itself) or user is not set.
        static::addGlobalScope('company', function (Builder $builder) {
            $model = $builder->getModel();
            if (in_array('company_id', $model->getFillable()) && Auth::check() && Auth::user()->company_id) {
                $builder->where($model->getTable().'.company_id', Auth::user()->company_id);
            }
        });

        static::creating(function ($model) {
            if (Auth::check()) {
                if (in_array('created_by', $model->getFillable()) && ! $model->created_by) {
                    $model->created_by = Auth::id();
                }
                if (in_array('company_id', $model->getFillable()) && ! $model->company_id) {
                    $model->company_id = Auth::user()->company_id;
                }
            }
            if (in_array('is_deleted', $model->getFillable()) && is_null($model->is_deleted)) {
                $model->is_deleted = 0;
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && in_array('updated_by', $model->getFillable())) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /** Soft-delete by flipping the flag (used by controllers instead of ->delete()). */
    public function softDeleteFlag(): bool
    {
        $this->is_deleted = 1;
        $this->updated_by = Auth::id();

        return $this->save();
    }

    /** Restore a soft-deleted row. */
    public function restoreFlag(): bool
    {
        $this->is_deleted = 0;
        $this->updated_by = Auth::id();

        return $this->save();
    }

    /** Query scope to include deleted rows, e.g. Model::withDeletedFlag()->get() */
    public function scopeWithDeletedFlag(Builder $query): Builder
    {
        return $query->withoutGlobalScope('notDeleted');
    }

    /** Query scope for only deleted rows. */
    public function scopeOnlyDeletedFlag(Builder $query): Builder
    {
        return $query->withoutGlobalScope('notDeleted')->where('is_deleted', 1);
    }
}
