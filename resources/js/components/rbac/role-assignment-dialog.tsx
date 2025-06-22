import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Role, User } from '@/types/rbac';
import { router } from '@inertiajs/react';
import { AlertTriangle, Clock, Save, Search, Shield } from 'lucide-react';
import React, { useMemo, useState } from 'react';

interface RoleAssignmentDialogProps {
    user: User;
    availableRoles: Role[];
    assignedRoles: Role[];
    open: boolean;
    onClose: () => void;
}

export function RoleAssignmentDialog({ user, availableRoles, assignedRoles, open, onClose }: RoleAssignmentDialogProps) {
    const [selectedRoles, setSelectedRoles] = useState<number[]>([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [isTemporary, setIsTemporary] = useState(false);
    const [duration, setDuration] = useState<string>('24');
    const [durationUnit, setDurationUnit] = useState<string>('hours');
    const [reason, setReason] = useState('');
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    // Filter out already assigned roles
    const unassignedRoles = useMemo(() => {
        const assignedRoleIds = assignedRoles.map((role) => role.id);
        return availableRoles.filter((role) => !assignedRoleIds.includes(role.id) && role.is_active);
    }, [availableRoles, assignedRoles]);

    // Filter roles based on search
    const filteredRoles = useMemo(() => {
        if (!searchTerm) return unassignedRoles;

        return unassignedRoles.filter(
            (role) =>
                role.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                role.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                role.description?.toLowerCase().includes(searchTerm.toLowerCase()),
        );
    }, [unassignedRoles, searchTerm]);

    // Group roles by hierarchy level
    const rolesByLevel = useMemo(() => {
        return filteredRoles.reduce(
            (acc, role) => {
                if (!acc[role.hierarchy_level]) {
                    acc[role.hierarchy_level] = [];
                }
                acc[role.hierarchy_level].push(role);
                return acc;
            },
            {} as Record<number, Role[]>,
        );
    }, [filteredRoles]);

    const handleRoleToggle = (roleId: number) => {
        setSelectedRoles((prev) => (prev.includes(roleId) ? prev.filter((id) => id !== roleId) : [...prev, roleId]));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (selectedRoles.length === 0) {
            setErrors({ roles: ['Please select at least one role to assign'] });
            return;
        }

        setLoading(true);
        setErrors({});

        const formData = {
            role_ids: selectedRoles,
            is_temporary: isTemporary,
            ...(isTemporary && {
                duration: parseInt(duration),
                duration_unit: durationUnit,
                reason: reason.trim() || undefined,
            }),
        };

        router.post(`/admin/users/${user.id}/roles/assign`, formData, {
            onSuccess: () => {
                onClose();
                // Reset form
                setSelectedRoles([]);
                setIsTemporary(false);
                setDuration('24');
                setDurationUnit('hours');
                setReason('');
            },
            onError: (errors) => {
                setErrors(errors as unknown as Record<string, string[]>);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    const getHierarchyColor = (level: number) => {
        const colors = [
            'bg-green-100 text-green-800',
            'bg-blue-100 text-blue-800',
            'bg-yellow-100 text-yellow-800',
            'bg-orange-100 text-orange-800',
            'bg-red-100 text-red-800',
        ];
        return colors[level] || 'bg-gray-100 text-gray-800';
    };

    const calculateExpiryDate = () => {
        if (!isTemporary) return null;

        const now = new Date();
        const durationValue = parseInt(duration);

        switch (durationUnit) {
            case 'minutes':
                return new Date(now.getTime() + durationValue * 60 * 1000);
            case 'hours':
                return new Date(now.getTime() + durationValue * 60 * 60 * 1000);
            case 'days':
                return new Date(now.getTime() + durationValue * 24 * 60 * 60 * 1000);
            case 'weeks':
                return new Date(now.getTime() + durationValue * 7 * 24 * 60 * 60 * 1000);
            default:
                return null;
        }
    };

    const expiryDate = calculateExpiryDate();

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[90vh] max-w-4xl flex-col overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Assign Roles to {user.name}</DialogTitle>
                    <DialogDescription>
                        Select one or more roles to assign to this user. You can assign roles permanently or temporarily.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex h-full flex-col">
                    <div className="flex-1 space-y-6 overflow-y-auto">
                        {/* Search */}
                        <div className="space-y-2">
                            <Label htmlFor="search">Search Roles</Label>
                            <div className="relative">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    id="search"
                                    placeholder="Search by name or description..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                        </div>

                        {/* Assignment Type */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">Assignment Type</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center space-x-2">
                                    <Checkbox id="is_temporary" checked={isTemporary} onCheckedChange={(checked) => setIsTemporary(!!checked)} />
                                    <Label htmlFor="is_temporary" className="flex items-center gap-2">
                                        <Clock className="h-4 w-4" />
                                        Temporary Assignment
                                    </Label>
                                </div>

                                {isTemporary && (
                                    <div className="bg-muted space-y-4 rounded-lg p-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="duration">Duration</Label>
                                                <Input
                                                    id="duration"
                                                    type="number"
                                                    min="1"
                                                    value={duration}
                                                    onChange={(e) => setDuration(e.target.value)}
                                                    className={errors.duration ? 'border-red-500' : ''}
                                                />
                                                {errors.duration && <p className="text-sm text-red-500">{errors.duration[0]}</p>}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="duration_unit">Unit</Label>
                                                <Select value={durationUnit} onValueChange={setDurationUnit}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="minutes">Minutes</SelectItem>
                                                        <SelectItem value="hours">Hours</SelectItem>
                                                        <SelectItem value="days">Days</SelectItem>
                                                        <SelectItem value="weeks">Weeks</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        {expiryDate && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <AlertTriangle className="h-4 w-4 text-amber-500" />
                                                <span>Will expire on: {expiryDate.toLocaleString()}</span>
                                            </div>
                                        )}

                                        <div className="space-y-2">
                                            <Label htmlFor="reason">Reason (Optional)</Label>
                                            <Textarea
                                                id="reason"
                                                placeholder="Reason for temporary assignment..."
                                                value={reason}
                                                onChange={(e) => setReason(e.target.value)}
                                                rows={2}
                                            />
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Role Selection */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <span>Available Roles ({selectedRoles.length} selected)</span>
                                    <Badge variant="outline">{filteredRoles.length} available</Badge>
                                </CardTitle>
                                <CardDescription>Select roles to assign to this user</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {errors.roles && (
                                    <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3">
                                        <p className="text-sm text-red-600">{errors.roles[0]}</p>
                                    </div>
                                )}

                                {filteredRoles.length === 0 ? (
                                    <div className="py-8 text-center">
                                        <Shield className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                        <h3 className="mb-2 text-lg font-semibold">No roles available</h3>
                                        <p className="text-muted-foreground">
                                            {searchTerm
                                                ? 'No roles match your search criteria.'
                                                : 'All available roles have already been assigned to this user.'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-6">
                                        {Object.entries(rolesByLevel)
                                            .sort(([a], [b]) => parseInt(b) - parseInt(a))
                                            .map(([level, roles]) => (
                                                <div key={level}>
                                                    <div className="mb-3 flex items-center gap-2">
                                                        <Badge className={getHierarchyColor(parseInt(level))}>Level {level}</Badge>
                                                        <span className="text-muted-foreground text-sm">
                                                            {roles.length} role{roles.length !== 1 ? 's' : ''}
                                                        </span>
                                                    </div>

                                                    <div className="grid gap-3 md:grid-cols-2">
                                                        {roles.map((role) => (
                                                            <div
                                                                key={role.id}
                                                                className={`cursor-pointer rounded-lg border p-3 transition-colors ${
                                                                    selectedRoles.includes(role.id)
                                                                        ? 'bg-primary/10 border-primary'
                                                                        : 'hover:bg-muted/50'
                                                                }`}
                                                                onClick={() => handleRoleToggle(role.id)}
                                                            >
                                                                <div className="flex items-start space-x-3">
                                                                    <Checkbox
                                                                        checked={selectedRoles.includes(role.id)}
                                                                        onChange={() => handleRoleToggle(role.id)}
                                                                        className="mt-1"
                                                                    />
                                                                    <div className="flex-1">
                                                                        <div className="mb-1 flex items-center justify-between">
                                                                            <h4 className="text-sm font-medium">{role.display_name}</h4>
                                                                            <Badge variant="outline" className="text-xs">
                                                                                {role.permissions?.length || 0} perms
                                                                            </Badge>
                                                                        </div>
                                                                        <p className="text-muted-foreground line-clamp-2 text-xs">
                                                                            {role.description || 'No description available'}
                                                                        </p>
                                                                        <div className="mt-2">
                                                                            <Badge variant="outline" className="text-xs">
                                                                                {role.name}
                                                                            </Badge>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-4 border-t pt-4">
                        <Button type="button" variant="outline" onClick={onClose} disabled={loading}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={loading || selectedRoles.length === 0} className="gap-2">
                            <Save className="h-4 w-4" />
                            {loading ? 'Assigning...' : `Assign Role${selectedRoles.length !== 1 ? 's' : ''}`}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
