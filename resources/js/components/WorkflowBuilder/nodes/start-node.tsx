import { Handle, Position } from '@xyflow/react';
import React from 'react';
import { WorkflowNodeData } from '../types';

interface StartNodeProps {
    data: WorkflowNodeData;
    selected?: boolean;
}

const StartNode: React.FC<StartNodeProps> = ({ data, selected }) => {
    return (
        <div className={`workflow-node start-node ${selected ? 'selected' : ''}`}>
            <div className="node-header">
                <div className="node-icon">🚀</div>
                <div className="node-title">Start</div>
            </div>

            <div className="node-content">
                <div className="node-description">{data.description || 'Workflow starting point'}</div>
            </div>

            <Handle type="source" position={Position.Right} className="node-handle node-handle-source" id="start-output" />

            <div className="node-status">
                {data.isValid ? <div className="status-indicator status-valid">✓</div> : <div className="status-indicator status-invalid">!</div>}
            </div>
        </div>
    );
};

export default StartNode;
