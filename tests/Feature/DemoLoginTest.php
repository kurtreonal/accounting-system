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
            ->assertSee('data-profile-picture-open', false)
            ->assertSee('id="profile-picture-modal"', false)
            ->assertSee('fa-solid fa-user', false)
            ->assertSee('fa-arrow-right-from-bracket', false)
            ->assertSee('id="logout-confirmation-modal"', false)
            ->assertSee(route('logout'), false);

        $this->withSession($session)->post('/logout')
            ->assertRedirect('/login')
            ->assertSessionMissing('demo_user');
    }

    public function test_profile_picture_is_validated_saved_to_json_and_synced_to_the_session(): void
    {
        $path = storage_path('framework/testing/profile-users-'.uniqid().'.json');
        $user = [
            'id' => 1,
            'employee_code' => 'EMP-001',
            'name' => 'Test Administrator',
            'email' => 'test@example.test',
            'role' => 'Administrator',
            'active' => true,
            'avatar_data_url' => null,
        ];
        file_put_contents($path, json_encode([$user], JSON_THROW_ON_ERROR));
        config(['accounting.users_path' => $path]);
        $session = ['demo_user' => $user];

        try {
            $this->withSession($session)->postJson('/profile/avatar', [
                'avatar_data_url' => $this->solidPngDataUrl(1, 1),
            ])->assertUnprocessable();

            $avatar = $this->solidPngDataUrl(256, 256);
            $this->withSession($session)->postJson('/profile/avatar', [
                'avatar_data_url' => $avatar,
            ])->assertOk()->assertJsonPath('avatar_data_url', $avatar);

            $this->assertSame($avatar, session('demo_user.avatar_data_url'));
            $stored = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($avatar, $stored[0]['avatar_data_url']);

            $this->postJson('/profile/avatar', ['avatar_data_url' => null])
                ->assertOk()->assertJsonPath('avatar_data_url', null);
            $this->assertNull(session('demo_user.avatar_data_url'));
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    private function solidPngDataUrl(int $width, int $height): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('H*', hash('crc32b', $type.$data));
        };
        $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $row = "\0".str_repeat("\x33\x66\x99", $width);
        $pixels = str_repeat($row, $height);
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', $header).$chunk('IDAT', gzcompress($pixels, 9)).$chunk('IEND', '');

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
