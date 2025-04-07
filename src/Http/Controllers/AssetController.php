<?php

namespace Sushidev\Fairu\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;

class AssetController extends Controller
{

    public function index()
    {
        return redirect(config('fairu.url') . "/folders");
    }

    public function folderContent(Request $request)
    {

        $connection = config('fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->get(config('fairu.url') . '/api/folders/' . $request->input('folder'), [
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 25),
            'q' => $request->input('search'),
        ]);

        if ($result->status() == 403) {
            return abort(403, 'FAIRU: Derzeit exisitert zu diesem Tenant kein Abo. Bitte wende dich an den Support.');
        }

        if ($result->status() != 200) {
            return abort(400, 'Fehler bei der Kommunikation mit Fairu.');
        }

        return $result->json();
    }

    public function upload(Request $request)
    {

        $connection = config('fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->post(config('fairu.url') . '/api/files', [
            'type' => 'standard',
            'filename' => $request->input('filename'),
            'folder' => $request->input('folder'),
        ]);

        if ($result->status() != 200) {
            return abort(400, 'Fehler bei der Kommunikation mit Fairu.');
        }

        return $result->json();
    }

    public function createFolder(Request $request)
    {

        $connection = config('fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->post(config('fairu.url') . '/api/folders', [
            'name' => $request->input('name'),
            'parent_id' => $request->input('folder'),
        ]);

        if ($result->status() != 200) {
            return abort(400, 'Fehler bei der Kommunikation mit Fairu.');
        }

        return $result->json();
    }

    public function updateFolder(Request $request, $id)
    {

        $connection = config('fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->put(config('fairu.url') . '/api/folders/' . $id, [
            'name' => $request->input('name'),
            'parent_id' => $request->input('folder'),
        ]);

        if ($result->status() != 200) {
            return abort(400, 'Fehler bei der Kommunikation mit Fairu.');
        }

        return $result->json();
    }

    public function getFile(Request $request, String $id)
    {
        $connection = config('fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->get(config('fairu.url') . '/api/files/' . $id);

        if ($result->status() == 403) {
            return abort(403, 'FAIRU: Derzeit exisitert zu diesem Tenant kein Abo. Bitte wende dich an den Support.');
        }

        if ($result->status() != 200) {
            return abort(400, 'Fehler bei der Kommunikation mit Fairu.');
        }

        return $result->json();
    }
    public function getFolder(Request $request, String $id)
    {
        $connection = config('fairu.connections.default');

        $result = Http::withHeaders([
            'Tenant' => data_get($connection, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($connection, 'tenant_secret'),
        ])->get(config('fairu.url') . '/api/folders/' . $id);

        if ($result->status() == 403) {
            return abort(403, 'FAIRU: Derzeit exisitert zu diesem Tenant kein Abo. Bitte wende dich an den Support.');
        }

        if ($result->status() != 200) {
            return abort(400, 'Fehler bei der Kommunikation mit Fairu.');
        }

        return $result->json();
    }
}
