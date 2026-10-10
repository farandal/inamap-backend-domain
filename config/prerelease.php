<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pre-release brochure URL
    |--------------------------------------------------------------------------
    |
    | Public URL of the INAMAP brochure (S3). Included as a download link
    | in the pre-release welcome email. Leave empty while the asset is not
    | published yet: the email template omits the link when this is empty.
    |
    */

    'brochure_url' => env('PRERELEASE_BROCHURE_URL', ''),

];
