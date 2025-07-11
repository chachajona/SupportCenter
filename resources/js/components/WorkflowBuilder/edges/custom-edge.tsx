import { BaseEdge, EdgeLabelRenderer, EdgeProps, getBezierPath } from '@xyflow/react';
import React from 'react';
import { WorkflowEdgeData } from '../types';

const CustomEdge: React.FC<EdgeProps> = ({ id, sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition, style = {}, data, selected }) => {
    const edgeData = (data as WorkflowEdgeData) || { label: '', condition: null };

    const [edgePath, labelX, labelY] = getBezierPath({
        sourceX,
        sourceY,
        sourcePosition,
        targetX,
        targetY,
        targetPosition,
    });

    const getEdgeColor = () => {
        if (edgeData.condition === 'true') {
            return '#10b981'; // Green for true condition
        } else if (edgeData.condition === 'false') {
            return '#ef4444'; // Red for false condition
        }
        return '#6b7280'; // Gray for default
    };

    const getEdgeStyle = () => ({
        ...style,
        stroke: getEdgeColor(),
        strokeWidth: selected ? 3 : 2,
        strokeDasharray: edgeData.condition ? '0' : '5,5',
    });

    return (
        <>
            <BaseEdge id={id} path={edgePath} style={getEdgeStyle()} />

            {edgeData.label && (
                <EdgeLabelRenderer>
                    <div
                        style={{
                            position: 'absolute',
                            transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
                            fontSize: 12,
                            pointerEvents: 'all',
                        }}
                        className={`edge-label ${selected ? 'selected' : ''}`}
                    >
                        <div className="edge-label-content">{edgeData.label}</div>
                    </div>
                </EdgeLabelRenderer>
            )}

            {edgeData.condition && (
                <EdgeLabelRenderer>
                    <div
                        style={{
                            position: 'absolute',
                            transform: `translate(-50%, -50%) translate(${labelX}px,${labelY - 20}px)`,
                            fontSize: 10,
                            pointerEvents: 'all',
                        }}
                        className={`condition-label condition-${edgeData.condition}`}
                    >
                        {edgeData.condition.toUpperCase()}
                    </div>
                </EdgeLabelRenderer>
            )}
        </>
    );
};

export default CustomEdge;
