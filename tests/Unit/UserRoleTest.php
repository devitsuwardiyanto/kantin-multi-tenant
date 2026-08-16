<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    public function test_has_role_true_only_when_active_and_matching(): void
    {
        $u = new User(['name' => 'A', 'email' => 'a@b.c']);
        $u->role = 'admin';
        $u->status = 'active';
        $this->assertTrue($u->hasRole('admin'));
        $this->assertTrue($u->isAdmin());
        $this->assertFalse($u->hasRole('tenant'));
        $this->assertFalse($u->isTenantOperator());
    }

    public function test_suspended_user_holds_no_role(): void
    {
        $u = new User;
        $u->role = 'admin';
        $u->status = 'suspended';
        $this->assertFalse($u->hasRole('admin'));
        $this->assertFalse($u->isActive());
    }

    public function test_null_role_holds_no_role(): void
    {
        $u = new User;
        $u->status = 'active';
        $this->assertFalse($u->hasRole('admin'));
        $this->assertFalse($u->hasRole('tenant'));
    }
}
