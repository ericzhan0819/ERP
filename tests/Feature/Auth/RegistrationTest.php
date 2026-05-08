<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('new users can not register', function () {
    $response = $this->post('/register', [
        'username' => 'testuser',
        'phone' => '0912345678',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertNotFound();
});
