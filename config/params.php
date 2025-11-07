<?php

return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'user.passwordResetTokenExpire' => 3600,
    'bccrEmail' => getenv('BCCR_EMAIL') ?: '',
    'bccrToken' => getenv('BCCR_TOKEN') ?: '',
    'bccrNombre' => getenv('BCCR_NOMBRE') ?: '',
];

