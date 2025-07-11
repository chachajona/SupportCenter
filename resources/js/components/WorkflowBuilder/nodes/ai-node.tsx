import { Handle, Position } from '@xyflow/react';
import React from 'react';
import { WorkflowNodeData } from '../types';

interface AINodeProps {
    data: WorkflowNodeData;
    selected?: boolean;
}

const AINode: React.FC<AINodeProps> = ({ data, selected }) => {
    const getAIIcon = (aiType: string) => {
        switch (aiType) {
            case 'categorize':
                return '🎯';
            case 'suggest_response':
                return '💬';
            case 'predict_escalation':
                return '⚠️';
            case 'sentiment_analysis':
                return '😊';
            case 'auto_translate':
                return '🌍';
            default:
                return '🤖';
        }
    };

    const getAITitle = (aiType: string) => {
        switch (aiType) {
            case 'categorize':
                return 'AI Categorize';
            case 'suggest_response':
                return 'AI Suggest Response';
            case 'predict_escalation':
                return 'AI Predict Escalation';
            case 'sentiment_analysis':
                return 'AI Sentiment Analysis';
            case 'auto_translate':
                return 'AI Translate';
            default:
                return 'AI Process';
        }
    };

    const aiType = data.config?.action || data.config?.type || 'unknown';
    const aiTitle = data.config?.title || getAITitle(aiType);
    const aiIcon = getAIIcon(aiType);

    return (
        <div className={`workflow-node ai-node ${selected ? 'selected' : ''}`}>
            <Handle type="target" position={Position.Left} className="node-handle node-handle-target" id="ai-input" />

            <div className="node-header">
                <div className="node-icon">{aiIcon}</div>
                <div className="node-title">{aiTitle}</div>
            </div>

            <div className="node-content">
                <div className="node-description">{data.description || data.config?.description || 'AI processing'}</div>

                {data.config?.confidence_threshold && <div className="node-summary">Confidence: {data.config.confidence_threshold}%</div>}
            </div>

            <Handle type="source" position={Position.Right} className="node-handle node-handle-source" id="ai-output" />

            <div className="node-status">
                {data.isValid ? <div className="status-indicator status-valid">✓</div> : <div className="status-indicator status-invalid">!</div>}
            </div>
        </div>
    );
};

export default AINode;
