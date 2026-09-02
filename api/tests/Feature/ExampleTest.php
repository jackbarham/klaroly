<?php

test('the root route responds', function () {
    $this->get('/')->assertOk();
});
