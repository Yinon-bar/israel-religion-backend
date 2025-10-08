<?php

// עם פלפל סודי גלובלי (ENV) – עדיף כבר HMAC-SHA256:
function hmac_id(string $tz): string
{
  $pepper = getenv('PEPPER_SECRET') ?: ($_SERVER['PEPPER_SECRET'] ?? null);
  $digits = str_pad(preg_replace('/\D+/', '', $tz) ?? '', 9, '0', STR_PAD_LEFT);
  return hash_hmac('sha256', $digits, $pepper); // 64 תווי hex
}
