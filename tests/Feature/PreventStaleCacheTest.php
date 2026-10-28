<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreventStaleCacheTest extends TestCase
{
    #[Test]
    public function web_responses_include_no_store_cache_control_header()
    {
        $this->get(route('login'))
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
