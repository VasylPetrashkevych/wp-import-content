import React from 'react';
import {Select} from 'antd';
const {Option} = Select;
import Hint from '../Hint/Hint';
const TableRow = ({title, defaultValue, changeAction, hintText, children}) => {
    return (
        <div className="grid__row">
            <div className="grid__row-label">
                <span className="title">{title}</span>
                {hintText ? <Hint massage={hintText} /> : null }
            </div>
            <div className="grid__row-option">
                <Select defaultValue="default" style={{width: 180}} onChange={changeAction}>
                    <Option value="default" disabled>{defaultValue}</Option>
                    {children}
                </Select>
            </div>
        </div>
    )
}

export default TableRow;