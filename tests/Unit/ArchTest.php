<?php

arch()
    ->expect('Mindtwo\LaravelClickUpApi')
    ->toUseStrictTypes()
    ->not()
    ->toUse(['die', 'dd', 'dump']);

arch()
    ->preset()
    ->php();

arch()
    ->preset()
    ->security()
    ->ignoring('md5');
