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

        if ($data['depth'] ?? null) {
            $data['url'] = sprintf("%s&depth=%s", $data['url'], $data['depth']);
        }

        $response = (new Client)->post($data['url'], ['cookies' => $cookieJar]);

        return json_decode(($response->getBody())->getContents(), true);
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
        if (($data['class'] ?? null) && (!isset($data['update']))) {
            $data['url'] = sprintf("%s&class=%s", $data['url'], $data['class']);
        }

        $response = (new Client)->post($data['url'], ['cookies' => self::getCookie($data['token'])]);

        return self::getFormattedPdaResponse(($response->getBody())->getContents());
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
     * Retourne un token d'authentification admin
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getAdminToken(): string
    {
        $response = (new Client())->post(env('OMOGEN_LINK') . '/guygle/api/login?info', [
           'form_params' => self::prepareTokenRequest(env('OMOGEN_ADMIN_LOGIN'), env('OMOGEN_ADMIN_PASSWORD')),
        ]);
        $response = json_decode($response->getBody()->getContents());
        return $response->token;
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

        $response = (new Client())->post(env('OMOGEN_LINK') . '/guygle/api/login?info', [
            'form_params' => [
                'login' => $login,
                'password' => $credentials['password'],
                'timeout' => '0'
            ]
        ]);
        $response = json_decode($response->getBody()->getContents());

        if ($response->code !== self::STATE_OK || !isset($response->token)) {
            $message = sprintf("%s %s", $response->code, $response->text);
            if ($response->code === self::STATE_AUTH_IMPOSSIBLE) {
                $e = new AuthenticationException($message);
            } else {
                $e = new Exception($message);
            }

            throw $e;
        }

        return $response->token;
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

        if (strpos($explodeResponse[0], self::STATE_OK) !== false) {

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
            foreach ($explodeResponse as $index => $line) {
                if ($index >= 3 && $index < $maxIndex) {
                    $response['ignored_fields'][] = $line;
                }
            }
        } elseif (strpos($explodeResponse[0], self::STATE_AUTH_NEEDED) !== false) {
            $response['status'] = 403;
        } elseif (strpos($explodeResponse[0], self::STATE_GENERAL_ERROR) !== false) {
            $response['status'] = 500;
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
