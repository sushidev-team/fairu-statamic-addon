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
            this.$toast.error(err.response.data.message);
        });
};
