<?php

namespace SushidevTeam\Fairu\Tests;

use SushidevTeam\Fairu\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
