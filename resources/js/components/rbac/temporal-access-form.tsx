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
import { AlertTriangle, Clock, Search, Shield } from 'lucide-react';
import React, { useMemo, useState } from 'react';

interface TemporalAccessFormProps {
    user: User;
    availableRoles: Role[];
    open: boolean;
    onClose: () => void;
}

export function TemporalAccessForm({ user, availableRoles, open, onClose }: TemporalAccessFormProps) {
    const [selectedRoles, setSelectedRoles] = useState<number[]>([]);
    const [duration, setDuration] = useState<string>('60');
    const [durationUnit, setDurationUnit] = useState<string>('minutes');
    const [reason, setReason] = useState('');
    const [emergency, setEmergency] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    // Filter roles based on search
    const filteredRoles = useMemo(() => {
        if (!searchTerm) return availableRoles.filter((role) => role.is_active);

        return availableRoles.filter(
            (role) =>
                role.is_active &&
                (role.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    role.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    role.description?.toLowerCase().includes(searchTerm.toLowerCase())),
        );
    }, [availableRoles, searchTerm]);

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

    // Quick access presets
    const quickPresets = [
        {
            name: 'Emergency System Access',
            duration: '30',
            unit: 'minutes',
            roles: availableRoles
                .filter((r) => r.name.includes('admin') || r.name.includes('system'))
                .slice(0, 1)
                .map((r) => r.id),
            reason: 'Emergency system maintenance or critical issue resolution',
            emergency: true,
        },
        {
            name: 'Temporary Elevation',
            duration: '2',
            unit: 'hours',
            roles: availableRoles
                .filter((r) => r.hierarchy_level >= 2)
                .slice(0, 1)
                .map((r) => r.id),
            reason: 'Temporary elevation for specific task completion',
            emergency: false,
        },
        {
            name: 'Department Override',
            duration: '4',
            unit: 'hours',
            roles: availableRoles
                .filter((r) => r.name.includes('manager'))
                .slice(0, 1)
                .map((r) => r.id),
            reason: 'Department-level override for operational needs',
            emergency: false,
        },
    ];

    const handleRoleToggle = (roleId: number) => {
        setSelectedRoles((prev) => (prev.includes(roleId) ? prev.filter((id) => id !== roleId) : [...prev, roleId]));
    };

    const applyPreset = (preset: (typeof quickPresets)[0]) => {
        setSelectedRoles(preset.roles);
        setDuration(preset.duration);
        setDurationUnit(preset.unit);
        setReason(preset.reason);
        setEmergency(preset.emergency);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (selectedRoles.length === 0) {
            setErrors({ roles: ['Please select at least one role for temporal access'] });
            return;
        }

        if (!reason.trim()) {
            setErrors({ reason: ['Please provide a reason for temporal access'] });
            return;
        }

        setLoading(true);
        setErrors({});

        const formData = {
            role_ids: selectedRoles,
            duration: parseInt(duration),
            duration_unit: durationUnit,
            reason: reason.trim(),
            emergency,
        };

        router.post(`/admin/users/${user.id}/temporal-access`, formData, {
            onSuccess: () => {
                onClose();
                // Reset form
                setSelectedRoles([]);
                setDuration('60');
                setDurationUnit('minutes');
                setReason('');
                setEmergency(false);
                setSearchTerm('');
            },
            onError: (errors) => {
                setErrors(errors);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    const calculateExpiryDate = () => {
        const now = new Date();
        const durationValue = parseInt(duration);

        switch (durationUnit) {
            case 'minutes':
                return new Date(now.getTime() + durationValue * 60 * 1000);
            case 'hours':
                return new Date(now.getTime() + durationValue * 60 * 60 * 1000);
            case 'days':
                return new Date(now.getTime() + durationValue * 24 * 60 * 60 * 1000);
            default:
                return null;
        }
    };

    const expiryDate = calculateExpiryDate();

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

    const getTotalPermissions = () => {
        return selectedRoles.reduce((total, roleId) => {
            const role = availableRoles.find((r) => r.id === roleId);
            return total + (role?.permissions?.length || 0);
        }, 0);
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[90vh] max-w-4xl flex-col overflow-hidden">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        Grant Temporal Access to {user.name}
                    </DialogTitle>
                    <DialogDescription>
                        Grant temporary role access for emergency situations or specific tasks. This will be logged and monitored.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex h-full flex-col">
                    <div className="flex-1 space-y-6 overflow-y-auto">
                        {/* Quick Presets */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">Quick Presets</CardTitle>
                                <CardDescription>Pre-configured temporal access scenarios</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3 md:grid-cols-3">
                                    {quickPresets.map((preset, index) => (
                                        <Button
                                            key={index}
                                            type="button"
                                            variant="outline"
                                            className="h-auto justify-start p-3 text-left"
                                            onClick={() => applyPreset(preset)}
                                        >
                                            <div className="w-full">
                                                <div className="mb-1 flex items-center gap-2">
                                                    <span className="text-sm font-medium">{preset.name}</span>
                                                    {preset.emergency && (
                                                        <Badge variant="destructive" className="text-xs">
                                                            Emergency
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground text-xs">
                                                    {preset.duration} {preset.unit} • {preset.roles.length} role{preset.roles.length !== 1 ? 's' : ''}
                                                </p>
                                            </div>
                                        </Button>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Access Configuration */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">Access Configuration</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-3 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="duration">Duration *</Label>
                                        <Input
                                            id="duration"
                                            type="number"
                                            min="1"
                                            max={durationUnit === 'minutes' ? 480 : durationUnit === 'hours' ? 72 : 7}
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
                                                <SelectItem value="minutes">Minutes (max 480)</SelectItem>
                                                <SelectItem value="hours">Hours (max 72)</SelectItem>
                                                <SelectItem value="days">Days (max 7)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Emergency Access</Label>
                                        <div className="flex h-10 items-center space-x-2">
                                            <Checkbox id="emergency" checked={emergency} onCheckedChange={(checked) => setEmergency(!!checked)} />
                                            <Label htmlFor="emergency" className="text-sm">
                                                Emergency override
                                            </Label>
                                        </div>
                                    </div>
                                </div>

                                {expiryDate && (
                                    <div
                                        className={`rounded-lg border p-3 ${emergency ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'}`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <AlertTriangle className={`h-4 w-4 ${emergency ? 'text-red-600' : 'text-amber-600'}`} />
                                            <span className={`text-sm font-medium ${emergency ? 'text-red-800' : 'text-amber-800'}`}>
                                                {emergency ? 'Emergency Access' : 'Temporal Access'} expires on: {expiryDate.toLocaleString()}
                                            </span>
                                        </div>
                                        {emergency && (
                                            <p className="mt-1 text-xs text-red-700">
                                                Emergency access will notify security team and require immediate justification.
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="reason">Reason *</Label>
                                    <Textarea
                                        id="reason"
                                        placeholder="Please provide a detailed reason for this temporal access..."
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        rows={3}
                                        className={errors.reason ? 'border-red-500' : ''}
                                    />
                                    {errors.reason && <p className="text-sm text-red-500">{errors.reason[0]}</p>}
                                    <p className="text-muted-foreground text-xs">
                                        This will be logged and audited. Be specific about why temporal access is needed.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Role Selection */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <span>Select Roles ({selectedRoles.length} selected)</span>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">{getTotalPermissions()} permissions</Badge>
                                        <Badge variant="outline">{filteredRoles.length} available</Badge>
                                    </div>
                                </CardTitle>
                                <CardDescription>Choose roles for temporal access</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Search */}
                                <div className="relative">
                                    <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                    <Input
                                        placeholder="Search roles..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="pl-9"
                                    />
                                </div>

                                {errors.roles && (
                                    <div className="rounded-lg border border-red-200 bg-red-50 p-3">
                                        <p className="text-sm text-red-600">{errors.roles[0]}</p>
                                    </div>
                                )}

                                {filteredRoles.length === 0 ? (
                                    <div className="py-8 text-center">
                                        <Shield className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                        <h3 className="mb-2 text-lg font-semibold">No roles found</h3>
                                        <p className="text-muted-foreground">
                                            {searchTerm ? 'No roles match your search criteria.' : 'No active roles available.'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
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
                        <Button
                            type="submit"
                            disabled={loading || selectedRoles.length === 0 || !reason.trim()}
                            className={`gap-2 ${emergency ? 'bg-red-600 hover:bg-red-700' : ''}`}
                        >
                            <Clock className="h-4 w-4" />
                            {loading ? 'Granting...' : emergency ? 'Grant Emergency Access' : 'Grant Temporal Access'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
