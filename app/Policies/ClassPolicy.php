<?php

namespace App\Policies;

use App\Models\ClassRoom;
use App\Models\User;

class ClassPolicy
{
    public function view(User $u, ClassRoom $c): bool
    {
        return $u->role === 'admin' || ($u->role === 'teacher' && $c->teacher_id === $u->id);
    }
}
