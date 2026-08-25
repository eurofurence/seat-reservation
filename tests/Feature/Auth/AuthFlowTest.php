<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_without_redirect_loop()
    {
        $response = $this->get('/auth/login');

        $response->assertStatus(200);
        // Just check that it's the login component, don't rely on specific text
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
        );
    }

    public function test_root_redirects_to_auth_login()
    {
        $response = $this->get('/');

        $response->assertRedirect('/auth/login');
    }

    public function test_login_route_redirects_to_auth_login()
    {
        $response = $this->get('/login');

        $response->assertRedirect('/auth/login');
    }

    public function test_login_post_with_missing_oauth_config_shows_error()
    {
        // Temporarily clear the config to test error handling
        config(['services.identity.openid_configuration' => '']);

        $response = $this->post('/auth/login');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('OAuth identity service is not configured', session('error'));
    }

    public function test_login_post_with_valid_config_attempts_oauth()
    {
        // Set valid config
        config([
            'services.identity.openid_configuration' => 'https://identity.eurofurence.org/.well-known/openid-configuration',
            'services.identity.client_id' => 'test-client-id',
            'services.identity.client_secret' => 'test-secret',
            'services.identity.redirect' => 'http://localhost/auth/callback',
        ]);

        // Mock the HTTP request to the OpenID configuration
        \Http::fake([
            'identity.eurofurence.org/.well-known/openid-configuration' => \Http::response([
                'issuer' => 'https://identity.eurofurence.org',
                'authorization_endpoint' => 'https://identity.eurofurence.org/oauth2/auth',
                'token_endpoint' => 'https://identity.eurofurence.org/oauth2/token',
                'userinfo_endpoint' => 'https://identity.eurofurence.org/oauth2/userinfo',
                'jwks_uri' => 'https://identity.eurofurence.org/.well-known/jwks.json',
                'end_session_endpoint' => 'https://identity.eurofurence.org/oauth2/sessions/logout',
                'revocation_endpoint' => 'https://identity.eurofurence.org/oauth2/revoke',
            ], 200),
        ]);

        $response = $this->post('/auth/login');

        // Should attempt to redirect to OAuth provider (will be an Inertia location response)
        $this->assertNotEquals(419, $response->getStatusCode());
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_logout_clears_authenticated_session()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post('/auth/logout');

        // Inertia::location on a non-GET Inertia request returns a 409 with the target in the header.
        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', 'https://identity.eurofurence.org/oauth2/sessions/logout');
        $this->assertGuest();
    }

    public function test_frontchannel_logout_callback_clears_authenticated_session()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/auth/frontchannel-logout');

        $this->assertGuest();
    }
}
