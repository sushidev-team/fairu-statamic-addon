<?php

namespace Sushidev\Fairu\Tags;

use Statamic\Tags\Tags;
use Sushidev\Fairu\Services\FairuAssetRenderer;
use Sushidev\Fairu\Services\FairuMetaBag;
use Sushidev\Fairu\Traits\TransformAssets;

class FairuAssetTags extends Tags
{
    use TransformAssets;

    protected static $handle = 'fairu';

    public function url(): string
    {
        $id = $this->resolveIds($this->params->get('id'))[0] ?? null;
        $params = array_merge($this->params->toArray(), ['id' => $id]);
        $mode = $this->resolveFetchMetaMode($this->fetchMetaParam());
        $asset = null;

        if (! $this->params->get('name') && $id !== null && $mode !== null) {
            $bag = app(FairuMetaBag::class);
            if ($mode === 'meta' && $bag->shouldDefer()) {
                return $bag->queue('url', $id, $params, $this->getConnectionName());
            }
            $asset = $this->getFile($id, $this->fetchMetaParam());
        }

        return app(FairuAssetRenderer::class)->render('url', $params, $asset, $this->getConnectionName());
    }

    public function index()
    {
        $ids = $this->resolveIds($this->params->get('id') ?? $this->params->get('ids'));
        $params = $this->params->toArray();
        $bag = app(FairuMetaBag::class);

        if ($ids && $this->resolveFetchMetaMode($this->fetchMetaParam()) === 'meta'
            && $bag->shouldDefer() && is_string($this->content) && $this->content !== '') {
            return $bag->queueList(
                $ids, $params, $this->content, $this->context?->all() ?? [],
                $this->getConnectionName(), $this->templatingLanguage(),
            );
        }

        return app(FairuAssetRenderer::class)->assets(
            $this->getFiles($ids, $this->fetchMetaParam()) ?? [], $params, $this->getConnectionName(),
        );
    }

    public function image(): string
    {
        $id = $this->resolveIds($this->params->get('id'))[0] ?? null;
        if (! $id) {
            return '';
        }

        $params = array_merge($this->params->toArray(), ['id' => $id]);
        $bag = app(FairuMetaBag::class);
        if ($this->resolveFetchMetaMode($this->fetchMetaParam()) === 'meta' && $bag->shouldDefer()) {
            return $bag->queue('image', $id, $params, $this->getConnectionName());
        }

        return app(FairuAssetRenderer::class)->render(
            'image', $params, $this->getFile($id, $this->fetchMetaParam()), $this->getConnectionName(),
        );
    }

    public function images(): string
    {
        $ids = $this->resolveIds($this->params->get('ids') ?? $this->params->get('id'));
        if (! $ids) {
            return '';
        }

        $params = $this->params->toArray();
        $bag = app(FairuMetaBag::class);
        if ($this->resolveFetchMetaMode($this->fetchMetaParam()) === 'meta' && $bag->shouldDefer()) {
            return collect($ids)->map(fn ($id) => $bag->queue(
                'image', $id, array_merge($params, ['id' => $id]), $this->getConnectionName(),
            ))->implode('');
        }

        $renderer = app(FairuAssetRenderer::class);

        return collect($this->getFiles($ids, $this->fetchMetaParam()))
            ->map(fn ($asset) => $renderer->render('image', $params, $asset, $this->getConnectionName()))
            ->implode('');
    }
}
