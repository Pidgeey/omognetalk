<?php

namespace OmogenTalk\Lib;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Http;
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
        STATE_AUTH_REQUESTED = "32",
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

        // Permets d'ajoute le &class lorsque la configuration omogen le requiers
        if ($data['class'] ?? null) {
            $data['url'] = sprintf("%s&class=%s", $data['url'], $data['class']);
        }
        // Permets de récupérer la data lié à l'objet. Si data est false, seulement les ID seront retournés
        if ($data['data'] ?? null) {
            $data['url'] = sprintf("%s&data", $data['url']);
        }
        // Permets de récupérer les champs de manière normalisé (espaces remplacés par underscores et accents retirés)
        if ($data['canonicalize'] ?? null) {
            $data['url'] = sprintf("%s&canonicalize=php", $data['url']);
        }
        // Fonctionne avec la méthode with() du builder. Permets les jonctions entre les classes
        if ($data['look'] ?? null) {
            $data['url'] = sprintf("%s&look=%s", $data['url'], str_replace('#', '%23', $data['look']));
        }
        // Formate l'url.
        $data['url'] = str_replace('#', '%23', $data['url']);

        $response = (new Client)->get($data['url'], ['cookies' => $cookieJar]);

        return self::getApiResponse($response->getBody()->getContents());
    }

    /**
     * Permets de retourner un throw si nécessaire
     *
     * @note: Cette méthode à pour but premier de throw au plus tôt dans les cas ou Omogen retourne une erreur
     * et ainsi éviter de passer sur tout le traitement de la donnée par la suite
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
                abort(401, sprintf("%s %s", $data['code'] ?? "", $data['text'] ?? ""));
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
        // Permets d'ajoute le &class lorsque la configuration omogen le requiers
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
     * @note Cette méthode prends en paramètre l'email d'un utilisateur. Ensuite, il renvoi un id en réponse. Cet ID
     * devra être passé en paramètre pour la renitialisation du mot de passe qui est, en toute logique, la prochaine
     * étape.
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
     * @note Prends en paramètre l'ID récupérer lors de la requête précédente ainsi que le nouveau password.
     * Omogen va vérifier si l'id corresponds et changera le mot de passe associé à l'utilisateur.
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
     * @note Cette méthode à pour but de récupérer un token utilisateur. Il va donc créer une session avec Omogen
     * puis ensuite la méthode récupère le token dans le header de la réponse, le traite puis le retourne
     * En général c'est ce token que nous allons stoker en front et réutiliser dans les requêtes vers omogen
     *
     * @param array $credentials
     *
     * @return Object
     * @throws \Illuminate\Auth\AuthenticationException|\GuzzleHttp\Exception\GuzzleException
     */
    public static function login(array $credentials): array
    {
        $login = $credentials['login'];

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

        $response->token = $token;
        return (array)$response;
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
     * @note Cette méthode est appellé lors des requêtes formattés en PDA (le plus souvent les méthodes create/update)
     * Etant donné la purge qu'est Omogen, toute la logique qui constitue cette méthode est uniquement basé sur le format
     * que renvoi Omogen. La plupart du temps, celui ne change pas, mais impossible d'être sur à tous les coups..
     * Donc, à manipuler avec précaution et ne pas hésiter à débugger dans cette méthode si les retours attendus ne sont
     * pas corrects
     *
     * @param string $pdaResponse
     *
     * @return array
     */
    private static function getFormattedPdaResponse(string $pdaResponse): array
    {
        $response = [];
        // Les retours PDA de Omogen en texte contiennent des retours à la ligne. On va donc cherche dans un premier temps
        // à découper le retour Omogen dans un tableau afin de travailler avec indexes
        $explodeResponse = explode("\n", $pdaResponse);

        // Le premier index est toujours le code retour Omogen, je vais donc m'en servir pour déterminer si le retour
        // de la requête contient une erreur
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
            // Ici je récupère l'id de l'entité pour l'utiliser par la suite
            $explodedId = explode('[ ]', $response['id']);
            if (strlen($explodedId[0]) <= 20) {
                $response['id'] = $explodedId[0];
            }
            // Ici, dans la plupart des cas, on récupère les champs qui ont étés ignorés par Omogen.
            // Cela permets éventuellement de débugger et de comprendre pourquoi certains champs ne se sont pas mis à jour
            // sur omogen.
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
        } elseif (str_contains($explodeResponse[0], self::STATE_AUTH_REQUESTED)) {
            $response['status'] = 403;
            $response['message'] = $pdaResponse;
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
