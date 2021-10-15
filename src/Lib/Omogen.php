<?php

namespace OmogenTalk\Lib;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Auth\AuthenticationException;
use OmogenTalk\Lib\Traits\Helpers as OmogenHelpers;
use \Exception;

/**
 * Class Omogen
 *
 * @package App\Lib
 */
class Omogen
{
    use OmogenHelpers;

    /** @var string Status omogen */
    const STATE_OK = "10",
        STATE_AUTH_IMPOSSIBLE = "21",
        STATE_AUTH_NEEDED = '22',
        STATE_BAD_REQUEST = "34",
        STATE_IMPOSSIBLE_ACTION = "37",
        STATE_GENERAL_ERROR = "39";

    /**
     * Récupère un objet Omogen
     *
     * @param array $data
     *
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getObject(array $data)
    {
        $cookieJar = CookieJar::fromArray(['GBSESSIONID' => $data['token']], env('OMOGEN_DOMAIN'));

        if ($data['class'] ?? null) {
            $data['url'] = sprintf("%s&class=%s", $data['url'], $data['class']);
        }

        if ($data['data'] ?? null) {
            $data['url'] = sprintf("%s&data", $data['url']);
        }

        if ($data['canonicalize'] ?? null) {
            $data['url'] = sprintf("%s&canonicalize=php", $data['url']);
        }

        if ($data['look'] ?? null) {
            $data['url'] = sprintf("%s&look=%s", $data['url'], str_replace('#', '%23', $data['look']));
        }

        $data['url'] = str_replace('#', '%23', $data['url']);

        $response = (new Client)->get($data['url'], ['cookies' => $cookieJar]);

        return self::getApiResponse($response->getBody()->getContents());
    }

    /**
     * Permets de retourner un throw si nécessaire
     *
     * @param string $jsonResponse
     * @return array
     */
    protected static function getApiResponse(string $jsonResponse): array
    {
        $data = json_decode($jsonResponse, true);

        $code = $data['code'];

        switch ($code) {
            case self::STATE_AUTH_NEEDED:
                abort(401);
                break;
            case self::STATE_BAD_REQUEST:
            case self::STATE_GENERAL_ERROR:
            case self::STATE_IMPOSSIBLE_ACTION:
                abort(400);
        }

        return $data;
    }

    /**
     * Mets un jour un object Omogen ou le créer si inexistant
     *
     * @param array $data
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function createOrUpdateObject(array $data): array
    {
        if (($data['class'] ?? null)) {
            $data['url'] = sprintf("%s&class=%s", $data['url'], $data['class']);
        }

        $data['url'] = str_replace('#', '%23', $data['url']);

        $response = (new Client)->post($data['url'], ['cookies' => self::getCookie($data['token']), 'form_params' => $data['form_data'] ?? []]);

        return self::getFormattedPdaResponse(($response->getBody())->getContents());
    }

    /**
     * Supprime un object Omogen
     *
     * @param array $data
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function deleteObject(array $data): array
    {
        $response = (new Client)->post($data['url'], ['cookies' => self::getCookie($data['token'])]);

        return self::getFormattedPdaResponse($response->getBody()->getContents());
    }

    /**
     * Effectue un upload de document
     *
     * @param array $data
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function uploadDocument(array $data): array
    {
        $options = array_merge(['cookies' => self::getCookie($data['token'])], [
            'multipart' => $data['multipart'],
            'headers' => ['Content-type' => 'multipart/form-data'],
        ]);
        $response = (new Client())->post($data['url'], $options);

        return self::getFormattedPdaResponse(($response->getBody())->getContents());
    }

    /**
     * Récupère un id pour réinitialiser un mot de passe
     *
     * @param string $email
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getResetPasswordId(string $email): array
    {
        $url = sprintf("%sguygle/pda/password?email=%s", env('OMOGEN_LINK'), $email);

        $response = (new Client())->get($url);

        return self::getFormattedPdaResponse(($response->getBody())->getContents());
    }

    /**
     * Set un nouveau mot de passe
     *
     * @param string $id
     * @param string $password
     * @return array
     */
    public static function setNewPassword(string $id, string $password): array
    {
        $url = sprintf(
            "%sguygle/pda/password?reset-id=%s&password=%s&confirm-password=%s",
            env('OMOGEN_LINK'),
            $id,
            $password,
            $password
        );

        $response = (new Client)->post($url);

        return self::getFormattedPdaResponse($response->getBody()->getContents());
    }

    /**
     * Récupère un document depuis Omogen
     *
     * @param array $data
     * @param string $storagePath
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getDocument(array $data, string $storagePath)
    {
        $cookieJar = CookieJar::fromArray(['GBSESSIONID' => $data['token']], env('OMOGEN_DOMAIN'));

        $resource = \GuzzleHttp\Psr7\Utils::tryFopen($storagePath, 'w+');

        return (new Client)->get($data['url'], ['cookies' => $cookieJar, 'sink' => $resource]);
    }

    /**
     * Récupère un fichier encodé en base64 depuis Omogen
     *
     * @param array $data
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getEncodedDocument(array $data, array $options = []): string
    {
        $cookieJar = CookieJar::fromArray(['GBSESSIONID' => $data['token']], env('OMOGEN_DOMAIN'));

        $response = (new Client)->get($data['url'], ['cookies' => $cookieJar]);

        return isset($options['binary']) ? $response->getBody()->getContents() : utf8_encode($response->getBody()->getContents());
    }

    /**
     * Retourne un token d'authentification admin
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getAdminToken(): string
    {
        $response = (new Client())->post(env('OMOGEN_LINK') . 'guygle/api/login?info', [
            'form_params' => self::prepareTokenRequest(env('OMOGEN_ADMIN_LOGIN'), env('OMOGEN_ADMIN_PASSWORD')),
        ]);
        $token = $response->getHeader('set-cookie')[0] ?? null;
        $explodedToken = explode(";", $token);
        $token = str_replace("GBSESSIONID=", "", $explodedToken[0]);
        return $token;
    }

    /**
     * Login
     *
     * @param array $credentials
     *
     * @return string
     * @throws \Illuminate\Auth\AuthenticationException|\GuzzleHttp\Exception\GuzzleException
     */
    public static function login(array $credentials): string
    {
        $login = self::prepareUserId($credentials['login']);

        $response = (new Client())->post(env('OMOGEN_LINK') . 'guygle/api/login?info', [
            'form_params' => [
                'login' => $login,
                'password' => $credentials['password'],
                'timeout' => '0'
            ]
        ]);
        $token = $response->getHeader('set-cookie')[0] ?? null;
        $explodedToken = explode(";", $token);
        $token = str_replace("GBSESSIONID=", "", $explodedToken[0]);
        $response = json_decode($response->getBody()->getContents());
        if ($response->code !== self::STATE_OK || !$token) {
            $message = sprintf("%s %s", $response->code, $response->text);
            if ($response->code === self::STATE_AUTH_IMPOSSIBLE) {
                abort(401, $message);
            } else {
                $e = new Exception($message);
            }

            throw $e;
        }

        return $token;
    }

    /**
     * Prépare un dataset pour une requête prévoyant de récupérer un token d'authentification
     *
     * @param string $login
     * @param string $password
     * @param int $timeout
     *
     * @return array
     */
    private static function prepareTokenRequest(string $login, string $password, int $timeout = 0): array
    {
        return [
            'login' => $login,
            'password' => $password,
            'timeout' => $timeout,
        ];
    }

    /**
     * Formate une réponse Omogen format pda
     *
     * @param string $pdaResponse
     *
     * @return array
     */
    private static function getFormattedPdaResponse(string $pdaResponse): array
    {
        $response = [];
        $explodeResponse = explode("\n", $pdaResponse);

        if (str_contains($explodeResponse[0], self::STATE_OK)) {
            /**
             * Response venant de createOrUpdateObject
             *
             * Index 0 -> Code reponse
             * Index 1 -> Identifiant
             * Index 2 -> string empty
             * Index 3 jusqu'à ( Index max - 1 ) -> Champs ignorés
             */
            $maxIndex = count($explodeResponse) - 1;
            $response['status'] = 200;
            $response['id'] = $explodeResponse[1];

            // NOTE: 26/05/21 Complétement de la merde mais pas vraiment possible de faire autrement en l'état
            $explodedId = explode('[ ]', $response['id']);
            if (strlen($explodedId[0]) <= 20) {
                $response['id'] = $explodedId[0];
            }

            foreach ($explodeResponse as $index => $line) {
                if ($index >= 3 && $index < $maxIndex) {
                    $response['ignored_fields'][] = $line;
                }
            }
        } elseif (str_contains($explodeResponse[0], self::STATE_AUTH_NEEDED)) {
            $response['status'] = 403;
        } elseif (str_contains($explodeResponse[0], self::STATE_GENERAL_ERROR)) {
            $response['status'] = 500;
        } elseif (str_contains($explodeResponse[0], self::STATE_IMPOSSIBLE_ACTION)) {
            $response['status'] = 400;
            $response['message'] = empty($explodeResponse[3]) ? $explodeResponse[1] : $explodeResponse[3];
        }

        return $response;
    }

    /**
     * Retourne un cookie
     *
     * @param string $token
     *
     * @return \GuzzleHttp\Cookie\CookieJar
     */
    private static function getCookie(string $token): CookieJar
    {
        return CookieJar::fromArray(['GBSESSIONID' => $token], env('OMOGEN_DOMAIN'));
    }
}
