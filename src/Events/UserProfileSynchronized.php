<?php

namespace Usersau\UsersauLaravelClient\Events;

use Usersau\UsersauLaravelClient\Identity\UserProfile;

class UserProfileSynchronized
{
    public $user;
    public UserProfile $profile;
    public string $issuer;

    public function __construct($user, UserProfile $profile, string $issuer)
    {
        $this->user = $user;
        $this->profile = $profile;
        $this->issuer = $issuer;
    }
}
