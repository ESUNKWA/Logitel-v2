<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // « Prise de service » is mandatory in the application (config hotel.duty_required); tests
        // not about it act as receptionists without taking the service first. DutyTest turns it on.
        config(['hotel.duty_required' => false]);
    }
}
