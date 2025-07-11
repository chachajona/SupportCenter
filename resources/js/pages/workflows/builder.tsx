import { WorkflowData } from '@/components/WorkflowBuilder/types';
import WorkflowBuilder from '@/components/WorkflowBuilder/workflow-builder';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import axios from '@/lib/axios';
import { PageProps } from '@/types/page';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function WorkflowBuilderPage({ auth: _auth }: PageProps) {
    const [initialWorkflow, setInitialWorkflow] = useState<WorkflowData | undefined>(undefined);
    const [loading, setLoading] = useState(false);

    // Check if we're editing an existing workflow
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const editId = urlParams.get('edit');

        if (editId) {
            setLoading(true);
            axios
                .get(`/api/workflows/${editId}`)
                .then((response) => {
                    const workflow = response.data.data;
                    setInitialWorkflow({
                        name: workflow.name,
                        description: workflow.description,
                        triggerType: workflow.trigger_type || 'manual',
                        triggerConditions: workflow.trigger_conditions || {},
                        isActive: workflow.is_active || false,
                        nodes: workflow.workflow_data?.nodes || [],
                        edges: workflow.workflow_data?.edges || [],
                    });
                })
                .catch((error) => {
                    console.error('Failed to load workflow:', error);
                })
                .finally(() => {
                    setLoading(false);
                });
        }
    }, []);

    const handleSave = async (workflowData: WorkflowData) => {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');

            const payload = {
                name: workflowData.name,
                description: workflowData.description,
                trigger_type: workflowData.triggerType,
                trigger_conditions: workflowData.triggerConditions,
                is_active: workflowData.isActive,
                workflow_data: {
                    nodes: workflowData.nodes,
                    edges: workflowData.edges,
                },
            };

            if (editId) {
                // Update existing workflow
                await axios.put(`/api/workflows/${editId}`, payload);
                alert('Workflow updated successfully!');
            } else {
                // Create new workflow
                await axios.post('/api/workflows', payload);
                alert('Workflow created successfully!');
            }

            // Redirect back to workflows list
            router.visit('/workflows');
        } catch (error) {
            console.error('Failed to save workflow:', error);
            alert('Failed to save workflow. Please try again.');
        }
    };

    const handleTest = async (workflowData: WorkflowData) => {
        try {
            // For testing, we'll send the workflow data to the test endpoint
            const payload = {
                name: workflowData.name || 'Test Workflow',
                workflow_data: {
                    nodes: workflowData.nodes,
                    edges: workflowData.edges,
                },
            };

            const response = await axios.post('/api/workflows/test', payload);

            if (response.data.success) {
                alert(`Workflow test completed!\n\nResult: ${response.data.message}\nExecution ID: ${response.data.execution_id || 'N/A'}`);
            } else {
                alert(`Workflow test failed: ${response.data.message}`);
            }
        } catch (error) {
            console.error('Failed to test workflow:', error);
            alert('Failed to test workflow. Please check the console for details.');
        }
    };

    if (loading) {
        return (
            <AppLayout>
                <Head title="Loading Workflow Builder..." />
                <div className="flex h-screen items-center justify-center">
                    <div className="text-center">
                        <div className="border-primary mx-auto h-8 w-8 animate-spin rounded-full border-b-2"></div>
                        <p className="text-muted-foreground mt-2">Loading workflow...</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title="Workflow Builder - Visual Automation Designer" />

            <div className="flex h-screen flex-col">
                {/* Header Bar */}
                <div className="border-border bg-card flex items-center justify-between border-b px-4 py-3">
                    <div className="flex items-center space-x-4">
                        <Link href="/workflows">
                            <Button variant="outline" size="sm">
                                <ArrowLeftIcon className="mr-2 h-4 w-4" />
                                Back to Workflows
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-foreground text-xl font-semibold">🤖 Workflow Builder</h1>
                            <p className="text-muted-foreground text-sm">Visual drag-and-drop automation designer with AI nodes</p>
                        </div>
                    </div>

                    <div className="flex items-center space-x-2">
                        <div className="text-muted-foreground text-sm">💡 Tip: Drag AI nodes from the toolbar to create intelligent workflows</div>
                    </div>
                </div>

                {/* Workflow Builder */}
                <div className="flex-1">
                    <WorkflowBuilder initialWorkflow={initialWorkflow} onSave={handleSave} onTest={handleTest} />
                </div>
            </div>
        </AppLayout>
    );
}
