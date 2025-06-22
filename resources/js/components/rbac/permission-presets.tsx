import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Permission } from '@/types/rbac';
import { BarChart3, BookOpen, FileText, Layers, Settings, Shield, Users, Zap } from 'lucide-react';
import React from 'react';

interface PermissionPreset {
    id: string;
    name: string;
    description: string;
    icon: React.ComponentType<{ className?: string }>;
    color: string;
    permissions: string[];
    category: string;
}

interface PermissionPresetsProps {
    permissions: Permission[];
    selectedPermissions: number[];
    onApplyPreset: (permissionIds: number[]) => void;
    className?: string;
}

// Define permission presets based on common role patterns
const PERMISSION_PRESETS: PermissionPreset[] = [
    {
        id: 'basic_support',
        name: 'Basic Support Agent',
        description: 'Essential permissions for support staff',
        icon: Users,
        color: 'bg-blue-100 text-blue-800 border-blue-200',
        category: 'Support',
        permissions: [
            'tickets.create',
            'tickets.view_own',
            'tickets.edit_own',
            'tickets.assign',
            'tickets.close',
            'users.view_own',
            'users.edit_own',
            'knowledge.create_articles',
        ],
    },
    {
        id: 'department_lead',
        name: 'Department Manager',
        description: 'Team leadership and departmental oversight',
        icon: Shield,
        color: 'bg-green-100 text-green-800 border-green-200',
        category: 'Management',
        permissions: [
            'tickets.view_department',
            'tickets.edit_department',
            'tickets.delete_department',
            'tickets.assign',
            'tickets.transfer',
            'users.view_department',
            'users.edit_department',
            'users.create',
            'sla.view',
            'sla.enforce',
            'reports.view_department',
            'reports.create_custom',
        ],
    },
    {
        id: 'knowledge_manager',
        name: 'Knowledge Curator',
        description: 'Content and documentation management',
        icon: BookOpen,
        color: 'bg-purple-100 text-purple-800 border-purple-200',
        category: 'Content',
        permissions: [
            'knowledge.create_articles',
            'knowledge.edit_articles',
            'knowledge.approve_articles',
            'knowledge.manage_categories',
            'knowledge.version_control',
            'tickets.view_department',
            'users.view_department',
        ],
    },
    {
        id: 'reports_analyst',
        name: 'Reports & Analytics',
        description: 'Business intelligence and reporting',
        icon: BarChart3,
        color: 'bg-orange-100 text-orange-800 border-orange-200',
        category: 'Analytics',
        permissions: [
            'reports.view_all',
            'reports.create_custom',
            'reports.export',
            'reports.schedule',
            'audit.view_logs',
            'audit.export_data',
            'tickets.view_all',
            'users.view_all',
        ],
    },
    {
        id: 'compliance_auditor',
        name: 'Compliance Officer',
        description: 'Audit and compliance oversight',
        icon: FileText,
        color: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        category: 'Compliance',
        permissions: [
            'audit.view_logs',
            'audit.export_data',
            'audit.compliance_reports',
            'tickets.view_all',
            'users.view_all',
            'roles.view',
            'reports.view_all',
            'reports.export',
        ],
    },
    {
        id: 'system_admin',
        name: 'System Administrator',
        description: 'Full system access and configuration',
        icon: Settings,
        color: 'bg-red-100 text-red-800 border-red-200',
        category: 'Administration',
        permissions: [
            'system.configuration',
            'system.maintenance',
            'system.backup',
            'users.manage_all',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'departments.create',
            'departments.edit',
            'departments.delete',
        ],
    },
];

export function PermissionPresets({ permissions, selectedPermissions, onApplyPreset, className }: PermissionPresetsProps) {
    const getPresetPermissionIds = (presetPermissions: string[]): number[] => {
        return permissions.filter((permission) => presetPermissions.includes(permission.name)).map((permission) => permission.id);
    };

    const getPresetStatus = (presetPermissions: string[]): 'none' | 'partial' | 'all' => {
        const presetIds = getPresetPermissionIds(presetPermissions);
        const selectedPresetIds = presetIds.filter((id) => selectedPermissions.includes(id));

        if (selectedPresetIds.length === 0) return 'none';
        if (selectedPresetIds.length === presetIds.length) return 'all';
        return 'partial';
    };

    const handleApplyPreset = (preset: PermissionPreset) => {
        const presetIds = getPresetPermissionIds(preset.permissions);
        const status = getPresetStatus(preset.permissions);

        if (status === 'all') {
            // If all permissions are selected, deselect them
            const newSelection = selectedPermissions.filter((id) => !presetIds.includes(id));
            onApplyPreset(newSelection);
        } else {
            // Otherwise, add the preset permissions
            const newSelection = [...new Set([...selectedPermissions, ...presetIds])];
            onApplyPreset(newSelection);
        }
    };

    const groupedPresets = PERMISSION_PRESETS.reduce(
        (acc, preset) => {
            if (!acc[preset.category]) {
                acc[preset.category] = [];
            }
            acc[preset.category].push(preset);
            return acc;
        },
        {} as Record<string, PermissionPreset[]>,
    );

    return (
        <div className={`flex h-full flex-col ${className}`}>
            <div className="mb-4 flex-shrink-0 border-b pb-4">
                <div className="mb-2 flex items-center gap-2">
                    <Layers className="h-5 w-5" />
                    <h3 className="text-lg font-semibold">Permission Presets</h3>
                </div>
                <p className="text-muted-foreground text-sm">Quick apply common permission sets for different roles</p>
            </div>
            <div className="flex-1 space-y-6 overflow-y-auto pr-2">
                {Object.entries(groupedPresets).map(([category, presets]) => (
                    <div key={category} className="space-y-3">
                        <h4 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">{category}</h4>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            {presets.map((preset) => {
                                const status = getPresetStatus(preset.permissions);
                                const availableCount = getPresetPermissionIds(preset.permissions).length;
                                const Icon = preset.icon;

                                return (
                                    <Button
                                        key={preset.id}
                                        variant={status === 'all' ? 'default' : 'outline'}
                                        className={`h-auto justify-start p-3 ${status === 'partial' ? 'border-primary' : ''}`}
                                        onClick={() => handleApplyPreset(preset)}
                                        type="button"
                                    >
                                        <div className="flex w-full items-start gap-3 text-left">
                                            <Icon className="mt-0.5 h-5 w-5 flex-shrink-0" />
                                            <div className="min-w-0 flex-1">
                                                <div className="mb-1 flex items-center gap-2">
                                                    <span className="truncate text-sm font-medium">{preset.name}</span>
                                                    <Badge variant="secondary" className="px-1.5 py-0.5 text-xs">
                                                        {availableCount}
                                                    </Badge>
                                                    {status === 'partial' && (
                                                        <Badge variant="outline" className="px-1.5 py-0.5 text-xs">
                                                            Partial
                                                        </Badge>
                                                    )}
                                                    {status === 'all' && (
                                                        <Badge variant="default" className="px-1.5 py-0.5 text-xs">
                                                            Applied
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground line-clamp-2 text-xs">{preset.description}</p>
                                            </div>
                                        </div>
                                    </Button>
                                );
                            })}
                        </div>
                    </div>
                ))}

                <div className="border-t pt-3">
                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                        <Zap className="h-3 w-3" />
                        <span>Click a preset to apply/remove its permissions</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default PermissionPresets;
