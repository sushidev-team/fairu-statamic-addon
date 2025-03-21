import axios from 'axios';

export const fairuLoadFolder = ({ page, folder, search = null, successCallback, errorCallback }) => {
    axios
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

export const fairuUpload = ({ file, folder, onUploadProgressCallback, errorCallback, successCallback }) => {
    axios
        .post('/fairu/upload', {
            filename: file.name,
            mime: file.type,
            folder,
        })
        .then(async (result) => {
            await axios
                .put(result.data.upload_url, file, {
                    headers: {
                        'x-amz-acl': 'public-read',
                        'Content-Type': file.type?.toString(),
                    },
                    onUploadProgress: onUploadProgressCallback,
                })
                .then(async (resultUpload) => {
                    await axios.get(result.data.sync_url).then((resultSync) => {
                        successCallback(resultSync);
                    });
                })
                .catch((err) => {
                    console.error(err);
                    !!errorCallback && errorCallback(err);
                });
        })
        .catch((err) => {
            console.error(err);
            !!errorCallback && errorCallback(err);
        });
};
