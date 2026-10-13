<?php

return [

    /*
    | BR-SEC-14, ADR-0008: Content Security Policy with a new nonce per request.
    | On by default (servers). Local computers turn it off in .env (CSP_ENABLED=false),
    | because the Vite development server runs on another port.
    */
    'csp_enabled' => (bool) env('CSP_ENABLED', true),

];
