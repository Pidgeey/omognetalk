<?php

namespace OmogenTalk\Model;

use OmogenTalk\Requests\FormRequest;
use OmogenTalk\Lib\Omogen;
use OmogenTalk\Lib\OmogenBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

    /** @var bool Détermine si le paramètre class doit toujours être présent dans les requêtes */
    protected $persistingClassParameter = false;

    /** @var bool Détermine si le model courant existe dans le système Omogen */
    protected $exists = false;

    /** @var bool Détermine si l'identifiant doit être précisé dans l'url pour une requête de création */
    public $idNeeded = false;

    /**
     * Model constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->castBooleanAttributes();
    }

    /**
     * Cast en boolean les champs Oui et Non depuis Omogen
     */
    private function castBooleanAttributes()
    {
        foreach ($this->getAttributes() as $key => $attribute) {
            if ($attribute === 'Oui') {
                $this->setAttribute($key, true);
            }

            if ($attribute === 'Non') {
                $this->setAttribute($key, false);
            }
        }
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
     * Détermine si le model courant détient un paramètre class persistant
     *
     * @return bool
     */
    public function hasPersistingClassParameter(): bool
    {
        return $this->persistingClassParameter;
    }

    /**
     * Détermine si le model courant existe sur le système Omogen
     *
     * @return bool
     */
    public function isObjectExistsInOmogen(): bool
    {
        return $this->exists;
    }

    /**
     * Déclare le model courant comme existant sur le système Omogen
     */
    public function declareModelIsExisting()
    {
        $this->exists = true;
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
    public function setAttribute(string $attribute, $value)
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function save(?string $token): ?self
    {
        return (new OmogenBuilder($this, ['token' => $token]))->createOrUpdate();
    }

    /**
     * Récupère les attributs modifiés apportés au model
     *
     * @param string|null $value
     *
     * @return string|array|null
     */
    public function getChanges(?string $value = null)
    {
        if ($value) {
            return $this->changes[$value] ?? null;
        }
        return $this->changes;
    }

    /**
     * Détermine si le model courant à subis des modifications d'attributs
     *
     * @param string|null $value
     *
     * @return bool
     */
    public function hasChanges(?string $value = null): bool
    {
        if ($value) {
            return isset($this->changes[$value]);
        }
        return !empty($this->changes);
    }

    /**
     * Mets à jour une liste de documents
     *
     * @param \OmogenTalk\Requests\FormRequest $request
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * Ajoute un groupe au model courant
     *
     * @param string $group
     *
     * @return void
     */
    public function addGroup(string $group)
    {
        // Récupère les groupes existants. S'il le model courant n'en dipose pas, créer un tableau vide par défaut
        $groups = $this->groups ?? [];
        // Ajoute le groupe en paramètre dans le tableau des groupes
        $groups[] = $group;
        // Mets à jour les groupes du model courant
        $this->groups = $groups;
    }

    /**
     * Converti un tableau de donnée en string pour intégrer un tableau syntaxe Omogen
     *
     * @param array $arrayToConvert
     *
     * @return string
     */
    public function convertToTableString(array $arrayToConvert)
    {
        /**
         * Pour effectuer des entrés sur un tableau omogen, il faut parser le tout sur une string
         *
         * Chaque valeur de colonne doit être séparé par un caractère ASCII(8)
         * Chaque ligne de tableau doit être séparé par un caractère ASCII(9)
         */

        $escape = chr(8);
        $nextLine = chr(9);

        $string = "";

        // On boucle sur les différentes entrées du tableau de valeur
        foreach ($arrayToConvert as $index => $values) {
            // Ou boucle sur tous les attributs de chaque entrée
            foreach (array_values($values) as $valueIndex => $value) {
                // Si on passe sur une seconde entrée, on espace avec un tab
                if ($valueIndex === 0 && $index > 0) {
                    $string = sprintf("%s%s", $string, $nextLine);
                }
                // S'il s'agit de la première entrée, on démarre de cette valeur
                if ($index === 0 && $valueIndex === 0) {
                    $string = sprintf("%s", $value);
                    // S'il s'agit de la première valeur mais pas la première entrée
                } elseif ($index > 0 && $valueIndex === 0) {
                    $string = sprintf("%s%s", $string, $value);
                    // S'il s'agit d'une autre valeur, on incrémente la string existante puis y ajoute un espace
                } else {
                    $string = sprintf("%s%s%s", $string, $escape, $value);
                }
            }
        }

        return $string;
    }

    /**
     * Set un identifiant pour le model courant
     *
     * @param string $id
     */
    public function setId(string $id)
    {
        $this->attributes['id'] = $id;
    }

    public function update(FormRequest $request)
    {
        $attributes = $request->getValidatedAttributes();

        foreach ($attributes as $key => $value) {
            $this->{$key} = $value;
        }

        // TODO: passer le token provenant du header
        return $this->save(Omogen::getAdminToken());
    }

    /**
     * Setter magic
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        // Ce if permets de pouvoir modifier des attributs avant de créer l'objet sur Omogen
        if (!$this->isObjectExistsInOmogen()) {
            $this->attributes[$name] = $value;
        } else {
            $this->changes[$name] = $value;
        }
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
