<?php

it('returns a successful response', function () {
    expect($this->app->runningInConsole())
        ->toBeTrue();
});
