<?php

namespace App\Support\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves accounts for the admin and member guards, deliberately hiding the
 * ones that were switched off.
 *
 * Every retrieval path of the Eloquent provider funnels through this query, so
 * a deactivated account is refused whether it arrives with a live session, a
 * remember-me cookie, or a fresh set of credentials.
 */
class ActiveUserProvider extends EloquentUserProvider
{
    /**
     * @param  Model|null  $model
     * @return Builder<Model>
     */
    protected function newModelQuery($model = null): Builder
    {
        return parent::newModelQuery($model)->where('is_active', true);
    }
}
