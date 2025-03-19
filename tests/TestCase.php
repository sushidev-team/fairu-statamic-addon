<?php

namespace Sushidev\Fairu\Tests;

use Sushidev\Fairu\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
