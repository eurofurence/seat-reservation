<?php

namespace Tests\Feature;

use Tests\TestCase;

class PreventStaleCacheTest extends TestCase
{
    /** @test */
    public function web_responses_include_no_store_cache_control_header()
    {
        $this->get(route('login'))
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
