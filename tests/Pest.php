<?php

use Mindtwo\LaravelClickUpApi\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()
    ->extend(TestCase::class)
    ->group('feature')
    ->in('Feature');

pest()
    ->extend(TestCase::class)
    ->group('unit')
    ->in('Unit');

pest()
    ->extend(TestCase::class)
    ->group('vendor')
    ->in('Vendor');
