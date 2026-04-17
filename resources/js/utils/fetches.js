import { config } from '@statamic/cms/api';

function headers(extra = {}) {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': config.get('csrfToken'),
        ...extra,
    };
}

export async function fairuGetFolder({ folder, successCallback, errorCallback }) {
    try {
        const res = await fetch(`/fairu/folders/${folder}`, {
            headers: headers(),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        successCallback?.({ data });
    } catch (err) {
        console.error(err);
        errorCallback?.(err);
    }
}

export async function fairuLoadFolder({ page, folder, search = null, successCallback, errorCallback }) {
    try {
        const res = await fetch('/fairu/folders', {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ page, folder, search }),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        successCallback?.({ data });
    } catch (err) {
        console.error(err);
        errorCallback?.(err);
    }
}

export async function fairuUpload({ files, folder, onUploadProgressCallback = () => {}, errorCallback, successCallback }) {
    const filesArray = files ? Array.from(files) : [];

    try {
        const res = await fetch('/fairu/upload-multiple', {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({
                files: filesArray.map((file) => file.name),
                folder,
            }),
        });

        if (!res.ok) {
            const errorData = await res.json().catch(() => ({}));
            throw { response: { data: errorData } };
        }

        const entries = await res.json();
        let counter = 0;

        for (const [index, entry] of entries.entries()) {
            try {
                if (!entry.storage_upload_path) {
                    throw new Error(`Missing storage_upload_path for file: ${entry.filename}`);
                }

                const putRes = await fetch(entry.storage_upload_path.toString(), {
                    method: 'PUT',
                    headers: {
                        'x-amz-acl': 'public-read',
                        'Content-Type': entry.mime_type?.toString(),
                    },
                    body: filesArray[index] || null,
                });

                if (!putRes.ok) {
                    throw new Error(`PUT failed with HTTP ${putRes.status} for file: ${entry.filename}`);
                }

                counter++;
                onUploadProgressCallback({ loaded: counter, total: entries.length });
            } catch (err) {
                console.error(err);
                errorCallback?.(err);
                return;
            }
        }

        try {
            const metaRes = await fetch('/fairu/upload-meta-bulk', {
                method: 'POST',
                headers: headers(),
                body: JSON.stringify({
                    files: entries.map((e) => ({
                        path: e.storage_path,
                        name: e.filename,
                        parent_id: folder,
                        fingerprint: e.fingerprint?.toString(),
                    })),
                }),
            });

            if (!metaRes.ok) throw new Error(`HTTP ${metaRes.status}`);
            const metaData = await metaRes.json();
            successCallback?.({ data: metaData });
        } catch (err) {
            console.error(err);
            errorCallback?.(err);
        }
    } catch (err) {
        console.error(err);
        errorCallback?.(err);
    }
}

export async function fairuCreateFolder({ name, folder }) {
    const res = await fetch('/fairu/folders/create', {
        method: 'POST',
        headers: headers(),
        body: JSON.stringify({ name, folder }),
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw errorData;
    }

    return res.json();
}

export async function fairuLoadFilesMeta(ids) {
    const res = await fetch('/fairu/files/list', {
        method: 'POST',
        headers: headers(),
        body: JSON.stringify({ ids }),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

export async function fairuGetFile(id) {
    const res = await fetch(`/fairu/files/${id}`, {
        headers: headers(),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

export async function fairuUpdateFile(id, payload) {
    const res = await fetch(`/fairu/files/${id}`, {
        method: 'PUT',
        headers: headers(),
        body: JSON.stringify(payload),
    });

    if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw errorData;
    }

    return res.json();
}
