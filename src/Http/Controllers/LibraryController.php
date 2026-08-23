<?php

namespace Sushidev\Fairu\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statamic\Facades\User;
use Sushidev\Fairu\Services\FairuChannels;
use Sushidev\Fairu\Services\FairuGalleries;
use Throwable;

/**
 * What the gallery and channel pickers read.
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
