import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Permission } from '@/types/rbac';
import { router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import React, { useEffect, useState } from 'react';

interface PermissionEditDialogProps {
    permission: Permission | null;
    open: boolean;
    onClose: () => void;
}

export function PermissionEditDialog({ permission, open, onClose }: PermissionEditDialogProps) {
    const [formData, setFormData] = useState({
        name: '',
        display_name: '',
        description: '',
        resource: '',
        action: '',
        is_active: true,
    });
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const isEditing = !!permission;

    // Common resource types
    const commonResources = [
        'tickets',
        'users',
        'roles',
        'permissions',
        'departments',
        'settings',
        'reports',
        'system',
        'knowledge_base',
        'templates',
        'workflows',
        'assets',
    ];

    // Common action types
    const commonActions = [
        'create',
        'read',
        'view',
        'edit',
        'update',
        'delete',
        'manage',
        'assign',
        'unassign',
        'approve',
        'reject',
        'publish',
        'archive',
        'export',
        'import',
    ];

    useEffect(() => {
        if (permission) {
            setFormData({
                name: permission.name,
                display_name: permission.display_name,
                description: permission.description || '',
                resource: permission.resource,
                action: permission.action,
                is_active: permission.is_active,
            });
        } else {
            setFormData({
                name: '',
                display_name: '',
                description: '',
                resource: '',
                action: '',
                is_active: true,
            });
        }
        setErrors({});
    }, [permission, open]);

    // Auto-generate permission name when resource and action change
    useEffect(() => {
        if (formData.resource && formData.action && !isEditing) {
            const generatedName = `${formData.resource}.${formData.action}`;
            setFormData((prev) => ({ ...prev, name: generatedName }));
        }
    }, [formData.resource, formData.action, isEditing]);

    // Auto-generate display name when name changes
    useEffect(() => {
        if (formData.name && !isEditing) {
            const parts = formData.name.split('.');
            if (parts.length >= 2) {
                const resource = parts[0].replace(/_/g, ' ');
                const action = parts[1].replace(/_/g, ' ');
                const displayName = `${action.charAt(0).toUpperCase() + action.slice(1)} ${resource}`;
                setFormData((prev) => ({ ...prev, display_name: displayName }));
            }
        }
    }, [formData.name, isEditing]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const url = isEditing ? `/admin/permissions/${permission.id}` : '/admin/permissions';
        const method = isEditing ? 'put' : 'post';

        router[method](url, formData, {
            onSuccess: () => {
                onClose();
            },
            onError: (errors) => {
                setErrors(errors as unknown as Record<string, string[]>);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    const handleResourceChange = (value: string) => {
        setFormData((prev) => ({ ...prev, resource: value }));
    };

    const handleActionChange = (value: string) => {
        setFormData((prev) => ({ ...prev, action: value }));
    };

    const getPermissionPreview = () => {
        if (formData.resource && formData.action) {
            return `${formData.resource}.${formData.action}`;
        }
        return 'resource.action';
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit Permission' : 'Create New Permission'}</DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the permission details and settings.'
                            : 'Create a new permission by defining its resource, action, and details.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Permission Preview */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm">Permission Preview</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <Badge variant="outline" className="font-mono">
                                    {getPermissionPreview()}
                                </Badge>
                                <span className="text-muted-foreground text-sm">This is how the permission will be referenced in the system</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Resource and Action */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="resource">Resource *</Label>
                            <Select value={formData.resource} onValueChange={handleResourceChange}>
                                <SelectTrigger className={errors.resource ? 'border-red-500' : ''}>
                                    <SelectValue placeholder="Select a resource" />
                                </SelectTrigger>
                                <SelectContent>
                                    {commonResources.map((resource) => (
                                        <SelectItem key={resource} value={resource}>
                                            {resource.replace(/_/g, ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {!commonResources.includes(formData.resource) && formData.resource && (
                                <Input
                                    value={formData.resource}
                                    onChange={(e) => setFormData({ ...formData, resource: e.target.value })}
                                    placeholder="Custom resource name"
                                    className={errors.resource ? 'border-red-500' : ''}
                                />
                            )}
                            {errors.resource && <p className="text-sm text-red-500">{errors.resource[0]}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="action">Action *</Label>
                            <Select value={formData.action} onValueChange={handleActionChange}>
                                <SelectTrigger className={errors.action ? 'border-red-500' : ''}>
                                    <SelectValue placeholder="Select an action" />
                                </SelectTrigger>
                                <SelectContent>
                                    {commonActions.map((action) => (
                                        <SelectItem key={action} value={action}>
                                            {action.replace(/_/g, ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {!commonActions.includes(formData.action) && formData.action && (
                                <Input
                                    value={formData.action}
                                    onChange={(e) => setFormData({ ...formData, action: e.target.value })}
                                    placeholder="Custom action name"
                                    className={errors.action ? 'border-red-500' : ''}
                                />
                            )}
                            {errors.action && <p className="text-sm text-red-500">{errors.action[0]}</p>}
                        </div>
                    </div>

                    {/* Permission Name */}
                    <div className="space-y-2">
                        <Label htmlFor="name">Permission Name *</Label>
                        <Input
                            id="name"
                            value={formData.name}
                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            placeholder="e.g., tickets.create"
                            className={errors.name ? 'border-red-500' : ''}
                            readOnly={!isEditing && !!formData.resource && !!formData.action}
                        />
                        <p className="text-muted-foreground text-xs">
                            {!isEditing && formData.resource && formData.action
                                ? 'Auto-generated from resource and action'
                                : 'Use the format: resource.action (e.g., tickets.create)'}
                        </p>
                        {errors.name && <p className="text-sm text-red-500">{errors.name[0]}</p>}
                    </div>

                    {/* Display Name */}
                    <div className="space-y-2">
                        <Label htmlFor="display_name">Display Name *</Label>
                        <Input
                            id="display_name"
                            value={formData.display_name}
                            onChange={(e) => setFormData({ ...formData, display_name: e.target.value })}
                            placeholder="e.g., Create Tickets"
                            className={errors.display_name ? 'border-red-500' : ''}
                        />
                        <p className="text-muted-foreground text-xs">Human-readable name for the permission</p>
                        {errors.display_name && <p className="text-sm text-red-500">{errors.display_name[0]}</p>}
                    </div>

                    {/* Description */}
                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            value={formData.description}
                            onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                            placeholder="Brief description of what this permission allows..."
                            rows={3}
                            className={errors.description ? 'border-red-500' : ''}
                        />
                        <p className="text-muted-foreground text-xs">
                            Optional description to help administrators understand the permission's purpose
                        </p>
                        {errors.description && <p className="text-sm text-red-500">{errors.description[0]}</p>}
                    </div>

                    {/* Status */}
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="is_active"
                                checked={formData.is_active}
                                onCheckedChange={(checked) => setFormData({ ...formData, is_active: !!checked })}
                            />
                            <Label htmlFor="is_active" className="text-sm font-normal">
                                Active permission
                            </Label>
                        </div>
                        <p className="text-muted-foreground text-xs">Inactive permissions cannot be assigned to roles</p>
                    </div>

                    {/* Common Permission Examples */}
                    {!isEditing && (
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">Common Permission Examples</CardTitle>
                                <CardDescription>Click on any example to auto-fill the form</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-2">
                                    {[
                                        { resource: 'tickets', action: 'create', display: 'Create Tickets' },
                                        { resource: 'tickets', action: 'view', display: 'View Tickets' },
                                        { resource: 'users', action: 'manage', display: 'Manage Users' },
                                        { resource: 'roles', action: 'assign', display: 'Assign Roles' },
                                        { resource: 'reports', action: 'export', display: 'Export Reports' },
                                        { resource: 'settings', action: 'update', display: 'Update Settings' },
                                    ].map((example) => (
                                        <Button
                                            key={`${example.resource}.${example.action}`}
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-auto justify-start p-2 text-left"
                                            onClick={() => {
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    resource: example.resource,
                                                    action: example.action,
                                                    display_name: example.display,
                                                }));
                                            }}
                                        >
                                            <div>
                                                <div className="text-xs font-medium">{example.display}</div>
                                                <div className="text-muted-foreground text-xs">
                                                    {example.resource}.{example.action}
                                                </div>
                                            </div>
                                        </Button>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Actions */}
                    <div className="flex justify-end gap-4 border-t pt-4">
                        <Button type="button" variant="outline" onClick={onClose} disabled={loading}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={loading} className="gap-2">
                            <Save className="h-4 w-4" />
                            {loading ? 'Saving...' : isEditing ? 'Update Permission' : 'Create Permission'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
