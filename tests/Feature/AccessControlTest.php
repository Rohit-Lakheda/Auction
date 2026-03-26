<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccessControlTest extends TestCase
{
    public function test_public_auth_pages_are_accessible(): void
    {
        $this->get('/login')->assertStatus(200);
        $this->get('/register')->assertStatus(200);
        $this->get('/forgot-password')->assertStatus(200);
    }

    public function test_user_routes_redirect_when_not_authenticated(): void
    {
        $this->get('/user/dashboard')->assertRedirect('/login');
        $this->get('/user/auctions')->assertRedirect('/login');
        $this->get('/user/profile')->assertRedirect('/login');
    }

    public function test_admin_routes_redirect_when_not_authenticated(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/admin/manage-users')->assertRedirect('/login');
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    public function test_admin_routes_redirect_when_non_admin_session(): void
    {
        $response = $this->withSession([
            'user_id' => 123,
            'role' => 'user',
        ])->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }
}
