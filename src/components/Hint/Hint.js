import {Popover} from 'antd';
import {QuestionCircleOutlined} from '@ant-design/icons';
import React from 'react';

const Hint = ({massage}) => {
    return <Popover trigger="click" content={massage} placement="topLeft">
        <QuestionCircleOutlined/>
    </Popover>;
};

export default Hint;