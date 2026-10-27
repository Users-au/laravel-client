<?php

namespace Usersau\UsersauLaravelClient\Contracts;

interface AuthContextProvider
{
    /** Return an application-defined context_id, branding and links. */
    public function context(): array;
}
