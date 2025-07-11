import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import axios from '@/lib/axios';
import { PageProps } from '@/types/page';
import { Head, Link } from '@inertiajs/react';
import { EditIcon, PauseIcon, PlayIcon, PlusIcon, TrashIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Workflow {
    id: number;
    name: string;
    description: string;
    is_active: boolean;
    trigger_type: string;
    created_at: string;
    executions_count: number;
}

export default function WorkflowsIndex({ auth: _auth }: PageProps) {
    const [workflows, setWorkflows] = useState<Workflow[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchWorkflows = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/api/workflows');
            setWorkflows(response.data.data || []);
            setError(null);
        } catch (err) {
            setError('Failed to load workflows');
            console.error('Failed to fetch workflows:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchWorkflows();
    }, []);

    const toggleWorkflow = async (workflowId: number) => {
        try {
            await axios.post(`/api/workflows/${workflowId}/toggle`);
            fetchWorkflows(); // Refresh the list
        } catch (err) {
            console.error('Failed to toggle workflow:', err);
        }
    };

    const executeWorkflow = async (workflowId: number) => {
        try {
            await axios.post(`/api/workflows/${workflowId}/execute`);
            alert('Workflow executed successfully!');
        } catch (err) {
            console.error('Failed to execute workflow:', err);
            alert('Failed to execute workflow');
        }
    };

    const deleteWorkflow = async (workflowId: number) => {
        if (confirm('Are you sure you want to delete this workflow?')) {
            try {
                await axios.delete(`/api/workflows/${workflowId}`);
                fetchWorkflows(); // Refresh the list
            } catch (err) {
                console.error('Failed to delete workflow:', err);
            }
        }
    };

    return (
        <AppLayout>
            <Head title="Workflow Automation - Intelligent Process Management" />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-foreground text-3xl font-bold">🤖 Workflow Automation</h1>
                        <p className="text-muted-foreground mt-2">Create and manage automated workflows with AI-powered decision making</p>
                    </div>
                    <Link href="/workflows/builder">
                        <Button>
                            <PlusIcon className="mr-2 h-4 w-4" />
                            Create Workflow
                        </Button>
                    </Link>
                </div>

                {/* AI Foundation Status Banner */}
                <Card className="mb-6 border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/20">
                    <CardHeader>
                        <CardTitle className="text-blue-800 dark:text-blue-200">🎯 Automation System Status</CardTitle>
                        <CardDescription className="text-blue-600 dark:text-blue-300">
                            AI categorization, smart routing, and semantic search are operational
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="flex items-center space-x-2">
                                <Badge variant="default" className="bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                    ✅ AI Categorization
                                </Badge>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Badge variant="default" className="bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                    ✅ Smart Assignment
                                </Badge>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Badge variant="default" className="bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                    ✅ Semantic Search
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {error && (
                    <Card className="border-destructive bg-destructive/10 mb-6">
                        <CardContent className="pt-6">
                            <p className="text-destructive">{error}</p>
                        </CardContent>
                    </Card>
                )}

                {loading ? (
                    <div className="py-8 text-center">
                        <div className="border-primary mx-auto h-8 w-8 animate-spin rounded-full border-b-2"></div>
                        <p className="text-muted-foreground mt-2">Loading workflows...</p>
                    </div>
                ) : workflows.length === 0 ? (
                    <Card className="border-border bg-card">
                        <CardContent className="py-8 text-center">
                            <h3 className="text-foreground mb-2 text-lg font-semibold">No workflows found</h3>
                            <p className="text-muted-foreground mb-4">Create your first automated workflow to get started.</p>
                            <Link href="/workflows/builder">
                                <Button>
                                    <PlusIcon className="mr-2 h-4 w-4" />
                                    Create Your First Workflow
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {workflows.map((workflow) => (
                            <Card key={workflow.id} className="border-border bg-card transition-shadow hover:shadow-lg">
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-foreground text-lg">{workflow.name}</CardTitle>
                                            <CardDescription className="mt-1">{workflow.description || 'No description'}</CardDescription>
                                        </div>
                                        <Badge
                                            variant={workflow.is_active ? 'default' : 'secondary'}
                                            className={
                                                workflow.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : ''
                                            }
                                        >
                                            {workflow.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        <div className="text-muted-foreground text-sm">
                                            <p>
                                                <strong>Trigger:</strong> {workflow.trigger_type}
                                            </p>
                                            <p>
                                                <strong>Executions:</strong> {workflow.executions_count || 0}
                                            </p>
                                            <p>
                                                <strong>Created:</strong> {new Date(workflow.created_at).toLocaleDateString()}
                                            </p>
                                        </div>

                                        <div className="flex space-x-2">
                                            <Button variant="outline" size="sm" onClick={() => executeWorkflow(workflow.id)} className="flex-1">
                                                <PlayIcon className="mr-1 h-3 w-3" />
                                                Execute
                                            </Button>
                                            <Button variant="outline" size="sm" onClick={() => toggleWorkflow(workflow.id)}>
                                                {workflow.is_active ? <PauseIcon className="h-3 w-3" /> : <PlayIcon className="h-3 w-3" />}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => window.open(`/workflows/builder?edit=${workflow.id}`, '_blank')}
                                            >
                                                <EditIcon className="h-3 w-3" />
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => deleteWorkflow(workflow.id)}
                                                className="text-destructive hover:text-destructive"
                                            >
                                                <TrashIcon className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
