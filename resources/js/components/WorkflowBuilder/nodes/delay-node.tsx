import { Handle, Position } from '@xyflow/react';
import React from 'react';
import { WorkflowNodeData } from '../types';

interface DelayNodeProps {
    data: WorkflowNodeData;
    selected?: boolean;
}

const DelayNode: React.FC<DelayNodeProps> = ({ data, selected }) => {
    const formatDelayTime = (seconds: number) => {
        if (seconds < 60) {
            return `${seconds}s`;
        } else if (seconds < 3600) {
            return `${Math.floor(seconds / 60)}m`;
        } else if (seconds < 86400) {
            return `${Math.floor(seconds / 3600)}h`;
        } else {
            return `${Math.floor(seconds / 86400)}d`;
        }
    };

    const delaySeconds = data.config?.seconds || data.config?.delay_seconds || 0;
    const delayType = data.config?.type || 'fixed';

    const getDelayDisplay = () => {
        if (delayType === 'fixed') {
            return formatDelayTime(delaySeconds);
        } else if (delayType === 'dynamic') {
            return `Dynamic (${data.config?.field || 'field'})`;
        } else {
            return 'Wait';
        }
    };

    return (
        <div className={`workflow-node delay-node ${selected ? 'selected' : ''}`}>
            <Handle type="target" position={Position.Left} className="node-handle node-handle-target" id="delay-input" />

            <div className="node-header">
                <div className="node-icon">⏱️</div>
                <div className="node-title">Delay</div>
            </div>

            <div className="node-content">
                <div className="node-description">{data.description || 'Wait for a specified time'}</div>

                <div className="delay-info">
                    <div className="delay-duration">{getDelayDisplay()}</div>
                    {data.config?.description && <div className="delay-description">{data.config.description}</div>}
                </div>
            </div>

            <Handle type="source" position={Position.Right} className="node-handle node-handle-source" id="delay-output" />

            <div className="node-status">
                {data.isValid ? <div className="status-indicator status-valid">✓</div> : <div className="status-indicator status-invalid">!</div>}
            </div>
        </div>
    );
};

export default DelayNode;
