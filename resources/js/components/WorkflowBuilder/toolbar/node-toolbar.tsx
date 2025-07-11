import React from 'react';
import { NodeTemplate } from '../types';

interface NodeToolbarProps {
    onCreateNode: (nodeType: string) => void;
}

const nodeTemplates: NodeTemplate[] = [
    {
        type: 'start',
        title: 'Start',
        description: 'Workflow starting point',
        icon: '🚀',
        category: 'Flow Control',
        defaultConfig: {},
    },
    {
        type: 'action',
        title: 'Action',
        description: 'Perform an action',
        icon: '⚡',
        category: 'Actions',
        defaultConfig: { type: 'assign_ticket' },
    },
    {
        type: 'condition',
        title: 'Condition',
        description: 'Check a condition',
        icon: '❓',
        category: 'Flow Control',
        defaultConfig: { type: 'field_equals' },
    },
    {
        type: 'ai',
        title: 'AI Process',
        description: 'AI-powered processing',
        icon: '🤖',
        category: 'AI & ML',
        defaultConfig: { action: 'categorize' },
    },
    {
        type: 'delay',
        title: 'Delay',
        description: 'Wait for a specified time',
        icon: '⏱️',
        category: 'Flow Control',
        defaultConfig: { seconds: 60, type: 'fixed' },
    },
    {
        type: 'end',
        title: 'End',
        description: 'Workflow endpoint',
        icon: '🏁',
        category: 'Flow Control',
        defaultConfig: { type: 'success' },
    },
];

const NodeToolbar: React.FC<NodeToolbarProps> = ({ onCreateNode }) => {
    const categories = [...new Set(nodeTemplates.map((template) => template.category))];

    const handleDragStart = (event: React.DragEvent, nodeType: string) => {
        event.dataTransfer.setData('application/reactflow', nodeType);
        event.dataTransfer.effectAllowed = 'move';
    };

    return (
        <div className="node-toolbar">
            <div className="toolbar-header">
                <h3>Node Library</h3>
                <p>Drag nodes to the canvas</p>
            </div>

            {categories.map((category) => (
                <div key={category} className="node-category">
                    <div className="category-header">
                        <h4>{category}</h4>
                    </div>

                    <div className="category-nodes">
                        {nodeTemplates
                            .filter((template) => template.category === category)
                            .map((template) => (
                                <div
                                    key={template.type}
                                    className="node-template"
                                    draggable
                                    onDragStart={(e) => handleDragStart(e, template.type)}
                                    onClick={() => onCreateNode(template.type)}
                                    title={template.description}
                                >
                                    <div className="template-icon">{template.icon}</div>
                                    <div className="template-info">
                                        <div className="template-title">{template.title}</div>
                                        <div className="template-description">{template.description}</div>
                                    </div>
                                </div>
                            ))}
                    </div>
                </div>
            ))}

            <div className="toolbar-footer">
                <div className="usage-hint">💡 Click or drag nodes to add them to your workflow</div>
            </div>
        </div>
    );
};

export default NodeToolbar;
