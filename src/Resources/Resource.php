<?php

namespace OmogenTalk\Resources;

use OmogenTalk\Model\Model;
use Illuminate\Http\Response;

/**
 * Class Resource
 *
 * @package App\Resources
 */
class Resource
{
    /** @var Model|null $resource */
    protected $resource;

    /** @var array */
    private $data;

    /** @var bool Détermine si les documents doivent être récupérer avec la resource */
    protected bool $withFiles = false;

    /**
     * Resource constructor.
     *
     * @param \OmogenTalk\Model\Model|null $model
     * @param bool $withFiles
     */
    public function __construct(?Model $model, bool $withFiles = false)
    {
        $this->withFiles = $withFiles;

        if ($model) {
            $this->resource = $model;
            $this->data = $this->defineDataset();
        }
    }

    /**
     * Initiate resource
     *
     * @param Model $model
     */
    private function setResource(Model $model): void
    {
        $this->resource = $model;
    }

    /**
     * Get resource
     *
     * @return Model
     */
    protected function getResource(): Model
    {
        return $this->resource;
    }

    /**
     * Cast resource into array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->resource->getAttributes();
    }

    /**
     * Retourne une réponse au format json
     *
     * @return \Illuminate\Http\Response
     */
    public function toJsonResponse(): Response
    {
        $status = null;
        if (!$this->resource) {
            $this->data['message'] = sprintf('%s: Aucune resource trouvée', static::class);
            $status = 404;
        }
        return jsonResponse($this->data, $status);
    }

    /**
     * Détermine le modèle de réponse json de la resource
     *
     * @return array[]
     */
    private function defineDataset(): array
    {
        return [
            'id' => $this->resource->id ?? null,
            'type' => $this->resource->getOmogenClassName(),
            'attributes' => $this->toArray(),
        ];
    }
}
