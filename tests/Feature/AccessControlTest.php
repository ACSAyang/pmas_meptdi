<?php

namespace Tests\Feature;

use App\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/', '/personnel', '/accountability', '/admin/users', '/reports'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_login_page_renders_and_valid_credentials_sign_in(): void
    {
        $user = $this->userWith([Role::DUTY_WATCH], $this->member());
        $this->get('/login')->assertOk()->assertSee('Sign in');
        $this->post('/login', ['login' => $user->username, 'password' => 'Secret#12345'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        $user = $this->userWith([Role::PERSONNEL], $this->member());
        $this->post('/login', ['login' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
    }

    public function test_suspended_account_cannot_sign_in(): void
    {
        $user = $this->userWith([Role::PERSONNEL], $this->member());
        $user->update(['is_active' => false]);
        $this->post('/login', ['login' => $user->username, 'password' => 'Secret#12345'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_personnel_role_cannot_reach_operational_or_admin_pages(): void
    {
        $user = $this->userWith([Role::PERSONNEL], $this->member());
        $this->actingAs($user);
        $this->get('/')->assertOk();
        $this->get('/applications')->assertOk();
        foreach (['/personnel', '/accountability', '/muster', '/movements', '/reports', '/admin/users', '/admin/audit', '/admin/settings'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_command_viewer_is_read_only(): void
    {
        $user = $this->userWith([Role::COMMAND]);
        $this->actingAs($user);
        $this->get('/accountability')->assertOk();
        $this->get('/reports/strength')->assertOk();
        $this->get('/muster')->assertForbidden();
        $this->post('/muster', [])->assertForbidden();
        $this->get('/movements/create')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_administrator_does_not_automatically_have_approval_authority(): void
    {
        $admin = $this->userWith([Role::ADMIN]);
        $this->assertFalse($admin->hasPermission('applications.act'));
        $this->actingAs($admin)->get('/approvals')->assertForbidden();
    }

    public function test_temporary_password_forces_change(): void
    {
        $user = $this->userWith([Role::PERSONNEL], $this->member());
        $user->update(['must_change_password' => true]);
        $this->actingAs($user)->get('/')->assertRedirect(route('password.change'));
    }

    public function test_restricted_contact_info_hidden_without_permission(): void
    {
        $p = $this->member();
        $p->update(['contact_number' => '09170000001']);
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        $records = $this->userWith([Role::RECORDS], $this->member());
        $this->actingAs($dow)->get('/personnel/'.$p->id)->assertOk()->assertDontSee('09170000001');
        $this->actingAs($records)->get('/personnel/'.$p->id)->assertOk()->assertSee('09170000001');
    }
}
