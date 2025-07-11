import {
    addEdge,
    Background,
    Connection,
    Controls,
    Edge,
    EdgeTypes,
    Node,
    NodeTypes,
    ReactFlow,
    ReactFlowProvider,
    useEdgesState,
    useNodesState,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import React, { useCallback, useMemo, useState } from 'react';

// Custom Node Components
import ActionNode from './nodes/action-node';
import AINode from './nodes/ai-node';
import ConditionNode from './nodes/condition-node';
import DelayNode from './nodes/delay-node';
import EndNode from './nodes/end-node';
import StartNode from './nodes/start-node';

// Custom Edge Components
import CustomEdge from './edges/custom-edge';

// Toolbar Components
import NodeToolbar from './toolbar/node-toolbar';

// Types
import { WorkflowData, WorkflowEdgeData, WorkflowNodeData, WorkflowSettings } from './types';

// Styles
import './workflow-builder.css';

interface WorkflowBuilderProps {
    initialWorkflow?: WorkflowData;
    onSave: (workflow: WorkflowData) => void;
    onTest: (workflow: WorkflowData) => void;
    readonly?: boolean;
}

const nodeTypes: NodeTypes = {
    start: StartNode,
    action: ActionNode,
    condition: ConditionNode,
    ai: AINode,
    delay: DelayNode,
    end: EndNode,
};

const edgeTypes: EdgeTypes = {
    custom: CustomEdge,
};

const WorkflowBuilder: React.FC<WorkflowBuilderProps> = ({ initialWorkflow, onSave, onTest, readonly = false }) => {
    const [nodes, setNodes, onNodesChange] = useNodesState<Node>([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState<Edge>([]);
    const [selectedNode, setSelectedNode] = useState<Node<WorkflowNodeData> | null>(null);
    const [workflowSettings, setWorkflowSettings] = useState<WorkflowSettings>({
        name: '',
        description: '',
        triggerType: 'manual',
        triggerConditions: {},
        isActive: true,
    });

    // Initialize workflow data
    React.useEffect(() => {
        if (initialWorkflow) {
            const initialNodes: Node[] = initialWorkflow.nodes.map((node) => ({
                id: node.id,
                type: node.type,
                position: node.position,
                data: node.data,
            }));

            const initialEdges: Edge[] = initialWorkflow.edges.map((edge) => ({
                id: edge.id,
                source: edge.source,
                target: edge.target,
                type: edge.type,
                data: edge.data,
            }));

            setNodes(initialNodes);
            setEdges(initialEdges);
            setWorkflowSettings({
                name: initialWorkflow.name || '',
                description: initialWorkflow.description || '',
                triggerType: initialWorkflow.triggerType || 'manual',
                triggerConditions: initialWorkflow.triggerConditions || {},
                isActive: initialWorkflow.isActive ?? true,
            });
        }
    }, [initialWorkflow, setNodes, setEdges]);

    // Handle node connections
    const onConnect = useCallback(
        (connection: Connection) => {
            const edge: Edge = {
                ...connection,
                id: `${connection.source}-${connection.target}`,
                type: 'custom',
                data: {
                    label: '',
                    condition: null,
                },
            };
            setEdges((eds) => addEdge(edge, eds));
        },
        [setEdges],
    );

    // Handle node selection
    const onNodeClick = useCallback((event: React.MouseEvent, node: Node) => {
        setSelectedNode(node as Node<WorkflowNodeData>);
    }, []);

    // Handle node creation from toolbar
    const onCreateNode = useCallback(
        (nodeType: string) => {
            const nodeId = `${nodeType}-${Date.now()}`;
            const newNode: Node = {
                id: nodeId,
                type: nodeType,
                position: { x: 100, y: 100 },
                data: {
                    label: nodeType.charAt(0).toUpperCase() + nodeType.slice(1),
                    config: {},
                    isValid: nodeType === 'start' || nodeType === 'end',
                },
            };

            setNodes((nds) => [...nds, newNode]);
            setSelectedNode(newNode as Node<WorkflowNodeData>);
        },
        [setNodes],
    );

    // Handle node data updates
    const onNodeDataChange = useCallback(
        (nodeId: string, newData: Partial<WorkflowNodeData>) => {
            setNodes((nds) => nds.map((node) => (node.id === nodeId ? { ...node, data: { ...(node.data as WorkflowNodeData), ...newData } } : node)));

            // Update selected node if it's the one being changed
            if (selectedNode && selectedNode.id === nodeId) {
                setSelectedNode((prev) => (prev ? { ...prev, data: { ...(prev.data as WorkflowNodeData), ...newData } } : null));
            }
        },
        [setNodes, selectedNode],
    );

    // Handle edge data updates (reserved for future edge property editing)
    // const onEdgeDataChange = useCallback(
    //     (edgeId: string, newData: Partial<WorkflowEdgeData>) => {
    //         setEdges((eds) => eds.map((edge) => (edge.id === edgeId ? { ...edge, data: { ...(edge.data as WorkflowEdgeData), ...newData } } : edge)));
    //     },
    //     [setEdges],
    // );

    // Validate workflow
    const validateWorkflow = useCallback(() => {
        const errors: string[] = [];

        // Check for start node
        const startNodes = nodes.filter((node) => node.type === 'start');
        if (startNodes.length === 0) {
            errors.push('Workflow must have a start node');
        } else if (startNodes.length > 1) {
            errors.push('Workflow can only have one start node');
        }

        // Check for end node
        const endNodes = nodes.filter((node) => node.type === 'end');
        if (endNodes.length === 0) {
            errors.push('Workflow must have at least one end node');
        }

        // Check for disconnected nodes
        const connectedNodeIds = new Set([...edges.map((edge) => edge.source), ...edges.map((edge) => edge.target)]);

        const disconnectedNodes = nodes.filter((node) => node.type !== 'start' && node.type !== 'end' && !connectedNodeIds.has(node.id));

        if (disconnectedNodes.length > 0) {
            errors.push(`Disconnected nodes: ${disconnectedNodes.map((n) => (n.data as WorkflowNodeData).label).join(', ')}`);
        }

        return errors;
    }, [nodes, edges]);

    // Save workflow
    const handleSave = useCallback(() => {
        const errors = validateWorkflow();
        if (errors.length > 0) {
            alert(`Workflow validation errors:\n${errors.join('\n')}`);
            return;
        }

        const workflowData: WorkflowData = {
            name: workflowSettings.name,
            description: workflowSettings.description,
            triggerType: workflowSettings.triggerType,
            triggerConditions: workflowSettings.triggerConditions,
            isActive: workflowSettings.isActive,
            nodes: nodes.map((node) => ({
                id: node.id,
                type: node.type || 'unknown',
                position: node.position,
                data: node.data as WorkflowNodeData,
            })),
            edges: edges.map((edge) => ({
                id: edge.id,
                source: edge.source,
                target: edge.target,
                type: edge.type,
                data: edge.data as WorkflowEdgeData,
            })),
        };

        onSave(workflowData);
    }, [workflowSettings, nodes, edges, validateWorkflow, onSave]);

    // Test workflow
    const handleTest = useCallback(() => {
        const errors = validateWorkflow();
        if (errors.length > 0) {
            alert(`Workflow validation errors:\n${errors.join('\n')}`);
            return;
        }

        const workflowData: WorkflowData = {
            name: workflowSettings.name,
            description: workflowSettings.description,
            triggerType: workflowSettings.triggerType,
            triggerConditions: workflowSettings.triggerConditions,
            isActive: workflowSettings.isActive,
            nodes: nodes.map((node) => ({
                id: node.id,
                type: node.type || 'unknown',
                position: node.position,
                data: node.data as WorkflowNodeData,
            })),
            edges: edges.map((edge) => ({
                id: edge.id,
                source: edge.source,
                target: edge.target,
                type: edge.type,
                data: edge.data as WorkflowEdgeData,
            })),
        };

        onTest(workflowData);
    }, [workflowSettings, nodes, edges, validateWorkflow, onTest]);

    // Clear selection when clicking on canvas
    const onPaneClick = useCallback(() => {
        setSelectedNode(null);
    }, []);

    const validationErrors = useMemo(() => validateWorkflow(), [validateWorkflow]);

    return (
        <div className="workflow-builder">
            <div className="workflow-builder-header">
                <h2>Workflow Builder</h2>
                <div className="workflow-builder-actions">
                    {validationErrors.length > 0 && (
                        <div className="validation-errors">
                            <span className="error-icon">⚠️</span>
                            <span>{validationErrors.length} validation error(s)</span>
                        </div>
                    )}
                    <button onClick={handleTest} disabled={readonly || validationErrors.length > 0} className="btn btn-secondary">
                        Test Workflow
                    </button>
                    <button onClick={handleSave} disabled={readonly || validationErrors.length > 0} className="btn btn-primary">
                        Save Workflow
                    </button>
                </div>
            </div>

            <div className="workflow-builder-content">
                <div className="workflow-builder-sidebar">
                    <div className="workflow-settings">
                        <h3>Workflow Settings</h3>
                        <div className="form-group">
                            <label htmlFor="workflow-name">Name</label>
                            <input
                                id="workflow-name"
                                type="text"
                                value={workflowSettings.name}
                                onChange={(e) => setWorkflowSettings((prev) => ({ ...prev, name: e.target.value }))}
                                disabled={readonly}
                                className="form-control"
                                placeholder="Enter workflow name"
                            />
                        </div>
                        <div className="form-group">
                            <label htmlFor="workflow-description">Description</label>
                            <textarea
                                id="workflow-description"
                                value={workflowSettings.description}
                                onChange={(e) => setWorkflowSettings((prev) => ({ ...prev, description: e.target.value }))}
                                disabled={readonly}
                                className="form-control"
                                placeholder="Enter workflow description"
                                rows={3}
                            />
                        </div>
                        <div className="form-group">
                            <label htmlFor="trigger-type">Trigger Type</label>
                            <select
                                id="trigger-type"
                                value={workflowSettings.triggerType}
                                onChange={(e) =>
                                    setWorkflowSettings((prev) => ({ ...prev, triggerType: e.target.value as WorkflowSettings['triggerType'] }))
                                }
                                disabled={readonly}
                                className="form-control"
                            >
                                <option value="manual">Manual</option>
                                <option value="automatic">Automatic</option>
                                <option value="schedule">Schedule</option>
                                <option value="webhook">Webhook</option>
                            </select>
                        </div>
                        <div className="form-group">
                            <label className="checkbox-label">
                                <input
                                    type="checkbox"
                                    checked={workflowSettings.isActive}
                                    onChange={(e) => setWorkflowSettings((prev) => ({ ...prev, isActive: e.target.checked }))}
                                    disabled={readonly}
                                />
                                Active
                            </label>
                        </div>
                    </div>

                    {!readonly && <NodeToolbar onCreateNode={onCreateNode} />}

                    {selectedNode && (
                        <div className="properties-panel">
                            <h3>Node Properties</h3>
                            <div className="form-group">
                                <label>Node Type</label>
                                <input type="text" value={selectedNode.type || 'unknown'} disabled className="form-control" />
                            </div>
                            <div className="form-group">
                                <label>Label</label>
                                <input
                                    type="text"
                                    value={(selectedNode.data as WorkflowNodeData).label}
                                    onChange={(e) => onNodeDataChange(selectedNode.id, { label: e.target.value })}
                                    disabled={readonly}
                                    className="form-control"
                                />
                            </div>
                            <div className="form-group">
                                <label>Description</label>
                                <textarea
                                    value={(selectedNode.data as WorkflowNodeData).description || ''}
                                    onChange={(e) => onNodeDataChange(selectedNode.id, { description: e.target.value })}
                                    disabled={readonly}
                                    className="form-control"
                                    rows={3}
                                />
                            </div>
                        </div>
                    )}
                </div>

                <div className="workflow-builder-canvas">
                    <ReactFlowProvider>
                        <ReactFlow
                            nodes={nodes}
                            edges={edges}
                            onNodesChange={onNodesChange}
                            onEdgesChange={onEdgesChange}
                            onConnect={onConnect}
                            onNodeClick={onNodeClick}
                            onPaneClick={onPaneClick}
                            nodeTypes={nodeTypes}
                            edgeTypes={edgeTypes}
                            fitView
                            attributionPosition="bottom-left"
                            className="workflow-canvas"
                            nodesDraggable={!readonly}
                            nodesConnectable={!readonly}
                            elementsSelectable={!readonly}
                        >
                            <Background />
                            <Controls />
                        </ReactFlow>
                    </ReactFlowProvider>
                </div>
            </div>
        </div>
    );
};

export default WorkflowBuilder;
