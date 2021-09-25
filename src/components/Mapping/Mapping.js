import React from 'react';
import {connect} from 'react-redux';
import './style.scss';
import {Table, Select, Tag} from 'antd';

const {Column} = Table;
const {Option} = Select;
import {updateMapping} from '../../store/actions';
import {disabledFields} from '../../config';

const Mapping = (data) => {
    const setMapping = (value, row) => {
        const dataFields = {...data.mappedFields};
        let key = row.parent_key === undefined ? 'no_parent' : row.parent_key;
        if (dataFields[key] === undefined) {
            dataFields[key] = [];
        }
        dataFields[key].push({key: row['field-key'], value, type: row.type, group_key: row['group_key'] });
        data.updateMapping(dataFields);
    };
    const tableData = [];
    for (let field of data.fields) {
        tableData.push(
            {
                key: field.value,
                field: field.label,
                field_type: field.type,
                parent_field: field['parent_field'],
                group_key: field['group_key'],
                status: false,
            }
        );
    }

    return <div className="mapping">
        {
            data.length !== 0 ? <Table
                dataSource={tableData}
                pagination={false}
                bordered={true}
            >
                <Column title="Parent key" dataIndex="parent_field" key="parent_field" className="hidden_col"/>
                <Column title="Group key" dataIndex="group_key" key="group_key" className="hidden_col"/>
                <Column title="Field" dataIndex="field" key="field"/>
                <Column title="Field type" dataIndex="field_type" key="field_type"/>
                <Column title="Connected field" dataIndex="connected_field" key="connected_field"
                        render={
                            (text, record) => {
                                if (disabledFields.includes(record.field_type)) {
                                    return <Tag color="red">This field can't be connected</Tag>;
                                }
                                if (data.dataFromFile.length === 0) {
                                    return <Tag color="orange">Select a file</Tag>;
                                }
                                return (
                                    <Select style={{width: 180}} onChange={setMapping} defaultValue="default">
                                        <Option value="default">not selected</Option>
                                        {data.dataFromFile.map(field => <Option field-key={record.key}
                                                                                parent_key={record.parent_field}
                                                                                type={record.field_type}
                                                                                key={field}
                                                                                group_key={record.group_key}
                                                                                value={field}>{field}</Option>)}
                                    </Select>);
                            }}/>
                <Column title="Status" dataIndex="status" key="status"
                        render={(text, record) => {
                            const status = Object.keys(data.mappedFields).find((element) => {
                                return data.mappedFields[element].find(item => {
                                    if (item.key !== 'undefined') {
                                        return item.key === record.key;
                                    }
                                });
                            });
                            return <Tag color={status ? 'green' : 'red'}>{status ? 'Connected' : 'Not connected'}</Tag>;
                        }}
                />
            </Table> : null
        }
    </div>;
};
const mapDispatchToProps = {
    updateMapping,
};

const mapStateToProps = state => {
    return state;
};

export default connect(mapStateToProps, mapDispatchToProps)(Mapping);

