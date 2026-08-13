<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    public function test_it_shows_the_static_demo_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Enterprise Accounting')
            ->assertSee('autocomplete="off"', false)
            ->assertDontSee('value="admin@gmail.com"', false);
    }

    public function test_it_signs_in_with_a_user_from_the_json_fixture(): void
    {
        $this->post('/login', [
            'email' => 'admin@gmail.com',
            'password' => '123',
            'role' => 'Administrator',
        ])->assertRedirect('/dashboard');

        $this->assertSame('admin@gmail.com', session('demo_user.email'));
    }

    public function test_it_rejects_a_mismatched_demo_role(): void
    {
        $this->post('/login', [
            'email' => 'admin@gmail.com',
            'password' => '123',
            'role' => 'Viewer / Auditor',
        ])->assertSessionHasErrors('email');
    }

    public function test_authenticated_layout_has_profile_logout_controls_and_logout_invalidates_session(): void
    {
        $session = ['demo_user' => ['id' => 1, 'name' => 'Test Administrator', 'email' => 'test@example.test', 'role' => 'Administrator']];

        $this->withSession($session)->get('/dashboard')->assertOk()
            ->assertSee('data-profile-toggle', false)
            ->assertSee('id="profile-menu"', false)
            ->assertSee('fa-arrow-right-from-bracket', false)
            ->assertSee('id="logout-confirmation-modal"', false)
            ->assertSee(route('logout'), false);

        $this->withSession($session)->post('/logout')
            ->assertRedirect('/login')
            ->assertSessionMissing('demo_user');
    }
}
