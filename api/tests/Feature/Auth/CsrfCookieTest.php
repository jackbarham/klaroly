<?php

/**
 * The one Sanctum route the web app calls before its first POST. It has no
 * mobile twin, because a bearer-token caller has no session to protect, and
 * nothing else in the suite touches it: actingAsWebApp() asserts the stateful
 * domain and the frontend URL still agree, and this is the other half, that
 * the route those settings feed actually hands the cookie out.
 */
it('hands the web app its CSRF cookie', function () {
    $this->withHeader('Referer', config('app.frontend_url'))
        ->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});
