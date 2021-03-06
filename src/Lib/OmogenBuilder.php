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
        METHOD_PUT = 'put',
        METHOD_DOC = 'doc',
        METHOD_DELETE = "delete",
        METHOD_STOCK = "stock",
        METHOD_CLICK = "click";

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

    /** @var bool Permets de déterminer s'il est nécessaire de rajouter l'id dans le builder */
    protected $needIdOnRequest = true;

    protected $formData = [];

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
     * @note Cette méthode est la méthode principale appelé dans tous les cas de getters sur ce builder.
     * Sa fonction principale et de formatter les données de retour.
     * Elle va dans un premier temps, récupérer les données de Omogen puis les traiter pour les rendre compliant avec le
     * reste de l'application:
     * - Création des models dynamique
     * - Conversion des champs Omogen
     * - Cast des attributs
     *
     * @return array
     * @throws \Exception
     */
    public function get(): array
    {
        // On récupère le resultat brut venant d'omogen
        $result = $this->getResultsRaw();

        // Something went wrong, inform client (throw HttpResponseException)
        if (isset($result['status']) && $result['status'] != 200)
            abort(response()->json($result, getErrorCode($result['status'])));
        if (isset($result['code']) && $result['code'] != Omogen::STATE_OK)
            abort(response()->json($result, getErrorCode($result['code'])));

        $collection = [];

        // on s'assure qu'un résultat est présent
        if (!empty($result['object']) && count($result['object']) > 0) {
            $result = array_values($result['object']);
            // Si un résultat est obtenu
            if (count($result[0]) > 0) {
                // Si le data n'est pas requis, on retourne la liste des identifiants
                if (!isset($this->data['data']) || (isset($this->data['data']) && !$this->data['data'])) {
                    return $result[0];
                }
                foreach ($result[0] as $entity) {
                    if (isset($entity['classe'])) {
                        $entityType = $entity['classe'];
                        $model = Arr::get(config('model'), $entityType);
                        // C'est ici que l'on convertit les attributs venant d'omogen qui en ont besoin depuis le model
                        $convertedAttributes = $this->model->getOmogenConvertedAttributes(self::METHOD_GET, true, $entity);

                        // Permets de cast les attributs avec plus de conformité
                        $this->castAttributes($convertedAttributes);

                        /** @var Model $model */
                        $model = new $this->model($convertedAttributes);
                        // Déclare que le model est existant sur le système Omogen afin de modifier la logique d'utilisation des attributs
                        $model->declareModelIsExisting();
                        $collection[] = $model;
                    }
                }
            }
        }

        return $collection;
    }

    /**
     * Cast un tableau objet lié en model
     *
     * @note Il s'agit de la méthode qui cast les objets Omogen en model.
     * Dans un premier temps, il ne faudra pas oublier de déclarer le nom de la classe dans le fichier config/model.php
     * Ensuite, cette méthode va tout simplement créer un nouveau model en fonction de la classe de l'objet Omogen
     *
     * @param $key
     * @param $value
     * @param $arrayValues
     *
     * @return Model|null
     */
    private function castObject($key, $value, $arrayValues)
    {
        $model = null;

        if ($key === 'classe') {
            $modelClass = Arr::get(config('model'), $value);
            if ($modelClass) {
                $model = new $modelClass;
                $convertedAttributes = $model->getOmogenConvertedAttributes(self::METHOD_GET, true, $arrayValues);

                $model = new $model($convertedAttributes);
                // Si le model est déclaré, on remplace ce tableau d'attribut par une classe de ce model

            }
        }
        return $model;
    }

    /**
     * Boucle sur les attributs afin de cast en model les champs comprenant des objets
     *
     * @note Cette méthode permets de caster les objets lié. Si ce sont des classes, et que ces classes sont déclarés
     * fans la configuration config/model.php, ces objets seront caster en model
     *
     * @param $attributes
     * @param $key
     * @param $value
     */
    private function findObject(&$attributes, $key, $value)
    {
        if (is_array($value)) {
            foreach ($value as $firstKeys => $arrayValues) {
                if (is_array($arrayValues)) {
                    foreach ($arrayValues as $attributeKey => $attribute) {
                        $model = $this->castObject($attributeKey, $attribute, $arrayValues);
                        if ($model) {
                            $attributes[$key][$firstKeys] = $model;
                            $this->findObject($model, $key, $model);
                        }
                    }
                }
            }
        }

        if (is_object($attributes)) {
            foreach ($attributes->getAttributes() as $objectClass => $arrayValues) {
                if (is_array($arrayValues)) {
                    foreach ($arrayValues as $b) {
                        if (is_array($b)) {
                            foreach ($b as $attributeKey => $attribute) {
                                $model = $this->castObject($attributeKey, $attribute, $b);
                                if ($model) {
                                    $attributes->{$objectClass} = $model;
                                    $this->findObject($model, $key, $model);
                                }
                            }
                        }
                    }

                }
            }
        }
    }

    /**
     * Cast attributes
     *
     * @note C'est dans cette méthode que l'on cast les attributs venant d'omogen.
     * Exemple: les booléens - Oui -> true
     *
     * @param array $attributes
     */
    private function castAttributes(array &$attributes)
    {
        foreach ($attributes as $key => $value) {

            /**
             * Cast des objets lié en classe
             *
             * Le but de cet algo est de vérifier s'il existe des objets liés dans dans le retour des données Omogen
             *
             * On boucle sur les différents tableau sur deux niveaux, si un attribut classe est présent dans le second niveau
             * cela veut donc dire que c'est un objet lié. On va donc vérifier si le model est déclaré dans l'application courante
             * S'il existe, on va créer un nouveau model en injectant les attributs
             *
             * TODO: Pour optimiser le code, potentiellement voir uniquement les attributs qui sont précisé dans le paramètre
             * de la méthode with($relation). Optimiser en bouclant uniquement sur les champs précisé en paramètre
             */

            $this->findObject($attributes, $key, $value);
        }
    }

    /**
     * Retourne une liste de résultats sous format omogen
     *
     * @note Méthode qui est toujours à la nature de la réception des données venant d'omogen lors des requêtes GET
     *
     * @return mixed
     * @throws Exception|\GuzzleHttp\Exception\GuzzleException
     */
    public function getResultsRaw()
    {
        $this->method = self::METHOD_GET;
        $this->setUrlInData();

        if (!isset($this->data['canonicalize'])) {
            $this->data['canonicalize'] = true;
        }

        return Omogen::getObject($this->data);
    }

    /**
     * Récupère une liste d'objets à partir d'un tableau d'identifiants
     *
     * @note Il s'agit de la méthode qu'il est nécessaire d'appeller lorsque il est nécessaire d'effectuer une requête
     * omogen many
     *
     * @param array $objectIds
     *
     * @return array
     * @throws \Exception
     */
    public function many(array $objectIds): array
    {
        $this->method = self::METHOD_GET;
        $this->builder = "many=";

        foreach ($objectIds as $id) {
            $this->builder = sprintf("%s %s", $this->builder, $id);
        }

        return $this->get();
    }

    public function getStock(string $objectId)
    {
        $this->method = self::METHOD_STOCK;
        $this->builder = "s={$objectId}";

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
     * @note Actuellement à l'état de prototype. S'il est nécessaire d'effectuer une clause where, se tourner vers
     * la méthoe queryRaw()
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
     * @note Principale méthode utilisée pour récupérer une entité via son ID
     *
     * @param string $objectId
     *
     * @return \OmogenTalk\Model\Model|null
     * @throws \Exception
     */
    public function find(string $objectId): ?Model
    {
        $this->builder = "id=$objectId";

        $this->needIdOnRequest = false;

        if ($this->model->hasPersistingClassParameter()) {
            $this->data['class'] = $this->model->getOmogenClass();
        }

        return $this->first();
    }

    /**
     * Récupère un document depuis Omogen
     *
     * @param string $query
     * @param string $path
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDocument(string $query, string $path)
    {
        $this->method = self::METHOD_DOC;
        $this->builder = "path={$query}";
        $this->data['data'] = false;
        $this->setUrlInData();

        return Omogen::getDocument($this->data, $path);
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

            // NOTE: Evite le double ID dans le cas USER
            $this->needIdOnRequest = false;
        } else {
            $convertedAttributes = $this->model->getOmogenConvertedAttributes(self::METHOD_PUT);
        }

        foreach ($convertedAttributes['converted'] ?? [] as $key => $attribute) {

            // Traite le cas ou l'attribut est un tableau
            if (is_array($attribute)) {
                $arrayAttribute = $attribute;
                $attribute = '';
                foreach ($arrayAttribute as $index => $value) {
                    if ($index === 0) {
                        $attribute = $value;
                        continue;
                    }
                    $attribute = $attribute . ' ' . $value;
                }
                $attribute = sprintf("%s", $attribute);

            }

            if (is_bool($attribute)) {
                $attribute ? $attribute = "Oui" : $attribute = "Non";
            }

            /**
             * Note 16/03/21: Essai d'envoyer les fichiers si fichiers présent dans le tableau d'attributs
             * Résultat; pas de mise en place car utilité de la méthode uploadDocument qui mets à jour les documents
             * PUIS mets à jour le form_data.
             * Problème: Si un soucis surviens lors de l'update du form_data, les documents seront mis à jour mais pas
             * les données du form_data. On ne veux pas qu'une partie des données ( documents ) soient à jour et pas le reste
             */

            $this->data['form_data'][$key] = $attribute;
        }

        $this->setUrlInData();

        $response = Omogen::createOrUpdateObject($this->data);

        $state = $response['status'];

        if ($state === 200) {
            if (isset($response['ignored_fields'])) {
                // Créer un message de log regroupant les champs ignoré ainsi que le type de model, id, etc..
            }
            $this->model->setId($response['id'] ?? null);
            return $this->model;
        } else {
            abort($response['status'] ?? 500, $response['message'] ?? "An error has been occured");
        }
    }

    /**
     * Créer une entité par une requête click
     *
     * @param string $objectId
     * @param string $click
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createObjectByClick(string $objectId,string $click): array
    {
        $this->format = self::FORMAT_PDA;
        $this->method = self::METHOD_CLICK;

        $this->builder = sprintf("id=%s&button=%s", $objectId, $click);
        $this->setUrlInData();

        return Omogen::createOrUpdateObject($this->data);
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
    public function uploadDocument(string $field,  $file): array
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
     * @param float $lon Longitude
     * @param float $lat Latitude
     * @param float $ray Circonference en km
     * @return array
     */
    public function coords(float $lon, float $lat, float $ray): array
    {
        /**
         *   1                111  km
         *   0.1              11.1 km
         *   0.01             1.11 km
         *   0.001            111  m
         *   0.0001           11.1 m
         *   0.00001          1.11 m
         *   0.000001         11.1 cm
         *   0.0000001        1.11 cm
         *   0.00000001       1.11 mm
         */
        $calc = ($ray / 111);
        $this->data['class'] = $this->model->getOmogenClass();

        $this->builder = sprintf("coord=%s,%s,%s,%s",
            ($lat - $calc),
            ($lon - $calc),
            ($lat + $calc),
            ($lon + $calc),
        );

        return $this->get();
    }

    /**
     * Supprime un entité
     *
     * @param string $objectId
     *
     * @return array
     */
    public function delete(string $objectId): array
    {
        $this->method = self::METHOD_DELETE;
        $this->format = self::FORMAT_PDA;

        $this->builder = "id={$objectId}";

        $this->data['data'] = false;

        $this->setUrlInData();

        return Omogen::deleteObject($this->data);
    }

    /**
     * Set l'url dans le data
     *
     * @note Il s'agit de la méthode formattant l'url en prévision de la requête. Elle est toujours appellée juste avant
     * les call vers le service Omogen
     *
     * @return void
     */
    protected function setUrlInData(): void
    {
        if ($this->model->hasRequiredId && $this->needIdOnRequest) {
            $this->builder = sprintf("id=%s&%s", $this->model->getId(), $this->builder);
        }

        $this->data['url'] = "{$this->domain}guygle/{$this->format}/{$this->method}?{$this->builder}";
    }

    /**
     * Créer une requête query selon la clause en paramètre
     *
     * @note Il s'agit de la méthode la plus simple et efficace afin d'effectuer une clase de type where vers Omogen
     * pour récupérer des données.
     *
     * @param string $request
     *
     * @return $this
     */
    public function queryRaw(string $request): self
    {
        $this->method = self::METHOD_GET;
        $this->format = self::FORMAT_API;
        $this->isQueryRequest = true;

        $this->builder = sprintf("query=%s %s", $this->model->getOmogenClassName(), $request);
        $this->setUrlInData();

        return $this;
    }

    /**
     * Permets de placer des relations une requête GET
     *
     * @note C'est la méthode permettant de créer des relations.
     * Les relations se lient avec des points, et les multiples relations s'espacent
     * Ex: with('user.patient.objet', 'customer', 'secteur.agence')
     * !!! Les relations demandées dans la méthode doivent toujours être déclarés sur la synthaxe Omogen
     * !!! Ex: si le champ du model est "customer" et que sa conversion Omogen est "client", il faudra utiliser "client"
     *
     * @return $this
     */
    public function with(): self
    {
        $args = func_get_args();
        $look = [];

        foreach ($args as $relation) {
            $exp = explode('.', $relation);
            $currentPath = '';
            $this->relations[] = $exp;
            foreach ($exp as $index => $attribute) {
                // Initialisation du currentPath lors de la première itération de la relation courante
                if ($index === 0) {
                    $currentPath = $attribute;
                    // Si le départ de la relation n'existe pas, on le créer pour la suite
                    if (!isset($look[$attribute])) {
                        $look[$attribute] = [];
                    }
                    // On skip car nul besoin d'accèder aux étape suivantes
                    continue;
                }
                // Mise à jour du currentPath de la relation courante
                $currentPath = $currentPath.'.'.$attribute;
                if (Arr::get($look, $currentPath)) {
                    continue;
                }
                Arr::set($look, $currentPath, []);
            }
            $currentPath = '';
        }

        $builder = '';
        $f = true;
        // Parcours du tableau $look afin d'écrire le builder selon les relations précedement construites
        foreach($look as $index => $value) {
            // Si c'est une première itération, on défini la base de &look
            if ($f) {
                $builder = sprintf('["%s"',  $index);
                $f = false;
            } else {
                // Sinon on concatène la suite de &look
                $builder = sprintf('%s,"%s"', $builder,  $index);
            }
            // Appel de la méthode afin de créer le &look relatif à la relation courante
            self::iterate($builder, $value);
        }
        // On finalise la valeur de &look on fermant le tableau initalisé au départ de la boucle des relations
        $builder = $builder.']';

        $this->data['look'] = $builder;

        return $this;
    }

    /**
     * Parcours une relation afin d'écrire le builder pour une requête contenant un with
     *
     * @param string $builder
     * @param array $relation
     * @param int $count
     *
     * @return mixed
     */
    public static function iterate(string &$builder, array $relation, int $count = 0)
    {
        $f = true;
        $c = $count;

        // Boucle sur une relation contenant 1,n attributs ex: agences,link
        foreach ($relation as $index => $value) {
            // Si c'est la première itération, on place l'ouverture du tableau
            if ($f) {
                $builder = sprintf('%s,["%s"', $builder, $index);
                // Pour chaque ouverture de tableau, on incrémente pour savoir combien de tableau il faudra femer au terme de la relation
                $c++;
            } else {
                // Si ce n'est pas le début de la relation, on place l'attribut à la suite du précedent
                $builder = sprintf('%s,"%s"', $builder, $index);
            }

            // Si la relation contient plusieurs attributs on souhaite relancer le processus
            if (count($relation) > 0) {
                // Si l'attribut courant ne possède aucune valeur, cela veut dire que c'est la fin de la relation courant
                if (empty($value)) {
                    $f = false;
                    // On passe à l'attribut suivant OU fin de la boucle si dernier attribut de la relation
                    continue;
                }
                // Si l'attribut contient une valeur, cela veut dire que la relation est plus profonde donc on relance le processus
                return self::iterate($builder, $value, $c);
            }
        }

        // Pour chaque ouveture de tableau, on termine l'écriture de la relation par une fermeture de tableau
        for ($i = 0; $i < $c; $i ++) {
            $builder = sprintf('%s]', $builder);
        }
    }

}
