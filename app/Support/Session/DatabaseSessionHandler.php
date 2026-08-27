<?php

namespace App\Support\Session;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Session\DatabaseSessionHandler as BaseDatabaseSessionHandler;

class DatabaseSessionHandler extends BaseDatabaseSessionHandler
{
    /** @param array<string, mixed> $payload */
    protected function addUserInformation(mixed &$payload): static
    {
        if ($this->container === null) {
            return $this;
        }

        $auth = $this->container->make(AuthFactory::class);

        $payload['admin_id'] = $auth->guard('admin')->id();
        $payload['member_id'] = $auth->guard('member')->id();

        return $this;
    }
}
