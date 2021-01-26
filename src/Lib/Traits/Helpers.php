<?php

namespace OmogenTalk\Lib\Traits;

trait Helpers
{
    /**
     * Encode en base64 l'email de l'utilisateur
     *
     * @param string $userId
     *
     * @return string
     */
    public static function prepareUserId(string $userId): string
    {
        return str_replace('=', '', strtoupper(base64_encode($userId)));
    }
}
