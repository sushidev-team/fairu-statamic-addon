<?php

namespace Sushidev\Fairu\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Statamic\Facades\User;
use Sushidev\Fairu\Services\FairuChannels;
use Sushidev\Fairu\Services\FairuGalleries;
use Throwable;

/**
 * What the gallery, channel and episode pickers read.
 *
 * Both are lists an editor chooses one entry from, and both are short — a
 * workspace has tens of galleries and a handful of shows, not thousands — so
 * they are fetched once and filtered in the browser rather than searched over
 * the wire.
 *
 * Behind the CP session and the `view fairu assets` permission: the answer names
 * every gallery and every unpublished show in the workspace, which is a listing
 * a visitor is deliberately not given.
 */
class LibraryController extends Controller
{
    public function galleries(Request $request)
    {
        $this->ensureCanView();

        try {
            $result = (new FairuGalleries($this->connection($request)))->all([
                'perPage' => 250,
                'search' => $request->input('search'),
                'orderBy' => 'name',
                'orderDirection' => 'ASC',
            ]);
        } catch (Throwable $ex) {
            return response()->json(['data' => [], 'message' => $ex->getMessage()], 200);
        }

        return response()->json([
            'data' => collect(data_get($result, 'data', []))->map(fn ($gallery) => [
                'id' => data_get($gallery, 'id'),
                'name' => data_get($gallery, 'name'),
                'date' => data_get($gallery, 'date'),
                'location' => data_get($gallery, 'location'),
            ])->values(),
        ]);
    }

    /**
     * Channels for the picker, including the ones that are not published yet:
     * a show is set up in the CMS before it goes live, and a picker that only
     * offered published shows could not be used to prepare the page that
     * publishes one.
     */
    public function channels(Request $request)
    {
        $this->ensureCanView();

        try {
            $result = (new FairuChannels($this->connection($request)))->all([
                'preview' => true,
                'perPage' => 250,
                'search' => $request->input('search'),
            ]);
        } catch (Throwable $ex) {
            return response()->json(['data' => [], 'message' => $ex->getMessage()], 200);
        }

        return response()->json([
            'data' => collect(data_get($result, 'data', []))->map(fn ($channel) => [
                'id' => data_get($channel, 'id'),
                'name' => data_get($channel, 'name'),
                'slug' => data_get($channel, 'slug'),
                'kind' => data_get($channel, 'kind'),
            ])->values(),
        ]);
    }

    /**
     * The episodes of one channel, for the episode picker.
     *
     * Reads the workspace's own view rather than the visitor's: the field exists
     * so that a page can be prepared for an episode before it is out, which is
     * precisely the episode the public query withholds.
     */
    public function episodes(Request $request)
    {
        $this->ensureCanView();

        $channel = (string) $request->input('channel');

        if (! Str::isUuid($channel)) {
            return response()->json(['data' => []], 200);
        }

        try {
            $result = (new FairuChannels($this->connection($request)))->find($channel, [
                'preview' => true,
                'episodes' => true,
            ]);
        } catch (Throwable $ex) {
            return response()->json(['data' => [], 'message' => $ex->getMessage()], 200);
        }

        return response()->json([
            'data' => collect(data_get($result, 'episodes', []))->map(fn ($episode) => [
                'id' => data_get($episode, 'id'),
                // `name` rather than `title`, so the pickers all read one shape.
                'name' => data_get($episode, 'title'),
                'number' => data_get($episode, 'number'),
                'published_at' => data_get($episode, 'published_at'),
            ])->values(),
        ]);
    }

    protected function connection(Request $request): string
    {
        return (string) ($request->input('connection') ?: 'default');
    }

    protected function ensureCanView(): void
    {
        $user = User::current();

        if (! $user || ! ($user->isSuper() || $user->hasPermission('view fairu assets'))) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}
