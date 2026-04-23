<?php

namespace Sushidev\Fairu\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;

class AssetController extends Controller
{

    public function index()
    {
        return redirect(config('statamic.fairu.url') . "/folders");
    }

    public function folderContent(Request $request)
    {

        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->get(config('statamic.fairu.url') . '/api/folders/' . $request->input('folder'), [
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 25),
            'q' => $request->input('search'),
            'globalSearch' => $request->boolean('globalSearch') ? 'true' : 'false',
        ]);

        if ($result->status() == 403) {
            return abort(403, 'Fairu: Currently, no subscription is active for this tenant.');
        }

        if ($result->status() != 200) {
            return abort(400, 'Communication error with Fairu.');
        }

        return $result->json();
    }

    public function upload(Request $request)
    {

        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->post(config('statamic.fairu.url') . '/api/files', [
            'type' => 'standard',
            'filename' => $request->input('filename'),
            'folder' => $request->input('folder'),
        ]);

        if ($result->status() != 200) {
            return abort(400, 'Communication error with Fairu.');
        }

        return $result->json();
    }

    public function uploadMultiple(Request $request)
    {
        $connection = config('statamic.fairu.connections.default');

        // Validate the request
        $request->validate([
            'files' => 'required|array',
            'folder' => 'nullable|string',
        ]);

        try {
            $result = Http::withHeaders([
                'Tenant' => data_get($connection, 'tenant'),
                'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
            ])->post(config('statamic.fairu.url') . '/api/upload', [
                'files' => $request->input('files'),
                'folder' => $request->input('folder'),
            ]);

            if (!$result->successful()) {
                return response()->json(['error' => 'Communication error with Fairu: ' . $result->body()], $result->status());
            }

            return response()->json($result->json());
        } catch (\Exception $e) {
            \Log::error('Fairu upload error: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occured: ' . $e->getMessage()], 500);
        }
    }


    public function uploadMetaBulk(Request $request)
    {

        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->post(config('statamic.fairu.url') . '/api/upload/meta/bulk', [
            'files' => $request->input('files'),
        ]);

        if ($result->status() != 200) {
            return abort(400, 'Communication error with Fairu.');
        }

        return $result->json();
    }

    public function createFolder(Request $request)
    {

        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->post(config('statamic.fairu.url') . '/api/folders', [
            'name' => $request->input('name'),
            'parent_id' => $request->input('folder'),
        ]);

        if ($result->status() != 200) {
            return abort(400, 'Communication error with Fairu.');
        }

        return $result->json();
    }

    public function updateFolder(Request $request, $id)
    {

        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->put(config('statamic.fairu.url') . '/api/folders/' . $id, [
            'name' => $request->input('name'),
            'parent_id' => $request->input('folder'),
        ]);

        if ($result->status() != 200) {
            return abort(400, 'Communication error with Fairu.');
        }

        return $result->json();
    }

    public function getFile(Request $request, String $id)
    {
        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->get(config('statamic.fairu.url') . '/api/files/' . $id);

        if ($result->status() == 403) {
            return abort(403, 'Fairu: Currently, no subscription is active for this tenant.');
        }

        if ($result->status() != 200) {
            return abort(400, 'Communication error with Fairu.');
        }

        return $result->json();
    }

    public function updateFile(Request $request, string $id)
    {
        $data = array_merge(
            ['id' => $id],
            array_filter(
                $request->only(['alt', 'caption', 'description', 'focal_point']),
                fn ($value) => $value !== null,
            ),
        );

        return $this->graphql(
            <<<'GRAPHQL'
                mutation UpdateFairuFile($data: FairuFileDTO!) {
                    updateFairuFile(data: $data) {
                        id
                        name
                        alt
                        caption
                        description
                        focal_point
                    }
                }
            GRAPHQL,
            ['data' => $data],
            'updateFairuFile',
        );
    }

    public function deleteFile(Request $request, string $id)
    {
        return $this->graphql(
            <<<'GRAPHQL'
                mutation DeleteFairuFile($id: ID!) {
                    deleteFairuFile(id: $id)
                }
            GRAPHQL,
            ['id' => $id],
            'deleteFairuFile',
        );
    }

    public function renameFile(Request $request, string $id)
    {
        $request->validate(['name' => 'required|string|min:1|max:255']);

        return $this->graphql(
            <<<'GRAPHQL'
                mutation RenameFairuFile($id: ID!, $name: String!) {
                    renameFairuFile(id: $id, name: $name) {
                        id
                        name
                    }
                }
            GRAPHQL,
            ['id' => $id, 'name' => $request->input('name')],
            'renameFairuFile',
        );
    }

    public function moveFile(Request $request, string $id)
    {
        $parent = $request->input('parent');

        return $this->graphql(
            <<<'GRAPHQL'
                mutation MoveFairuFile($id: ID!, $parent: ID) {
                    moveFairuFile(id: $id, parent: $parent)
                }
            GRAPHQL,
            ['id' => $id, 'parent' => $parent ?: null],
            'moveFairuFile',
        );
    }

    protected function graphql(string $query, array $variables, string $dataKey)
    {
        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->post(config('statamic.fairu.url') . '/graphql', [
            'query' => $query,
            'variables' => $variables,
        ]);

        if ($result->status() == 403) {
            return abort(403, 'Fairu: Currently, no subscription is active for this tenant.');
        }

        if (!$result->successful()) {
            return abort(400, 'Communication error with Fairu.');
        }

        $body = $result->json();

        if (!empty($body['errors'])) {
            return response()->json([
                'message' => data_get($body, 'errors.0.message', 'GraphQL error'),
                'errors' => $body['errors'],
            ], 422);
        }

        return ['data' => data_get($body, 'data.' . $dataKey)];
    }

    public function getFilesList(Request $request)
    {
        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->post(config('statamic.fairu.url') . '/api/files/list', [
            'ids' => $request->input('ids'),
        ]);

        if ($result->status() == 403) {
            return abort(403, 'Fairu: Currently, no subscription is active for this tenant.');
        }

        if ($result->status() != 200) {
            return abort(400, 'Communication error with Fairu.');
        }

        return $result->json();
    }


    public function getFolder(Request $request, String $id)
    {
        $connection = config('statamic.fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->get(config('statamic.fairu.url') . '/api/folders/' . $id);

        if ($result->status() == 403) {
            return abort(403, 'Fairu: Currently, no subscription is active for this tenant.');
        }

        if ($result->status() != 200) {
            return abort(400, 'Communication error with Fairu.');
        }

        return $result->json();
    }
}
