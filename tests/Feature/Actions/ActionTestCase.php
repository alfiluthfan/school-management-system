<?php

namespace Tests\Feature\Actions;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\BuildsActionFixtures;
use Tests\TestCase;

abstract class ActionTestCase extends TestCase
{
    use BuildsActionFixtures;
    use DatabaseMigrations;
}
