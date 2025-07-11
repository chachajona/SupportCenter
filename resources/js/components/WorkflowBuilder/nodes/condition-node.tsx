import { Handle, Position } from '@xyflow/react';
import React from 'react';
import { WorkflowNodeData } from '../types';

interface ConditionNodeProps {
    data: WorkflowNodeData;
    selected?: boolean;
}

const ConditionNode: React.FC<ConditionNodeProps> = ({ data, selected }) => {
    const getConditionIcon = (conditionType: string) => {
        switch (conditionType) {
            case 'field_equals':
                return '=';
            case 'field_contains':
                return '⊃';
            case 'field_greater_than':
                return '>';
            case 'field_less_than':
                return '<';
            case 'field_in_list':
                return '∈';
            case 'time_based':
                return '⏰';
            case 'user_based':
                return '👤';
            default:
                return '❓';
        }
    };

    const getConditionTitle = (conditionType: string) => {
        switch (conditionType) {
            case 'field_equals':
                return 'Field Equals';
            case 'field_contains':
                return 'Field Contains';
            case 'field_greater_than':
                return 'Field Greater Than';
            case 'field_less_than':
                return 'Field Less Than';
            case 'field_in_list':
                return 'Field In List';
            case 'time_based':
                return 'Time Based';
            case 'user_based':
                return 'User Based';
            default:
                return 'Condition';
        }
    };

    const conditionType = data.config?.type || 'unknown';
    const conditionTitle = data.config?.title || getConditionTitle(conditionType);
    const conditionIcon = getConditionIcon(conditionType);

    return (
        <div className={`workflow-node condition-node ${selected ? 'selected' : ''}`}>
            <Handle type="target" position={Position.Left} className="node-handle node-handle-target" id="condition-input" />

            <div className="node-header">
                <div className="node-icon">{conditionIcon}</div>
                <div className="node-title">{conditionTitle}</div>
            </div>

            <div className="node-content">
                <div className="node-description">{data.description || data.config?.description || 'Check a condition'}</div>

                {data.config?.summary && <div className="node-summary">{data.config.summary}</div>}
            </div>

            <div className="condition-outputs">
                <Handle
                    type="source"
                    position={Position.Right}
                    className="node-handle node-handle-source condition-true"
                    id="condition-true"
                    style={{ top: '35%' }}
                />
                <div className="condition-label condition-true-label">True</div>

                <Handle
                    type="source"
                    position={Position.Right}
                    className="node-handle node-handle-source condition-false"
                    id="condition-false"
                    style={{ top: '65%' }}
                />
                <div className="condition-label condition-false-label">False</div>
            </div>

            <div className="node-status">
                {data.isValid ? <div className="status-indicator status-valid">✓</div> : <div className="status-indicator status-invalid">!</div>}
            </div>
        </div>
    );
};

export default ConditionNode;
