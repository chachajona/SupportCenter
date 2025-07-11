/* eslint-disable @typescript-eslint/no-explicit-any */
export interface WorkflowNodeData extends Record<string, unknown> {
    label: string;
    config: Record<string, any>;
    isValid: boolean;
    description?: string;
    icon?: string;
}

export interface WorkflowEdgeData extends Record<string, unknown> {
    label: string;
    condition?: string | null;
    conditionConfig?: Record<string, any>;
}

export interface WorkflowNode {
    id: string;
    type: string;
    position: { x: number; y: number };
    data: WorkflowNodeData;
}

export interface WorkflowEdge {
    id: string;
    source: string;
    target: string;
    type?: string;
    data: WorkflowEdgeData;
}

export interface WorkflowData {
    name: string;
    description: string;
    triggerType: 'manual' | 'automatic' | 'schedule' | 'webhook';
    triggerConditions: Record<string, any>;
    isActive: boolean;
    nodes: WorkflowNode[];
    edges: WorkflowEdge[];
}

export interface WorkflowSettings {
    name: string;
    description: string;
    triggerType: 'manual' | 'automatic' | 'schedule' | 'webhook';
    triggerConditions: Record<string, any>;
    isActive: boolean;
}

export interface ActionConfig {
    type: string;
    title: string;
    description: string;
    icon: string;
    fields: ActionField[];
}

export interface ActionField {
    name: string;
    type: 'text' | 'select' | 'number' | 'boolean' | 'textarea' | 'multiselect' | 'json';
    label: string;
    required: boolean;
    options?: Array<{ value: string; label: string }>;
    placeholder?: string;
    description?: string;
    validation?: {
        min?: number;
        max?: number;
        pattern?: string;
    };
}

export interface ConditionConfig {
    type: string;
    title: string;
    description: string;
    icon: string;
    fields: ConditionField[];
}

export interface ConditionField {
    name: string;
    type: 'text' | 'select' | 'number' | 'boolean' | 'date' | 'time';
    label: string;
    required: boolean;
    options?: Array<{ value: string; label: string }>;
    placeholder?: string;
    description?: string;
}

export interface AIConfig {
    type: string;
    title: string;
    description: string;
    icon: string;
    fields: AIField[];
}

export interface AIField {
    name: string;
    type: 'text' | 'select' | 'number' | 'boolean' | 'textarea';
    label: string;
    required: boolean;
    options?: Array<{ value: string; label: string }>;
    placeholder?: string;
    description?: string;
}

export interface DelayConfig {
    type: string;
    title: string;
    description: string;
    icon: string;
    fields: DelayField[];
}

export interface DelayField {
    name: string;
    type: 'number' | 'select';
    label: string;
    required: boolean;
    options?: Array<{ value: string; label: string }>;
    placeholder?: string;
    description?: string;
    min?: number;
    max?: number;
}

export interface NodeTemplate {
    type: string;
    title: string;
    description: string;
    icon: string;
    category: string;
    defaultConfig: Record<string, any>;
}

export interface WorkflowTemplate {
    id: string;
    name: string;
    description: string;
    category: string;
    template_data: {
        nodes: WorkflowNode[];
        edges: WorkflowEdge[];
        settings: WorkflowSettings;
    };
}

export interface ValidationError {
    type: 'error' | 'warning';
    message: string;
    nodeId?: string;
    edgeId?: string;
}

export interface WorkflowExecution {
    id: string;
    workflow_id: string;
    status: 'running' | 'completed' | 'failed' | 'cancelled';
    started_at: string;
    completed_at?: string;
    execution_result?: Record<string, any>;
    error_message?: string;
    triggered_by: string;
}

export interface WorkflowAction {
    id: string;
    workflow_execution_id: string;
    action_type: string;
    action_data: Record<string, any>;
    status: 'pending' | 'running' | 'completed' | 'failed' | 'skipped';
    started_at?: string;
    completed_at?: string;
    result?: Record<string, any>;
    error_message?: string;
}
