import { Handle, Position } from '@xyflow/react';
import React from 'react';
import { WorkflowNodeData } from '../types';

interface ActionNodeProps {
    data: WorkflowNodeData;
    selected?: boolean;
}

const ActionNode: React.FC<ActionNodeProps> = ({ data, selected }) => {
    const getActionIcon = (actionType: string) => {
        switch (actionType) {
            case 'assign_ticket':
                return '👤';
            case 'update_ticket':
                return '📝';
            case 'send_notification':
                return '📢';
            case 'send_email':
                return '📧';
            case 'create_knowledge_article':
                return '📚';
            default:
                return '⚡';
        }
    };

    const getActionTitle = (actionType: string) => {
        switch (actionType) {
            case 'assign_ticket':
                return 'Assign Ticket';
            case 'update_ticket':
                return 'Update Ticket';
            case 'send_notification':
                return 'Send Notification';
            case 'send_email':
                return 'Send Email';
            case 'create_knowledge_article':
                return 'Create Article';
            default:
                return 'Action';
        }
    };

    const actionType = data.config?.type || 'unknown';
    const actionTitle = data.config?.title || getActionTitle(actionType);
    const actionIcon = getActionIcon(actionType);

    return (
        <div className={`workflow-node action-node ${selected ? 'selected' : ''}`}>
            <Handle type="target" position={Position.Left} className="node-handle node-handle-target" id="action-input" />

            <div className="node-header">
                <div className="node-icon">{actionIcon}</div>
                <div className="node-title">{actionTitle}</div>
            </div>

            <div className="node-content">
                <div className="node-description">{data.description || data.config?.description || 'Perform an action'}</div>

                {data.config?.summary && <div className="node-summary">{data.config.summary}</div>}
            </div>

            <Handle type="source" position={Position.Right} className="node-handle node-handle-source" id="action-output" />

            <div className="node-status">
                {data.isValid ? <div className="status-indicator status-valid">✓</div> : <div className="status-indicator status-invalid">!</div>}
            </div>
        </div>
    );
};

export default ActionNode;
