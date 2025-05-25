<?php

return [
    /*
|--------------------------------------------------------------------------
| Log Viewer route middleware.
|--------------------------------------------------------------------------
| The middleware should enable session and cookies support in order for the Log Viewer to work.
| The 'web' middleware will be applied automatically if empty.
|
*/
    'require_auth_in_production' => false,
    'middleware' => ['web', 'auth'],
];
