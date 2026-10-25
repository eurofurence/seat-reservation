<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class DevOnlyRoutesTest extends TestCase
{
    public function test_e2e_login_and_dev_login_404_outside_the_local_environment()
    {
        // This suite runs under APP_ENV=testing (phpunit.xml) — a stand-in for any
        // non-local environment, since both routes are gated on environment('local').
        $this->assertFalse(app()->environment('local'));

        $this->get('/e2e-login')->assertNotFound();
        $this->get('/dev-login')->assertNotFound();
    }
}
