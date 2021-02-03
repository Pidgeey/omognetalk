<?php

namespace OmogenTalk\Lib;

use OmogenTalk\Model\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use \Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class OmogenBuilder
 *
 * @package App\LibOmogen
 */
class OmogenBuilder
{
    /** @var string Méthodes HTTP */
    const METHOD_GET = 'get',
        METHOD_PUT = 'put';

    /** @var string Formats d'échange avec Omogen */
    const FORMAT_API = 'api',
        FORMAT_PDA = 'pda';

    /** @var \OmogenTalk\Model\Model Model courant */
    protected $model;

    /** @var array Paramètres de la requête */
    protected $data;

    /** @var string Builder courant */
    protected $builder;

    /** @var string Domaine */
    protected $domain;

    /** @var string Méthode HTTP */
    protected $method;

    /** @var string Format d'échange avec Omogen */
    protected $format = self::FORMAT_API;

    /**
     * OmogenBuilder constructor.
     *
     * @param \OmogenTalk\Model\Model $model
     * @param array $data
     */
    public function __construct(Model $model, array $data = [])
    {
        $this->model = $model;
        $this->data = $data;
        $this->domain = env('OMOGEN_LINK');
    }

    /**
     * Retourne une liste de résultats
     *
     * @return array
     * @throws \Exception
     */
    public function get(): array
    {
        $result = $this->getResultsRaw();

        $collection = [];

        if (!empty($result['object']) && count($result['object']) > 0) {
            $result = array_values($result['object']);
            foreach ($result[0] as $entity) {
                if (isset($entity['classe'])) {
                    $entityType = $entity['classe'];
                    $model = Arr::get(config('model'), $entityType);
                    $convertedAttributes = $this->model->getOmogenConvertedAttributes(self::METHOD_GET, true, $entity);

                    /** @var Model $model */
                    $model = new $model($convertedAttributes);
                    // Déclare que le model est existant sur le système Omogen afin de modifier la logique d'utilisation des attributs
                    $model->declareModelIsExisting();
                    $collection[] = $model;
                }
            }
        }

        return $collection;
    }

    /**
     * Retourne une liste de résultats sous format omogen
     *
     * @return mixed
     * @throws Exception|\GuzzleHttp\Exception\GuzzleException
     */
    public function getResultsRaw()
    {
        $this->method = self::METHOD_GET;
        $this->setUrlInData();

        $result = Omogen::getObject($this->data);

        if (!isset($result['object'])) {
            throw new Exception($result['text'], (int) $result['code']);
        }

        return $result;
    }

    /**
     * Retourne tous les résultats d'une classe omogen
     *
     * @return array
     * @throws \Exception
     */
    public function all(): array
    {
        $this->method = self::METHOD_GET;
        $this->builder = "query={$this->model->getOmogenClassName()}";
        $this->data['data'] = true;

        return $this->get();
    }

    /**
     * Retourne le nombre d'éléments présents
     *
     * @return int
     * @throws \Exception
     */
    public function count()
    {
        return count($this->get());
    }

    /**
     * Ajoute une clause where
     *
     * @param string $column
     * @param string $operator
     * @param string $value
     *
     * @return $this
     */
    public function where(string $column, string $operator, string $value): self
    {
        $this->builder = "query={$this->model->getOmogenClassName()} dont le $column $operator $value";
        return $this;
    }

    /**
     * Récupère un élément selon son identifiant
     *
     * @param string $objectId
     *
     * @return \OmogenTalk\Model\Model|null
     * @throws \Exception
     */
    public function find(string $objectId): ?Model
    {
        $this->builder = "object=$objectId";
        return $this->first();
    }

    /**
     * Récupère le premier élément d'une requête get
     *
     * @return Model
     * @throws \Exception
     */
    public function first(): ?Model
    {
        $result = $this->get();
        $entity = null;

        if (count($result) > 0) {
            $entity = $result[0];
        }

        return $entity;
    }

    /**
     * Créer ou mets à jour le model courant
     *
     * @return \OmogenTalk\Model\Model|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function createOrUpdate(): ?Model
    {
        $this->method = self::METHOD_PUT;
        $this->format = self::FORMAT_PDA;

        /**
         * Cette condition permets de déterminer si le paramètre &class doit être ajouté une non dans la requête
         * Le cas classique veut que le paramètre soit présent lors d'une création mais pas lors d'une mise à jour
         * Certaines classes Omogen comme Utilisateurs ou Groupes nécessitent que le paramètre soit toujours présent
         */
        if ($this->model->hasPersistingClassParameter() || !$this->model->hasChanges()) {
            $this->data['class'] = $this->model->getOmogenClass();

        }

        // Détermine si les attributs du model sont modifiés afin de récupérer les bons attributs
        if ($this->model->hasChanges()) {
            // Détermine si il s'agit d'un update afin d'envoyer ou non l'argument class lors de la requête
            $this->data['update'] = true;
            $convertedAttributes = $this->model->getOmogenConvertedAttributes(self::METHOD_PUT, false, $this->model->getChanges());

            $this->builder = sprintf('id=%s', $this->model->getId());
        } else {
            $convertedAttributes = $this->model->getOmogenConvertedAttributes(self::METHOD_PUT);
        }

        foreach ($convertedAttributes['converted'] ?? [] as $key => $attribute) {
            if (!$this->builder) {
                $this->builder = "{$key}={$attribute}";
                continue;
            }
            $this->builder = $this->builder . "&{$key}={$attribute}";
        }
        $this->setUrlInData();

        $response = Omogen::createOrUpdateObject($this->data);

        $state = $response['status'];

        if ($state === 200) {
            if (isset($response['ignored_fields'])) {
                // Créer un message de log regroupant les champs ignoré ainsi que le type de model, id, etc..
            }
            return $this->model;

        } else {
            if ($state === 400) {
                $exception = new BadRequestHttpException($response['message']);
            } else {
                // State 500
                $exception = new Exception("An error has been occured");
            }
            // Créer un log d'erreur comprenant la classe du model, les champs etc...
            throw $exception;
        }
    }

    /**
     * Upload un document sur le model courant
     *
     * @param string $field
     * @param \Illuminate\Http\UploadedFile $file
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function uploadDocument(string $field, UploadedFile $file): array
    {
        $this->method = self::METHOD_PUT;
        $this->format = self::FORMAT_PDA;

        /**
         * class = Classe Omogen
         * id = Identifiant de la resource
         * Champ omogen = @nom du fichier
         * @nom du fichier = fichier
         *
         * @var string $key Nom du champ omogen
         * @var \Illuminate\Http\UploadedFile $file
         */
        $multipart = [
            [
                'name' => 'class',
                'contents' => $this->model->getOmogenClass(),
            ],
            [
                'name' => 'id',
                'contents' => $this->model->getId(),
            ],
            [
                'name' => $field,
                'contents' => "@{$file->getClientOriginalName()}",
            ],
            [
                'name' => "@{$file->getClientOriginalName()}",
                'contents' => stream_get_contents(fopen($file->getRealPath(), 'r')),

            ]
        ];

        $this->setUrlInData();
        $this->data['multipart'] = $multipart;

        return Omogen::uploadDocument($this->data);
    }

    /**
     * Set l'url dans le data
     *
     * @return void
     */
    protected function setUrlInData(): void
    {
        $this->data['url'] = "{$this->domain}guygle/{$this->format}/{$this->method}?{$this->builder}";
    }

    /**
     * Créer une requête query selon la clause en paramètre
     *
     * @param string $request
     *
     * @return $this
     */
    public function queryRaw(string $request): self
    {
        $this->method = self::METHOD_GET;
        $this->format = self::FORMAT_API;

        $this->builder = sprintf("query=%s %s", $this->model->getOmogenClassName(), $request);
        $this->setUrlInData();

        return $this;
    }

}
