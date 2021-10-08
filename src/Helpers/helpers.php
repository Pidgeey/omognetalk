<?php

use OmogenTalk\Lib\Omogen;

/**
 * Retourne une réponse au format json
 *
 * @param $payload
 * @param int|null $status
 *
 * @return \Illuminate\Http\Response
 */
function jsonResponse($payload, ?int $status = null): \Illuminate\Http\Response
{
    if ($status) {
        return response($payload, $status)->header('Content-Type', 'application/json');
    }

    $result = [];
    $status = 200;

    if ((isset($payload['object']) && empty($payload))) {
        $result['message'] = 'Aucune resource';
        $status = 404;
    } elseif (isset($payload) && (count($payload) > 0)) {
        $result['data'] = $payload;
    } elseif ($payload['code'] === Omogen::STATE_BAD_REQUEST) {
        $result['message'] = $payload['text'];
        $status = 400;
    }

    return response($result, $status)->header('Content-Type', 'application/json');
}

/**
 * Retourne l'entier du status correspondant à une erreur omogen
 *
 * @param $error
 *
 * @return int
 */
function getErrorCode($error): int
{
    switch ($error) {
        case Omogen::STATE_OK:
            return 200;
        case Omogen::STATE_AUTH_IMPOSSIBLE:
            return 403;
        case Omogen::STATE_AUTH_NEEDED:
            return 401;
        case Omogen::STATE_BAD_REQUEST:
        case Omogen::STATE_IMPOSSIBLE_ACTION:
            return 400;
        case Omogen::STATE_GENERAL_ERROR:
        default:
            return 500;
    }
}
