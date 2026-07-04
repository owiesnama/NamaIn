<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case All = 'all';
    case Tenant = 'tenant';
    case Owners = 'owners';
    case TenantRole = 'tenant_role';
    case Users = 'users';
}
