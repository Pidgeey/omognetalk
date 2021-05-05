<?php

namespace OmogenTalk\Resources;

use OmogenTalk\Model\Model;

/**
 * Class ResourceCollection
 *
 * @package OmogenTalk\Resources
 */
class ResourceCollection
{
    /** @var array Collection de resources */
    protected $resourceCollection = [];

    /** @var bool Détermine si les documents doivent être récupérer avec la resource */
    protected bool $withFiles = false;

    /**
     * ResourceCollection constructor.
     *
     * @param array $models
     * @param bool $withFiles
     */
    public function __construct(array $models, bool $withFiles = false)
    {
        foreach ($models as $model) {
            $this->setResourceCollection($model, $withFiles);
        }
    }

    /**
     * Add current model to resource collection
     *
     * @param Model $model
     * @param bool $withFiles
     */
    private function setResourceCollection(Model $model, bool $withFiles): void
    {
        /** @var Resource $modelResource */
        $modelResource = $model->resource;
        $attributes = (new $modelResource($model, $withFiles))->toArray();
        if (!empty($attributes)) {
            $this->resourceCollection[] = [
                'id' => $attributes['id'] ?? null,
                'type' => $attributes['classe'] ?? null,
                'attributes' => $attributes
            ];
        }

    }

    /**
     * Retourne une réponse au format json
     *
     * @return \Illuminate\Http\Response
     */
    public function toJsonResponse(): \Illuminate\Http\Response
    {
        $status = null;
        if (empty($this->resourceCollection)) {
            $this->resourceCollection['message'] = sprintf('%s: Aucune resource trouvée', static::class);
            $status = 404;
        }
        return jsonResponse($this->resourceCollection, $status);
    }
}