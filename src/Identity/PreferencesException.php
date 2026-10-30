<?php

namespace Usersau\UsersauLaravelClient\Identity;

use RuntimeException;

class PreferencesException extends RuntimeException
{
    public int $status;
    public ?array $preferences;

    public function __construct(int $status, ?array $preferences = null)
    {
        $this->status = $status;
        $this->preferences = $preferences;
        parent::__construct($status === 409 ? 'preferences_conflict' : ($status === 401 || $status === 403 ? 'preferences_reauthorization_required' : 'preferences_unavailable'));
    }
}
