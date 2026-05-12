<?php

/**
 * Local / server secrets — not committed (see .gitignore).
 * Copy from config.local.php.example if you need a blank template.
 *
 * Razorpay below matches QRCodeApp.API appsettings.json test keys (local dev only).
 * Live: use Dashboard live keys and never commit them.
 */
return [
    'MYSQL_HOST' => 'localhost',
    'MYSQL_PORT' => '3306',
    'MYSQL_DATABASE' => 'your_database_name',
    'MYSQL_USER' => 'your_mysql_user',
    'MYSQL_PASSWORD' => 'your_mysql_password',

    'CALLMENOW_PUBLIC_BASE_URL' => 'http://localhost:4200',
    'CORS_ORIGIN' => 'http://localhost:4200',

    'RAZORPAY_KEY_ID' => '',
    'RAZORPAY_KEY_SECRET' => '',

    // Exotel — scan page masked call (fill from Exotel Dashboard → API settings)
    'EXOTEL_ACCOUNT_SID' => '',
    'EXOTEL_API_KEY' => '',
    'EXOTEL_API_TOKEN' => '',
    'EXOTEL_SUBDOMAIN' => 'api.exotel.com',
    'EXOTEL_CALLER_ID' => '',
    'EXOTEL_CALL_TYPE' => 'trans',
    'EXOTEL_DEFAULT_ISD' => '91',
    // 1 = Exotel pehle owner ko call; owner uthaye tab scanner ka phone second leg (visitor-first “callback” nahi)
    'EXOTEL_RING_OWNER_FIRST' => '1',

    // --- IVR: scan page “Open dialer — call” + 6-digit code (Gather → Connect) ---
    // MySQL (ek baar): qrcode-app-php/database/ensure_qr_stickers_ivr_access_code.sql
    // Exotel App Builder: Gather URL = …/api/exotel/ivr/gather | Connect URL = …/api/exotel/ivr/connect
    // DID: wahi ExoPhone jo inbound par ring kare (CallerId jaisa number chal sakta hai)
    'EXOTEL_IVR_DID' => '',
    'EXOTEL_IVR_WEBHOOK_SECRET' => '123456',
    // --- IVR Prompts ---
    'exotelIvrGatherPrompt' => 'Welcome to Call Me Now. Please enter the six digit access code printed on the sticker to connect to the owner.',
    'exotelIvrGatherRepeatPrompt' => 'Please enter the six digit access code.',

    // Local/deploy test payment ₹1; production par hata dena ya 299:
    'CALLMENOW_STICKER_PRICE_INR' => 1,
];
