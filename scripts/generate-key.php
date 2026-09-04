<?php
// Generate APP_KEY for .env — base64 32 bytes
echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;
