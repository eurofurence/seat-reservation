<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => fn () => $this->flash($request),
            'auth' => [
                'user' => $request->user(),
            ],
        ];
    }

    /**
     * Build the flash prop.
     *
     * @return array<string, string|null>|null
     */
    protected function flash(Request $request): ?array
    {
        $messages = array_filter([
            'success' => $request->session()->pull('success'),
            'error' => $request->session()->pull('error'),
            'warning' => $request->session()->pull('warning'),
            'info' => $request->session()->pull('info'),
        ]);

        if (empty($messages)) {
            return null;
        }

        // id is used to prevent it from showing up multiple times on reloads
        return [...$messages, 'id' => (string) Str::uuid()];
    }
}
