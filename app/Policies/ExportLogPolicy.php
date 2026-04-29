<?php

namespace App\Policies;

use App\Models\ExportLog;
use App\Models\User;

class ExportLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExportLog $exportLog): bool
    {
        if ($exportLog->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('owner') || $user->hasRole('manager');
    }

    public function download(User $user, ExportLog $exportLog): bool
    {
        return $this->view($user, $exportLog);
    }
}
