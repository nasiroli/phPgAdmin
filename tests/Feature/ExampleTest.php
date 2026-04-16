<?php

test('the health endpoint returns a successful response', function () {
    $this->get('/up')->assertOk();
});
