import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Role } from '@/types/rbac';
import { Book, Crown, Eye, Settings, Shield, Users } from 'lucide-react';
import { useMemo } from 'react';

interface RoleHierarchyViewProps {
    roles: Role[];
    open: boolean;
    onClose: () => void;
}

export function RoleHierarchyView({ roles, open, onClose }: RoleHierarchyViewProps) {
    // Group roles by hierarchy level
    const rolesByLevel = useMemo(() => {
        const grouped = roles.reduce(
            (acc, role) => {
                if (!acc[role.hierarchy_level]) {
                    acc[role.hierarchy_level] = [];
                }
                acc[role.hierarchy_level].push(role);
                return acc;
            },
            {} as Record<number, Role[]>,
        );

        // Sort each level by name
        Object.keys(grouped).forEach((level) => {
            grouped[parseInt(level)] = grouped[parseInt(level)].sort((a, b) => a.display_name.localeCompare(b.display_name));
        });

        return grouped;
    }, [roles]);

    const hierarchyLevels = useMemo(() => {
        return Object.keys(rolesByLevel)
            .map(Number)
            .sort((a, b) => b - a); // Sort from highest to lowest level
    }, [rolesByLevel]);

    const getHierarchyIcon = (level: number) => {
        switch (level) {
            case 4:
                return Crown;
            case 3:
                return Settings;
            case 2:
                return Shield;
            case 1:
                return Users;
            default:
                return Eye;
        }
    };

    const getHierarchyColor = (level: number) => {
        const colors = {
            4: 'bg-red-50 border-red-200 text-red-800',
            3: 'bg-orange-50 border-orange-200 text-orange-800',
            2: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            1: 'bg-blue-50 border-blue-200 text-blue-800',
            0: 'bg-green-50 border-green-200 text-green-800',
        };
        return colors[level as keyof typeof colors] || 'bg-gray-50 border-gray-200 text-gray-800';
    };

    const getHierarchyDescription = (level: number) => {
        const descriptions = {
            4: 'Highest level - System-wide authority',
            3: 'High level - Multi-department oversight',
            2: 'Mid level - Department management',
            1: 'Basic level - Team supervision',
            0: 'Entry level - Individual tasks',
        };
        return descriptions[level as keyof typeof descriptions] || 'Custom hierarchy level';
    };

    const getTotalPermissions = (rolesAtLevel: Role[]) => {
        return rolesAtLevel.reduce((total, role) => total + (role.permissions?.length || 0), 0);
    };

    const getTotalUsers = (rolesAtLevel: Role[]) => {
        return rolesAtLevel.reduce((total, role) => total + (role.users_count || 0), 0);
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[90vh] max-w-6xl flex-col overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Role Hierarchy</DialogTitle>
                    <DialogDescription>Visual representation of the role hierarchy structure and relationships</DialogDescription>
                </DialogHeader>

                <div className="flex h-full flex-col space-y-6">
                    {/* Overview Stats */}
                    <div className="grid grid-cols-3 gap-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">Total Levels</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{hierarchyLevels.length}</div>
                                <p className="text-muted-foreground text-xs">hierarchy levels</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">Total Roles</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{roles.length}</div>
                                <p className="text-muted-foreground text-xs">{roles.filter((r) => r.is_active).length} active</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">Hierarchy Range</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {Math.min(...hierarchyLevels)} - {Math.max(...hierarchyLevels)}
                                </div>
                                <p className="text-muted-foreground text-xs">level range</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Hierarchy Tree */}
                    <Card className="flex-1 overflow-hidden">
                        <CardHeader>
                            <CardTitle>Hierarchy Structure</CardTitle>
                            <CardDescription>Roles organized by hierarchy level, from highest authority to lowest</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="h-full overflow-y-auto p-6">
                                <div className="space-y-8">
                                    {hierarchyLevels.map((level, levelIndex) => {
                                        const rolesAtLevel = rolesByLevel[level];
                                        const HierarchyIcon = getHierarchyIcon(level);
                                        const hierarchyColor = getHierarchyColor(level);

                                        return (
                                            <div key={level} className="relative">
                                                {/* Connection Line to Previous Level */}
                                                {levelIndex > 0 && <div className="bg-border absolute -top-4 left-1/2 h-4 w-px"></div>}

                                                {/* Level Header */}
                                                <div className="mb-6 flex items-center justify-center">
                                                    <div className={`flex items-center gap-3 rounded-lg border-2 px-4 py-2 ${hierarchyColor}`}>
                                                        <HierarchyIcon className="h-5 w-5" />
                                                        <div className="text-center">
                                                            <div className="font-semibold">Level {level}</div>
                                                            <div className="text-xs opacity-80">{getHierarchyDescription(level)}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Level Stats */}
                                                <div className="mb-4 flex justify-center">
                                                    <div className="text-muted-foreground flex items-center gap-6 text-sm">
                                                        <div className="flex items-center gap-1">
                                                            <Shield className="h-4 w-4" />
                                                            {rolesAtLevel.length} roles
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <Users className="h-4 w-4" />
                                                            {getTotalUsers(rolesAtLevel)} users
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <Book className="h-4 w-4" />
                                                            {getTotalPermissions(rolesAtLevel)} permissions
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Roles at this Level */}
                                                <div className="relative">
                                                    {/* Horizontal Connection Line */}
                                                    {rolesAtLevel.length > 1 && (
                                                        <div className="bg-border absolute top-8 left-1/2 h-px w-full max-w-4xl -translate-x-1/2 transform"></div>
                                                    )}

                                                    <div className="grid grid-cols-1 justify-items-center gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                                        {rolesAtLevel.map((role) => (
                                                            <div key={role.id} className="relative">
                                                                {/* Vertical Connection to Horizontal Line */}
                                                                {rolesAtLevel.length > 1 && (
                                                                    <div className="bg-border absolute -top-2 left-1/2 h-2 w-px -translate-x-1/2 transform"></div>
                                                                )}

                                                                <Card className={`w-56 ${!role.is_active ? 'opacity-60' : ''}`}>
                                                                    <CardHeader className="pb-3">
                                                                        <div className="flex items-center justify-between">
                                                                            <CardTitle className="truncate text-base">{role.display_name}</CardTitle>
                                                                            {!role.is_active && (
                                                                                <Badge variant="secondary" className="text-xs">
                                                                                    Inactive
                                                                                </Badge>
                                                                            )}
                                                                        </div>
                                                                        <CardDescription className="line-clamp-2 text-xs">
                                                                            {role.description || 'No description available'}
                                                                        </CardDescription>
                                                                    </CardHeader>

                                                                    <CardContent>
                                                                        <div className="space-y-2">
                                                                            <div className="flex justify-between text-xs">
                                                                                <span className="text-muted-foreground">Users:</span>
                                                                                <span className="font-medium">{role.users_count || 0}</span>
                                                                            </div>
                                                                            <div className="flex justify-between text-xs">
                                                                                <span className="text-muted-foreground">Permissions:</span>
                                                                                <span className="font-medium">{role.permissions?.length || 0}</span>
                                                                            </div>
                                                                            <div className="pt-2">
                                                                                <Badge variant="outline" className="text-xs">
                                                                                    {role.name}
                                                                                </Badge>
                                                                            </div>
                                                                        </div>
                                                                    </CardContent>
                                                                </Card>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>

                                                {/* Connection Line to Next Level */}
                                                {levelIndex < hierarchyLevels.length - 1 && (
                                                    <div className="mt-8 flex justify-center">
                                                        <div className="bg-border h-4 w-px"></div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>

                                {roles.length === 0 && (
                                    <div className="flex flex-col items-center justify-center py-12">
                                        <Shield className="text-muted-foreground mb-4 h-12 w-12" />
                                        <h3 className="mb-2 text-lg font-semibold">No roles defined</h3>
                                        <p className="text-muted-foreground text-center">
                                            There are no roles in the system to display in the hierarchy.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Legend */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Hierarchy Legend</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-5 gap-4">
                                {[4, 3, 2, 1, 0].map((level) => {
                                    const HierarchyIcon = getHierarchyIcon(level);
                                    const hierarchyColor = getHierarchyColor(level);

                                    return (
                                        <div key={level} className="flex items-center gap-2">
                                            <div className={`rounded border p-2 ${hierarchyColor}`}>
                                                <HierarchyIcon className="h-4 w-4" />
                                            </div>
                                            <div>
                                                <div className="text-sm font-medium">Level {level}</div>
                                                <div className="text-muted-foreground text-xs">{getHierarchyDescription(level).split(' - ')[1]}</div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-4 border-t pt-4">
                        <Button variant="outline" onClick={onClose}>
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
