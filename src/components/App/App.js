import React, {useState, useEffect} from 'react';
import './style.scss';
import {connect} from 'react-redux';
import {Select, Spin, Cascader, Button, Input} from 'antd';
import TableRow from '../TableRow/TableRow';

const {Option} = Select;
import 'antd/dist/antd.css';
import * as actions from '../../store/actions';
import LoadFile from '../LoadFile/LoadFile';
import Mapping from '../Mapping/Mapping';
import {routUrl} from '../../config';
import {fetch} from '../../../../../../wp-includes/js/dist/vendor/wp-polyfill-fetch';
import Hint from '../Hint/Hint';

const App = (data) => {
    const [postTypes, setPostTypes] = useState([]);
    const [posts, setPosts] = useState([]);
    const [groupFields, setGroupFields] = useState({all: [], filtered: []});
    const [loading, setLoading] = useState(false);
    const [process, setProcess] = useState(false);
    const [rowID, setRowID] = useState('');
    const [pathToField, setPathToField] = useState([]);
    const [postTitleField, setPostTitleField] = useState('');
    const clearMapping = () => {
        if (Object.keys(data.mappedFields).length !== 0) {
            data.updateMapping({});
        }
    };

    const changePostType = value => {
        setLoading(true);
        data.updatePostType(value);
        clearMapping();
        data.updatePostID(null);
        fetch(routUrl(`get_posts?post_type=${value}`))
            .then(response => response.json())
            .then(data => {
                setPosts(data.posts);
                setGroupFields({all: data.fields, filtered: data.fields});
                setLoading(false);
            });

    };

    const changePost = value => {
        clearMapping();
        setLoading(true);
        data.updatePostID(value);
        fetch(routUrl(`get_posts?post_type=${data.postType}&post=${value}`))
            .then(response => response.json())
            .then(data => {
                setGroupFields({all: data.fields, filtered: data.fields});
                setLoading(false);
            });
    };

    useEffect(() => {
        fetch(routUrl('get_post_types'))
            .then(response => response.json())
            .then(data => {
                setPostTypes(data.postTypes);
            });
    }, []);

    const selectFieldData = keys => {
        clearMapping();
        setPathToField(keys);
        let filtered = [];
        for (let key of keys) {
            if (filtered.length === 0) {
                filtered = groupFields.all.filter(data => data.value === key);
            } else {
                filtered = filtered.filter(data => data.value === key);
            }

            if (filtered[0].children !== undefined) {
                filtered = filtered[0].children;
            }
        }
        setGroupFields({all: groupFields.all, filtered: filtered});
    };
    const startImport = () => {
        setProcess(true);
        fetch(routUrl('import'),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                        data: data.mappedFields,
                        postID: data.postID,
                        fileID: data.fileData.id,
                        rowID,
                        postType: data.postType,
                        postTitleField,
                    }
                )
            }
        )
            .then(response => response.json())
            .then(data => {
                console.log(data);
                setProcess(false);
            })
            .catch(error => {
                setProcess(false);
                console.log(error);
            });
    };
    const changeRowID = (e) => {
        setRowID(e.target.value);
    };

    const changePostTitle = (value) => {
          setPostTitleField(value);
    };
    return (
        <div className="wrap">
            <Spin spinning={loading} size="large">
                <h1>Wp import content</h1>
                <div className="grid">
                    <div className="grid__row">
                        <div className="grid__row-label"><span className="title">Select a file</span></div>
                        <div className="grid__row-option"><LoadFile route={routUrl}/></div>
                    </div>
                    <TableRow title="Select a post type" changeAction={changePostType} defaultValue="Select post type">
                        {postTypes.map(postType => <Option key={postType} value={postType}>{postType}</Option>)}
                    </TableRow>
                    {posts.length !== 0 ? (
                        <TableRow
                            title="Select a post"
                            changeAction={changePost}
                            defaultValue="Select post"
                            hintText="I you want to import content to specific post select the post form list"
                        >{posts.map(post => <Option key={post.postID}
                                                    value={post.postID}>{post.title}</Option>)}</TableRow>) : null}
                    {
                        data.postID === null && data.postType !== '' ?
                            <TableRow title="Select field for post title" changeAction={changePostTitle}
                                      defaultValue="Select field for post title">
                                {data.dataFromFile.map(field => <Option key={field} value={field}>{field}</Option>)}
                            </TableRow> : null
                    }
                    {groupFields.all.length !== 0 ?
                        (
                            <div className="grid__row">
                                <div className="grid__row-label"><span className="title">Select a field</span></div>
                                <div className="grid__row-option">
                                    <Cascader
                                        options={groupFields.all}
                                        onChange={selectFieldData}
                                        changeOnSelect
                                        placeholder="Please select"
                                        size="middle"
                                        syle={{width: 180}}
                                    />
                                </div>
                            </div>) : null}
                    {pathToField.length > 2 ?
                        <div className="grid__row">
                            <div className="grid__row-label">
                                <span className="title">Select row id in the post</span>
                                <Hint
                                    massage={<img src="/wp-content/plugins/wp-import-content/src/images/hint_1.png"/>}/>
                            </div>
                            <div className="grid__row-option"><Input onChange={changeRowID}/></div>
                        </div> : null}
                    <Mapping fields={groupFields.filtered}/>
                    <Button type="primary" loading={process} size="large" disabled={data.mappedFields.length === 0}
                            style={{width: 180, marginTop: 30}} onClick={startImport}>Start import</Button>
                </div>
            </Spin>
        </div>
    );
};

const mapDispatchToProps = {...actions};

const mapStateToProps = state => {
    return state;
};

export default connect(mapStateToProps, mapDispatchToProps)(App);

