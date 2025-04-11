import axios from 'axios';

export const fairuGetFolder = async ({ folder, successCallback, errorCallback }) => {
    await axios
        .get(`/fairu/folders/${folder}`)
        .then((result) => {
            !!successCallback && successCallback(result);
        })
        .catch((err) => {
            console.error(err);
            !!errorCallback && errorCallback(err);
        });
};

export const fairuLoadFolder = async ({ page, folder, search = null, successCallback, errorCallback }) => {
    await axios
        .post('/fairu/folders', {
            page,
            folder,
            search,
        })
        .then((result) => {
            !!successCallback && successCallback(result);
        })
        .catch((err) => {
            console.error(err);
            !!errorCallback && errorCallback(err);
        });
};

export const fairuUpload = async ({
    files,
    folder,
    onUploadProgressCallback = () => {},
    errorCallback,
    successCallback,
}) => {
    const filesArray = files ? Array.from(files) : [];

    axios
        .post('/fairu/upload-multiple', {
            files: filesArray?.map((file) => file.name),
            folder,
        })
        .then(async (result) => {
            let counter = 0;

            result?.data?.forEach(async (entry, index) => {
                try {
                    let resultUpload = await axios.put(
                        entry.storage_upload_path ? entry.storage_upload_path.toString() : '/',
                        filesArray ? filesArray[index] : null,
                        {
                            headers: {
                                'x-amz-acl': 'public-read',
                                'Content-Type': entry.mime_type?.toString(),
                            },
                            onUploadProgress: onUploadProgressCallback,
                        },
                    );

                    counter++;

                    if (counter == result.data.length) {
                        const resultMeta = await axios.post('/fairu/upload-meta-bulk', {
                            files: result?.data.map((entry) => {
                                return {
                                    path: entry.storage_path,
                                    name: entry.filename,
                                    parent_id: folder,
                                    fingerprint: entry.fingerprint?.toString(),
                                };
                            }),
                        });
                        !!successCallback && successCallback(resultMeta);
                    }
                } catch (err) {
                    console.error(err);
                    !!errorCallback && errorCallback(err);
                }
            });
        })
        .catch((err) => {
            console.error(err);
            !!errorCallback && errorCallback(err);
        });
};
