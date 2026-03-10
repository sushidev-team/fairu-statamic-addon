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
                await fetch(entry.storage_upload_path?.toString() || '/', {
                    method: 'PUT',
                    headers: {
                        'x-amz-acl': 'public-read',
                        'Content-Type': entry.mime_type?.toString(),
                    },
                    body: filesArray[index] || null,
                });

                counter++;
                onUploadProgressCallback({ loaded: counter, total: entries.length });

                if (counter === entries.length) {
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
                }
            } catch (err) {
                console.error(err);
                errorCallback?.(err);
                return;
            }
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
