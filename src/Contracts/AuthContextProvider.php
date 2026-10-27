<?php

namespace Usersau\UsersauLaravelClient\Contracts;

interface AuthContextProvider
{
    /** Return the current site's store_id, branding and links. */
    public function context(): array;
}
