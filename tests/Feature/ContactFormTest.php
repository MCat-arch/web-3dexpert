<?php

it('does not expose a standalone contact form or submission endpoint', function () {
    $this->get('/kontak')->assertNotFound();
    $this->get('/contact')->assertNotFound();
    $this->post('/kontak', [])->assertNotFound();
    $this->post('/contact', [])->assertNotFound();
});
