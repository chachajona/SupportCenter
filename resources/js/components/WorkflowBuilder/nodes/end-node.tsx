import { Handle, Position } from '@xyflow/react';
import React from 'react';
import { WorkflowNodeData } from '../types';

interface EndNodeProps {
    data: WorkflowNodeData;
    selected?: boolean;
}

const EndNode: React.FC<EndNodeProps> = ({ data, selected }) => {
    const getEndType = () => {
        const endType = data.config?.type || 'success';
        switch (endType) {
            case 'success':
                return { icon: '✅', title: 'Success', className: 'success' };
            case 'error':
                return { icon: '❌', title: 'Error', className: 'error' };
            case 'cancelled':
                return { icon: '⚪', title: 'Cancelled', className: 'cancelled' };
            case 'timeout':
                return { icon: '⏰', title: 'Timeout', className: 'timeout' };
            default:
                return { icon: '🏁', title: 'End', className: 'default' };
        }
    };

    const endTypeInfo = getEndType();

    return (
        <div className={`workflow-node end-node end-${endTypeInfo.className} ${selected ? 'selected' : ''}`}>
            <Handle type="target" position={Position.Left} className="node-handle node-handle-target" id="end-input" />

            <div className="node-header">
                <div className="node-icon">{endTypeInfo.icon}</div>
                <div className="node-title">{endTypeInfo.title}</div>
            </div>

            <div className="node-content">
                <div className="node-description">{data.description || 'Workflow endpoint'}</div>

                {data.config?.message && <div className="end-message">{data.config.message}</div>}

                {data.config?.return_data && (
                    <div className="end-return-data">
                        Returns: {typeof data.config.return_data === 'string' ? data.config.return_data : JSON.stringify(data.config.return_data)}
                    </div>
                )}
            </div>

            <div className="node-status">
                {data.isValid ? <div className="status-indicator status-valid">✓</div> : <div className="status-indicator status-invalid">!</div>}
            </div>
        </div>
    );
};

export default EndNode;
