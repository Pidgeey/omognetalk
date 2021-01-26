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
