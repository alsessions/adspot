<?php

return [
    'toEmail' => Craft::$app->getProjectConfig()->get('email.fromEmail'),
    'successFlashMessage' => 'Thank you for contacting us! Our team will get in touch shortly to follow up on your message.',
    'allowedMessageFields' => ['Phone', 'Preferred Response'],
];
