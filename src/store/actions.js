import * as types from './types';
import {UPDATE_DATA_FROM_FILE} from './types';

export function updatePostID(postID) {
    return {
        type: types.UPDATE_POST_ID,
        data: postID,
    }
}

export function updatePostType(postType) {
    return {
        type: types.UPDATE_POST_TYPE,
        data: postType,
    }
}
export function updateGroupID(fileId) {
    return {
        type: types.UPDATE_GROUP_ID,
        data: fileId,
    }
}

export function updateFileData(data) {
    return {
        type: types.UPDATE_FILE_DATA,
        data: data,
    }
}

export function updateDataFromFile(data) {
    return {
        type: types.UPDATE_DATA_FROM_FILE,
        data: data,
    }
}

export function updateMapping(data) {
    return {
        type: types.UPDATE_MAPPING,
        data: data,
    }
}