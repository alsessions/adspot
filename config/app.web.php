<?php

use craft\ckeditor\helpers\CkeditorConfig;

return [
    'on init' => static function(): void {
        CkeditorConfig::registerFirstPartyPackage(['ImageResize']);
    },
];
