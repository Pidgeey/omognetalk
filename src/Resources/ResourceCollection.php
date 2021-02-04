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

    /**
     * ResourceCollection constructor.
     *
     * @param array $models
     */
    public function __construct(array $models)
    {
        foreach ($models as $model) {
            $this->setResourceCollection($model);
        }
    }

    /**
     * Add current model to resource collection
     *
     * @param Model $model
     */
    private function setResourceCollection(Model $model): void
    {
        /** @var Resource $modelResource */
        $modelResource = $model->resource;
        $attributes = (new $modelResource($model))->toArray();
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