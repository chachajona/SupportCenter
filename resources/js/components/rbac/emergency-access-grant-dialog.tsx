import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { User as UserType } from '@/types/rbac';
import { router } from '@inertiajs/react';
import { AlertTriangle, Clock, Shield, User, X } from 'lucide-react';
import React, { useState } from 'react';

interface AvailablePermission {
    name: string;
    display_name?: string;
    description?: string;
}

interface EmergencyAccessGrantDialogProps {
    users: UserType[];
    availablePermissions: AvailablePermission[];
    open: boolean;
    onClose: () => void;
}

const getRiskLevel = (permissionName: string): 'critical' | 'high' | 'medium' | 'low' => {
    if (/delete|database|system\.maintenance|settings\./i.test(permissionName)) return 'critical';
    if (/impersonate|export|backup/i.test(permissionName)) return 'high';
    if (/logs|view_all/i.test(permissionName)) return 'medium';
    return 'low';
};

const durationPresets = [
    { label: '15 minutes', value: 15 },
    { label: '30 minutes', value: 30 },
    { label: '1 hour', value: 60 },
    { label: '2 hours', value: 120 },
    { label: '4 hours', value: 240 },
    { label: 'Custom', value: 0 },
];

export function EmergencyAccessGrantDialog({ users, availablePermissions, open, onClose }: EmergencyAccessGrantDialogProps) {
    const [selectedUser, setSelectedUser] = useState<string>('');
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);
    const [duration, setDuration] = useState<number>(30);
    const [customDuration, setCustomDuration] = useState<string>('');
    const [reason, setReason] = useState<string>('');
    const [confirmationChecked, setConfirmationChecked] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const validateForm = () => {
        const newErrors: Record<string, string> = {};

        if (!selectedUser) {
            newErrors.user = 'Please select a user';
        }

        if (selectedPermissions.length === 0) {
            newErrors.permissions = 'Please select at least one permission';
        }

        if (!reason.trim()) {
            newErrors.reason = 'Please provide a detailed reason for emergency access';
        } else if (reason.trim().length < 20) {
            newErrors.reason = 'Reason must be at least 20 characters long';
        }

        if (duration === 0 && (!customDuration || parseInt(customDuration) <= 0)) {
            newErrors.duration = 'Please specify a valid duration';
        }

        if (!confirmationChecked) {
            newErrors.confirmation = 'Please confirm you understand the security implications';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);

        try {
            const finalDuration = duration === 0 ? parseInt(customDuration) : duration;

            router.post(
                '/admin/emergency',
                {
                    user_id: selectedUser,
                    permissions: selectedPermissions,
                    duration: finalDuration,
                    reason: reason.trim(),
                },
                {
                    onSuccess: () => {
                        resetForm();
                        onClose();
                    },
                    onError: (errors) => {
                        setErrors(errors);
                    },
                    onFinish: () => {
                        setIsSubmitting(false);
                    },
                },
            );
        } catch (error) {
            console.error(error);
            setIsSubmitting(false);
        }
    };

    const resetForm = () => {
        setSelectedUser('');
        setSelectedPermissions([]);
        setDuration(30);
        setCustomDuration('');
        setReason('');
        setConfirmationChecked(false);
        setErrors({});
    };

    const handlePermissionToggle = (permission: string) => {
        setSelectedPermissions((prev) => (prev.includes(permission) ? prev.filter((p) => p !== permission) : [...prev, permission]));
    };

    const getRiskColor = (risk: string) => {
        switch (risk) {
            case 'critical':
                return 'bg-red-100 text-red-800 border-red-200';
            case 'high':
                return 'bg-orange-100 text-orange-800 border-orange-200';
            case 'medium':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const selectedUser_ = users.find((u) => u.id.toString() === selectedUser);
    const hasHighRiskPermissions = selectedPermissions.some((p) => {
        const level = getRiskLevel(p);
        return level === 'critical' || level === 'high';
    });

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-amber-500" />
                        Grant Emergency Access
                    </DialogTitle>
                    <DialogDescription>
                        Grant temporary elevated permissions for emergency situations. This action will be logged and monitored.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* User Selection */}
                    <div className="space-y-2">
                        <Label htmlFor="user-select">User</Label>
                        <Select value={selectedUser} onValueChange={setSelectedUser}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select a user" />
                            </SelectTrigger>
                            <SelectContent>
                                {users.map((user) => (
                                    <SelectItem key={user.id} value={user.id.toString()}>
                                        <div className="flex items-center gap-2">
                                            <User className="h-4 w-4" />
                                            <span>{user.name}</span>
                                            <span className="text-muted-foreground text-sm">({user.email})</span>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.user && <p className="text-sm text-red-600">{errors.user}</p>}
                    </div>

                    {/* Permission Selection */}
                    <div className="space-y-2">
                        <Label>Emergency Permissions</Label>
                        <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3">
                            {availablePermissions.map((permission) => {
                                const risk = getRiskLevel(permission.name);
                                const label = permission.display_name || permission.name;
                                return (
                                    <div key={permission.name} className="flex items-center space-x-3">
                                        <Checkbox
                                            id={permission.name}
                                            checked={selectedPermissions.includes(permission.name)}
                                            onCheckedChange={() => handlePermissionToggle(permission.name)}
                                        />
                                        <div className="flex-1">
                                            <label
                                                htmlFor={permission.name}
                                                className="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                            >
                                                {label}
                                            </label>
                                        </div>
                                        <Badge className={`${getRiskColor(risk)} text-xs`}>{risk.toUpperCase()}</Badge>
                                    </div>
                                );
                            })}
                        </div>
                        {errors.permissions && <p className="text-sm text-red-600">{errors.permissions}</p>}

                        {selectedPermissions.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-sm font-medium">Selected Permissions:</p>
                                <div className="flex flex-wrap gap-2">
                                    {selectedPermissions.map((permission) => {
                                        return (
                                            <Badge key={permission} variant="secondary" className="gap-1">
                                                {availablePermissions.find((p) => p.name === permission)?.display_name || permission}
                                                <button
                                                    type="button"
                                                    onClick={() => handlePermissionToggle(permission)}
                                                    className="ml-1 rounded-full p-0.5 hover:bg-gray-300"
                                                >
                                                    <X className="h-3 w-3" />
                                                </button>
                                            </Badge>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Duration Selection */}
                    <div className="space-y-2">
                        <Label>Access Duration</Label>
                        <div className="grid grid-cols-3 gap-2">
                            {durationPresets.map((preset) => (
                                <Button
                                    key={preset.value}
                                    type="button"
                                    variant={duration === preset.value ? 'default' : 'outline'}
                                    onClick={() => setDuration(preset.value)}
                                    className="text-sm"
                                >
                                    {preset.label}
                                </Button>
                            ))}
                        </div>

                        {duration === 0 && (
                            <div className="flex items-center gap-2">
                                <Input
                                    type="number"
                                    placeholder="Minutes"
                                    value={customDuration}
                                    onChange={(e) => setCustomDuration(e.target.value)}
                                    min="1"
                                    max="1440"
                                    className="w-32"
                                />
                                <span className="text-muted-foreground text-sm">minutes</span>
                            </div>
                        )}
                        {errors.duration && <p className="text-sm text-red-600">{errors.duration}</p>}
                    </div>

                    {/* Reason */}
                    <div className="space-y-2">
                        <Label htmlFor="reason">Reason for Emergency Access</Label>
                        <Textarea
                            id="reason"
                            placeholder="Provide a detailed explanation for why emergency access is needed..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={4}
                            className="resize-none"
                        />
                        <p className="text-muted-foreground text-xs">Minimum 20 characters. This will be logged and reviewed.</p>
                        {errors.reason && <p className="text-sm text-red-600">{errors.reason}</p>}
                    </div>

                    {/* Security Warnings */}
                    {hasHighRiskPermissions && (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                <strong>High Risk Permissions Selected:</strong> The selected permissions include critical system access. This grant
                                will trigger immediate security team notifications.
                            </AlertDescription>
                        </Alert>
                    )}

                    {selectedUser_ && (
                        <Alert>
                            <Shield className="h-4 w-4" />
                            <AlertDescription>
                                Emergency access will be granted to <strong>{selectedUser_.name}</strong> for{' '}
                                <strong>{duration === 0 ? customDuration : duration} minutes</strong>. All actions will be monitored and logged.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Confirmation */}
                    <div className="flex items-center space-x-2">
                        <Checkbox id="confirmation" checked={confirmationChecked} onCheckedChange={(checked) => setConfirmationChecked(!!checked)} />
                        <label
                            htmlFor="confirmation"
                            className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                        >
                            I understand the security implications and confirm this emergency access is necessary
                        </label>
                    </div>
                    {errors.confirmation && <p className="text-sm text-red-600">{errors.confirmation}</p>}
                </form>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" onClick={handleSubmit} disabled={isSubmitting} className="gap-2">
                        {isSubmitting ? (
                            <>
                                <Clock className="h-4 w-4 animate-spin" />
                                Granting Access...
                            </>
                        ) : (
                            <>
                                <Shield className="h-4 w-4" />
                                Grant Emergency Access
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
