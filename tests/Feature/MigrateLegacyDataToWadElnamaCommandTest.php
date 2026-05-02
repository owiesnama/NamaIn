<?php

test('example', function () {
    $response = $this->withoutTenantSubdomain()->get('/');

    $response->assertStatus(200);
});
