<?php

namespace App\Booking;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Backs the CSV-import propose/preview/confirm flow's server-side state (proposals, the
 * global-import queue, staged bookings) with the cache store, keyed by a token in the
 * session. This keeps the session payload small (just the token) while scoping the
 * cached state to the current admin, so concurrent imports never collide.
 *
 * The token, not session()->getId(), is the cache namespace: the session id isn't
 * guaranteed stable across login/logout, but a value round-tripped through
 * session()->put()/get() is.
 */
class ImportSessionStore
{
    private function token(): string
    {
        $token = session()->get('import_session_token');

        if ($token === null) {
            $token = (string) Str::uuid();
            session()->put('import_session_token', $token);
        }

        return $token;
    }

    private function key(string $key): string
    {
        return 'import:'.$this->token().':'.$key;
    }

    public function put(string $key, mixed $value): void
    {
        Cache::put($this->key($key), $value, now()->addMinutes((int) config('session.lifetime')));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->key($key), $default);
    }

    public function has(string $key): bool
    {
        return Cache::has($this->key($key));
    }

    public function forget(string|array $keys): void
    {
        foreach ((array) $keys as $key) {
            Cache::forget($this->key($key));
        }
    }
}
