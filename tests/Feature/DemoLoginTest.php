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
            ->assertDontSee('value="m.santos@nexii.ph"', false);
    }

    public function test_it_signs_in_with_a_user_from_the_json_fixture(): void
    {
        $this->post('/login', [
            'email' => 'm.santos@nexii.ph',
            'password' => 'password',
            'role' => 'Administrator',
        ])->assertRedirect('/dashboard');

        $this->assertSame('m.santos@nexii.ph', session('demo_user.email'));
    }

    public function test_it_rejects_a_mismatched_demo_role(): void
    {
        $this->post('/login', [
            'email' => 'm.santos@nexii.ph',
            'password' => 'password',
            'role' => 'Viewer / Auditor',
        ])->assertSessionHasErrors('email');
    }
}
