<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set up session data required for authentication middleware.
     * This prevents IdleSessionTimeout middleware from immediately logging out users during tests.
     */
    protected function setUpAuthenticationSession(): void
    {
        $this->session([
            'last_activity_time' => time(),
            'auth.password_confirmed_at' => time(),
        ]);
    }

    /**
     * Set up session with password confirmation for tests that require it.
     */
    protected function setUpPasswordConfirmedSession(): void
    {
        $this->session([
            'auth.password_confirmed_at' => time(),
            'last_activity_time' => time(),
        ]);
    }

    /**
     * Override actingAs to automatically set up session data.
     */
    public function actingAs($user, $guard = null)
    {
        $result = parent::actingAs($user, $guard);
        $this->setUpAuthenticationSession();
        return $result;
    }
}
