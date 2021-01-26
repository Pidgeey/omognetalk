<?php

namespace OmogenTalk\Model;

use OmogenTalk\Requests\FormRequest;
use OmogenTalk\Lib\Omogen;
use OmogenTalk\Lib\OmogenBuilder;

/**
 * Class Model
 *
 * @package App\Model
 */
abstract class Model
{
    /** @var string Classe du model Omogen */
    protected $omogenClass;

    /** @var string Nom de la classe Omogen */
    protected $omogenClassName;

    /** @var array Liste des attributs fillable du model courant */
    protected $fillable = [];

    /** @var array Attributs du model courant */
    protected $attributes = [];

    /** @var array Tableau des attributs convertit pour une requête Omogen */
    protected $omogenConvertedAttributes = [];

    /** @var string Clé primaire du model */
    protected $primaryKey;

    /** @var array Tableau des attributs ayant subit une modification */
    protected $changes = [];

    /**
     * Model constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Récupère l'identifiant du model courant
     *
     * @return string
     */
    public abstract function getId(): string;

    /**
     * Récupère la clé primaire du model courant
     *
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Retourne la classe omogen du model courant
     *
     * @return string
     */
    public function getOmogenClass(): string
    {
        return $this->omogenClass;
    }

    /**
     * Retourne le nom de classe omogen du model courant
     *
     * @return string
     */
    public function getOmogenClassName(): string
    {
        return $this->omogenClassName;
    }

    /**
     * Instancie un builder Omogen sur le model courant
     *
     * @param array $data
     *
     * @return OmogenBuilder
     */
    public static function getQueryBuilder(array $data): OmogenBuilder
    {
        return new OmogenBuilder(new static, $data);
    }

    /**
     * Retourne les attributs du model courant
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set model attributes
     *
     * @param array $attributes
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Set attribute
     *
     * @param string $attribute
     * @param string $value
     */
    public function setAttribute(string $attribute, string $value)
    {
        $this->attributes[$attribute] = $value;
    }

    /**
     * Récupère une liste d'attributs convertis pour le système Omogen
     *
     * @param string $method
     * @param bool $merge
     * @param array|null $attributes
     *
     * @return array
     */
    public function getOmogenConvertedAttributes(string $method = OmogenBuilder::METHOD_GET, bool $merge = false, ?array $attributes = null): array
    {
        $convertedAttributes = [];
        foreach ($attributes ?? $this->getAttributes() as $key => $attribute) {
            if (isset($this->omogenConvertedAttributes[$method][$key])) {
                $convertedAttributes['converted'][$this->omogenConvertedAttributes[$method][$key]] = $attribute;
            } else {
                $convertedAttributes['unconverted'][$key] = $attribute;
            }
        }

        if ($merge) {
            $convertedAttributes = array_merge($convertedAttributes['converted'] ?? [], $convertedAttributes['unconverted'] ?? []);
        }

        return $convertedAttributes;
    }

    /**
     * Créer un nouveau model
     *
     * @param \OmogenTalk\Requests\FormRequest $request
     *
     * @return static|null
     */
    public static function create(FormRequest $request): ?self
    {
        $model = new static($request->validated());
        return $model->save($request->header('Authorization'));

    }

    /**
     * Sauvegarde un model
     *
     * @param string|null $token
     *
     * @return $this|null
     */
    public function save(?string $token): ?self
    {
        return (new OmogenBuilder($this, ['token' => $token]))->createOrUpdate();
    }

    /**
     * Récupère le tableau des attributs modifiés du model courant
     *
     * @return array
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Détermine si le model courant à subis des modifications d'attributs
     *
     * @return bool
     */
    public function hasChanges(): bool
    {
        return !empty($this->changes);
    }

    /**
     * Mets à jour une liste de documents
     *
     * @param \OmogenTalk\Requests\FormRequest $request
     *
     * @return array
     */
    public function uploadDocument(FormRequest $request): array
    {
        $response = [];
        $attributes = $this->getOmogenConvertedAttributes(OmogenBuilder::METHOD_PUT, true, $request->allFiles());

        foreach ($attributes as $field => $file) {
            $response[] = (new OmogenBuilder($this, ['token' => Omogen::getAdminToken()]))->uploadDocument($field, $file);
        }

        return $response;
    }

    /**
     * Setter magic
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->changes[$name] = $value;
    }

    /**
     * Getter magic
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }
}
