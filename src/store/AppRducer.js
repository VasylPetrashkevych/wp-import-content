import * as types from './types';

const initState = {
    postID: null,
    postType: '',
    fileData: {
        id: null,
        name: '',
    },
    dataFromFile: [],
    mappedFields: {},
    groupID: null,
}

export const AppReducer = (state = initState, action) => {
    const data = action.data;
    switch (action.type) {
        case types.UPDATE_POST_ID:
            return {...state, postID: data}
        case types.UPDATE_POST_TYPE:
            return {...state, postType: data}
        case types.UPDATE_GROUP_ID:
            return {...state, groupID: data}
        case types.UPDATE_FILE_DATA:
            return {...state, fileData: data}
        case types.UPDATE_DATA_FROM_FILE:
            return {...state, dataFromFile: data}
        case types.UPDATE_MAPPING:
            return {...state, mappedFields: data}
        default:
            return  state
    }
}