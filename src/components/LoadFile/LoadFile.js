import React, {useState} from 'react';
import { Button } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import {connect} from 'react-redux';
import {updateFileData, updateDataFromFile} from '../../store/actions';
import './style.scss';

const LoadFile = ({route, fileData, updateFileData, updateDataFromFile}) => {
    const [loading, setLoading] = useState(false);
    const loadFile = e => {
        setLoading(true);
        const file = wp.media({
            title: 'Upload file',
            multiple: false,
            button: {
                text: 'Use this file'
            },
            library: {
                type: [
                    'text/csv'
                ]
            },

        }).open()
            .on('select', function () {
                const uploaded_file = file.state().get('selection').first();
                const fileData = uploaded_file.toJSON();
                fetch(route(`read_file/?id=${fileData.id}`))
                    .then(response => response.json())
                    .then(data => {
                        setLoading(false);
                        updateDataFromFile(data.data);
                        updateFileData({
                            id: fileData.id,
                            name: fileData.filename,
                        });
                    })
                    .catch(error => {
                        setLoading(false);
                        console.log(error);
                    })
            })
            .on('close', function() {
                setLoading(false);
            })
    }

    return (
        <div className="load-file">
            <Button type="primary" icon={<DownloadOutlined />} onClick={loadFile} loading={loading}>Select a file</Button>
            <div className="load-file__name">{fileData.name}</div>
        </div>
    )
}
const mapDispatchToProps = {
    updateFileData,
    updateDataFromFile,
}

const mapStateToProps = state => {
    return {
        fileData: state.fileData
    }
}
export default connect(mapStateToProps, mapDispatchToProps)(LoadFile);