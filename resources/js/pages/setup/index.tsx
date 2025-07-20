import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Stepper, type StepperStep } from '@/components/ui/stepper';
import { Head, router } from '@inertiajs/react';
import { ArrowRight, CheckCircle, Database, Info, Loader2, Lock, Settings, Shield, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface SetupProps {
    steps: {
        prerequisites_checked: boolean;
        database_configured: boolean;
        database_migrated: boolean;
        roles_seeded: boolean;
        admin_created: boolean;
    };
    currentStep: string;
    data?: {
        [key: string]: unknown;
    };
}

const createSetupSteps = (steps: SetupProps['steps']): StepperStep[] => [
    {
        key: 'prerequisites',
        title: 'Prerequisites',
        description: 'Check system requirements',
        icon: Settings,
        completed: steps.prerequisites_checked,
    },
    {
        key: 'database',
        title: 'Database Setup',
        description: 'Configure and initialize database',
        icon: Database,
        completed: steps.database_configured && steps.database_migrated,
    },
    {
        key: 'roles_seeded',
        title: 'System Configuration',
        description: 'Setup roles and permissions',
        icon: Shield,
        completed: steps.roles_seeded,
    },
    {
        key: 'app_settings',
        title: 'Application Setup',
        description: 'Configure admin account and settings',
        icon: UserPlus,
        completed: steps.admin_created,
    },
];

export default function SetupIndex({ steps, currentStep, data = {} }: SetupProps) {
    const [loading, setLoading] = useState<string | null>(null);
    const [finishing, setFinishing] = useState(false);

    // Reset handler
    const handleReset = () => {
        if (!confirm('Are you sure you want to restart the setup process? All progress will be lost.')) {
            return;
        }

        router.post(
            '/setup/reset',
            { confirm: 'yes' },
            {
                onSuccess: () => router.visit('/setup/prerequisites'),
            },
        );
    };

    // Database configuration state
    const [dbForm, setDbForm] = useState({
        host: (data.db_host as string) || 'localhost',
        port: (data.db_port as string) || '3306',
        database: (data.db_database as string) || '',
        username: (data.db_username as string) || '',
        password: '',
    });

    // Admin form state
    const [adminForm, setAdminForm] = useState({
        helpdesk_name: (data.app_name as string) || 'osTicket Support Center',
        helpdesk_url: (data.app_url as string) || window.location.origin,
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    // Password validation state
    const [passwordValidation, setPasswordValidation] = useState({
        length: false,
        uppercase: false,
        lowercase: false,
        number: false,
        special: false,
        confirmed: false,
        noName: false,
    });

    const stepperSteps = createSetupSteps(steps);

    const updateDbForm = (field: string, value: string) => {
        setDbForm((prev) => ({ ...prev, [field]: value }));
    };

    const updateAdminForm = (field: string, value: string) => {
        setAdminForm((prev) => ({ ...prev, [field]: value }));

        // Update password validation when password or name changes
        if (field === 'password' || field === 'name' || field === 'password_confirmation') {
            const newPassword = field === 'password' ? value : adminForm.password;
            const newName = field === 'name' ? value : adminForm.name;
            const newConfirmation = field === 'password_confirmation' ? value : adminForm.password_confirmation;

            setPasswordValidation({
                length: newPassword.length >= 12,
                uppercase: /[A-Z]/.test(newPassword),
                lowercase: /[a-z]/.test(newPassword),
                number: /\d/.test(newPassword),
                special: /[@$!%*?&]/.test(newPassword),
                confirmed: newPassword === newConfirmation && newPassword.length > 0,
                noName: newName && newPassword ? !newPassword.toLowerCase().includes(newName.toLowerCase()) : true,
            });
        }
    };

    const getStepTitle = (step: string) => {
        const titles = {
            prerequisites: 'System Prerequisites',
            database: 'Database Configuration',
            roles_seeded: 'System Configuration',
            app_settings: 'Application Setup',
        };
        return titles[step as keyof typeof titles] || 'Setup Step';
    };

    const getStepDescription = (step: string) => {
        const descriptions = {
            prerequisites: 'Verify that your server meets all requirements for running Support Center.',
            database: 'Configure your database connection and initialize the required tables and structure.',
            roles_seeded: "Set up default roles and permissions that will power your support center's access control.",
            app_settings: 'Create your administrator account and configure basic application settings.',
        };
        return descriptions[step as keyof typeof descriptions] || 'Complete this setup step to continue.';
    };

    const renderStepContent = (step: string) => {
        switch (step) {
            case 'prerequisites':
                return (
                    <div className="space-y-6">
                        <div className="space-y-4">
                            <div className="rounded-lg border border-green-200 bg-green-50/50 p-6 dark:border-green-800 dark:bg-green-900/10">
                                <h3 className="flex items-center gap-2 text-lg font-semibold text-green-900 dark:text-green-100">
                                    <Settings className="h-5 w-5" />
                                    System Requirements Check
                                </h3>
                                <p className="mt-2 text-sm text-green-800 dark:text-green-200">
                                    Verifying that your server environment meets all requirements for Support Center.
                                </p>
                            </div>

                            <div className="space-y-3">
                                <h4 className="font-medium text-gray-900 dark:text-gray-100">Requirements being checked:</h4>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {[
                                        { name: 'PHP Version', desc: 'PHP 8.2+ required for Laravel 12' },
                                        { name: 'Database Support', desc: 'MySQL 8.0+ or PostgreSQL 13+' },
                                        { name: 'File Permissions', desc: 'Write access to storage directories' },
                                        { name: 'Required Extensions', desc: 'PDO, OpenSSL, Mbstring, Tokenizer' },
                                        { name: 'Composer Dependencies', desc: 'All required packages installed' },
                                        { name: 'Environment Configuration', desc: 'Basic .env file setup' },
                                    ].map((req, idx) => (
                                        <div key={idx} className="rounded-md border bg-white/50 p-3 dark:bg-gray-800/50">
                                            <div className="flex items-center gap-2">
                                                <CheckCircle className="h-4 w-4 text-green-500" />
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{req.name}</p>
                                            </div>
                                            <p className="ml-6 text-xs text-gray-600 dark:text-gray-400">{req.desc}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {!steps.prerequisites_checked && (
                                <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-900/10">
                                    <div className="flex items-start gap-2">
                                        <Info className="mt-0.5 h-4 w-4 text-blue-600" />
                                        <div>
                                            <p className="text-sm font-medium text-blue-800 dark:text-blue-200">Automatic Check</p>
                                            <p className="text-xs text-blue-700 dark:text-blue-300">
                                                This step will automatically verify your server meets all requirements
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {!steps.prerequisites_checked && (
                            <Button
                                onClick={() => handleStepAction('prerequisites')}
                                disabled={loading === 'prerequisites'}
                                className="w-full"
                                size="lg"
                            >
                                {loading === 'prerequisites' ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Checking requirements...
                                    </>
                                ) : (
                                    <>
                                        <Settings className="mr-2 h-4 w-4" />
                                        Check System Requirements
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </>
                                )}
                            </Button>
                        )}

                        {steps.prerequisites_checked && (
                            <div className="space-y-4">
                                <div className="rounded-lg border border-green-200 bg-green-50/50 p-4 dark:border-green-800 dark:bg-green-900/10">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                                        <span className="font-medium text-green-900 dark:text-green-100">Prerequisites Verified Successfully!</span>
                                    </div>
                                    <p className="mt-2 text-sm text-green-700 dark:text-green-300">
                                        Your server meets all requirements. Ready to proceed to database configuration.
                                    </p>
                                </div>

                                <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-3 dark:border-blue-800 dark:bg-blue-900/10">
                                    <p className="text-sm text-blue-800 dark:text-blue-200">
                                        💡 <strong>Navigation Tip:</strong> You can click on completed steps in the progress indicator to review or
                                        modify previous configurations.
                                    </p>
                                </div>

                                <Button onClick={() => router.visit('/setup/database')} className="w-full" size="lg">
                                    Continue to Database Setup
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            </div>
                        )}
                    </div>
                );

            case 'database':
                return (
                    <div className="space-y-6">
                        <div className="space-y-4">
                            <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-6 dark:border-blue-800 dark:bg-blue-900/10">
                                <h3 className="flex items-center gap-2 text-lg font-semibold text-blue-900 dark:text-blue-100">
                                    <Database className="h-5 w-5" />
                                    Database Configuration & Setup
                                </h3>
                                <p className="mt-2 text-sm text-blue-800 dark:text-blue-200">
                                    Configure your database connection and initialize the required tables and structure.
                                </p>
                            </div>

                            {!steps.database_configured && (
                                <div className="space-y-4">
                                    <h4 className="font-medium text-gray-900 dark:text-gray-100">Database Connection Settings</h4>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label htmlFor="db_host" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Database Host
                                            </Label>
                                            <Input
                                                id="db_host"
                                                type="text"
                                                value={dbForm.host}
                                                onChange={(e) => updateDbForm('host', e.target.value)}
                                                placeholder="localhost"
                                                className="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="db_port" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Port
                                            </Label>
                                            <Input
                                                id="db_port"
                                                type="text"
                                                value={dbForm.port}
                                                onChange={(e) => updateDbForm('port', e.target.value)}
                                                placeholder="3306"
                                                className="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="db_database" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Database Name
                                            </Label>
                                            <Input
                                                id="db_database"
                                                type="text"
                                                value={dbForm.database}
                                                onChange={(e) => updateDbForm('database', e.target.value)}
                                                placeholder="osticket_scp"
                                                className="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="db_username" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Username
                                            </Label>
                                            <Input
                                                id="db_username"
                                                type="text"
                                                value={dbForm.username}
                                                onChange={(e) => updateDbForm('username', e.target.value)}
                                                placeholder="database_user"
                                                className="mt-1"
                                            />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <Label htmlFor="db_password" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Password
                                            </Label>
                                            <Input
                                                id="db_password"
                                                type="password"
                                                value={dbForm.password}
                                                onChange={(e) => updateDbForm('password', e.target.value)}
                                                placeholder="Enter database password"
                                                className="mt-1"
                                            />
                                        </div>
                                    </div>

                                    <Button
                                        onClick={() => handleStepAction('database')}
                                        disabled={loading === 'database' || !dbForm.host || !dbForm.database || !dbForm.username}
                                        className="w-full"
                                        size="lg"
                                    >
                                        {loading === 'database' ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Testing connection...
                                            </>
                                        ) : (
                                            <>
                                                <Database className="mr-2 h-4 w-4" />
                                                Test Database Connection
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </>
                                        )}
                                    </Button>
                                </div>
                            )}

                            {steps.database_configured && !steps.database_migrated && (
                                <div className="space-y-4">
                                    <div className="rounded-lg border border-green-200 bg-green-50/50 p-4 dark:border-green-800 dark:bg-green-900/10">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                                            <span className="font-medium text-green-900 dark:text-green-100">Database Connection Successful!</span>
                                        </div>
                                    </div>

                                    <div className="space-y-3">
                                        <h4 className="font-medium text-gray-900 dark:text-gray-100">Ready to create database tables:</h4>
                                        <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                            <li className="flex items-start gap-2">
                                                <CheckCircle className="mt-0.5 h-4 w-4 text-green-500" />
                                                Create user accounts and authentication tables
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <CheckCircle className="mt-0.5 h-4 w-4 text-green-500" />
                                                Set up ticket management system tables
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <CheckCircle className="mt-0.5 h-4 w-4 text-green-500" />
                                                Initialize department and team structures
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <CheckCircle className="mt-0.5 h-4 w-4 text-green-500" />
                                                Create audit logging and security tables
                                            </li>
                                        </ul>
                                    </div>

                                    <Button onClick={() => handleStepAction('migrate')} disabled={loading === 'migrate'} className="w-full" size="lg">
                                        {loading === 'migrate' ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Creating database tables...
                                            </>
                                        ) : (
                                            <>
                                                <Database className="mr-2 h-4 w-4" />
                                                Initialize Database Tables
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </>
                                        )}
                                    </Button>
                                </div>
                            )}

                            {steps.database_migrated && (
                                <div className="space-y-4">
                                    <div className="rounded-lg border border-green-200 bg-green-50/50 p-4 dark:border-green-800 dark:bg-green-900/10">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                                            <span className="font-medium text-green-900 dark:text-green-100">Database Setup Complete!</span>
                                        </div>
                                        <p className="mt-2 text-sm text-green-700 dark:text-green-300">
                                            All database tables have been created successfully. Ready for system configuration.
                                        </p>
                                    </div>

                                    <Button onClick={() => router.visit('/setup/roles-seeded')} className="w-full" size="lg">
                                        Continue to System Configuration
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>
                );

            case 'roles_seeded':
                return (
                    <div className="space-y-6">
                        <div className="space-y-4">
                            <div className="rounded-lg border border-purple-200 bg-purple-50/50 p-6 dark:border-purple-800 dark:bg-purple-900/10">
                                <h3 className="flex items-center gap-2 text-lg font-semibold text-purple-900 dark:text-purple-100">
                                    <Shield className="h-5 w-5" />
                                    System Configuration & Permissions
                                </h3>
                                <p className="mt-2 text-sm text-purple-800 dark:text-purple-200">
                                    Install default roles and permissions to manage your support team's access levels.
                                </p>
                            </div>

                            <div className="space-y-3">
                                <h4 className="font-medium text-gray-900 dark:text-gray-100">Roles being created:</h4>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {[
                                        { name: 'Support Agent', desc: 'Basic support staff with limited ticket access' },
                                        { name: 'Department Manager', desc: 'Manages department operations and team oversight' },
                                        { name: 'Regional Manager', desc: 'Oversees multiple departments and regions' },
                                        { name: 'System Administrator', desc: 'Full system access and configuration control' },
                                        { name: 'Compliance Auditor', desc: 'Read-only access for audit and compliance' },
                                        { name: 'Knowledge Curator', desc: 'Manages knowledge base and documentation' },
                                    ].map((role, idx) => (
                                        <div key={idx} className="rounded-md border bg-white/50 p-3 dark:bg-gray-800/50">
                                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{role.name}</p>
                                            <p className="text-xs text-gray-600 dark:text-gray-400">{role.desc}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-lg border border-green-200 bg-green-50/50 p-4 dark:border-green-800 dark:bg-green-900/10">
                                <div className="flex items-start gap-2">
                                    <CheckCircle className="mt-0.5 h-4 w-4 text-green-600" />
                                    <div>
                                        <p className="text-sm font-medium text-green-800 dark:text-green-200">Security Features</p>
                                        <p className="text-xs text-green-700 dark:text-green-300">
                                            Hierarchical permissions, department scoping, and audit trail included
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {!steps.roles_seeded && (
                            <Button onClick={() => handleStepAction('seed')} disabled={loading === 'seed'} className="w-full" size="lg">
                                {loading === 'seed' ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Creating roles and permissions...
                                    </>
                                ) : (
                                    <>
                                        <Shield className="mr-2 h-4 w-4" />
                                        Setup Roles & Permissions
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </>
                                )}
                            </Button>
                        )}

                        {steps.roles_seeded && (
                            <div className="space-y-4">
                                <div className="rounded-lg border border-green-200 bg-green-50/50 p-4 dark:border-green-800 dark:bg-green-900/10">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                                        <span className="font-medium text-green-900 dark:text-green-100">
                                            Roles & Permissions Created Successfully!
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm text-green-700 dark:text-green-300">
                                        All system roles and permissions have been configured. Ready for application setup.
                                    </p>
                                </div>

                                <Button onClick={() => router.visit('/setup/app-settings')} className="w-full" size="lg">
                                    Continue to Application Setup
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            </div>
                        )}
                    </div>
                );

            case 'app_settings':
                return (
                    <div className="space-y-6">
                        <div className="space-y-4">
                            <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-6 dark:border-amber-800 dark:bg-amber-900/10">
                                <h3 className="flex items-center gap-2 text-lg font-semibold text-amber-900 dark:text-amber-100">
                                    <UserPlus className="h-5 w-5" />
                                    Application Setup & Administrator Account
                                </h3>
                                <p className="mt-2 text-sm text-amber-800 dark:text-amber-200">
                                    Configure your helpdesk settings and create your first administrator account.
                                </p>
                            </div>

                            <div className="space-y-4">
                                <h4 className="font-medium text-gray-900 dark:text-gray-100">Helpdesk Configuration</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="helpdesk_name" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Helpdesk Name
                                        </Label>
                                        <Input
                                            id="helpdesk_name"
                                            type="text"
                                            value={adminForm.helpdesk_name}
                                            onChange={(e) => updateAdminForm('helpdesk_name', e.target.value)}
                                            placeholder="Support Center"
                                            className="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="helpdesk_url" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Helpdesk URL
                                        </Label>
                                        <Input
                                            id="helpdesk_url"
                                            type="url"
                                            value={adminForm.helpdesk_url}
                                            onChange={(e) => updateAdminForm('helpdesk_url', e.target.value)}
                                            placeholder="https://support.example.com"
                                            className="mt-1"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <h4 className="font-medium text-gray-900 dark:text-gray-100">Administrator Account</h4>
                                    <div className="space-y-3">
                                        <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-900/10">
                                            <div className="flex items-start gap-2">
                                                <Info className="mt-0.5 h-4 w-4 text-blue-600" />
                                                <div>
                                                    <p className="text-sm font-medium text-blue-800 dark:text-blue-200">Administrator Privileges</p>
                                                    <p className="text-xs text-blue-700 dark:text-blue-300">
                                                        Full access to user management, system configuration, and security settings
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                            <li className="flex items-start gap-2">
                                                <Shield className="mt-0.5 h-4 w-4 text-blue-500" />
                                                Password must be at least 12 characters long
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Shield className="mt-0.5 h-4 w-4 text-blue-500" />
                                                Use a unique email address for security
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Shield className="mt-0.5 h-4 w-4 text-blue-500" />
                                                Email will be automatically verified during setup
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Shield className="mt-0.5 h-4 w-4 text-blue-500" />
                                                Additional users can be created after setup
                                            </li>
                                        </ul>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label htmlFor="admin_name" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Full Name
                                            </Label>
                                            <Input
                                                id="admin_name"
                                                type="text"
                                                value={adminForm.name}
                                                onChange={(e) => updateAdminForm('name', e.target.value)}
                                                placeholder="Administrator Name"
                                                className="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="admin_email" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Email Address
                                            </Label>
                                            <Input
                                                id="admin_email"
                                                type="email"
                                                value={adminForm.email}
                                                onChange={(e) => updateAdminForm('email', e.target.value)}
                                                placeholder="admin@example.com"
                                                className="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="admin_password" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Password
                                            </Label>
                                            <Input
                                                id="admin_password"
                                                type="password"
                                                value={adminForm.password}
                                                onChange={(e) => updateAdminForm('password', e.target.value)}
                                                placeholder="Secure password (12+ chars)"
                                                className={`mt-1 ${
                                                    adminForm.password && !passwordValidation.length ? 'border-red-300 focus:border-red-500' : ''
                                                }`}
                                            />
                                            {adminForm.password && (
                                                <div className="mt-2 space-y-1">
                                                    <div className="text-xs font-medium text-gray-700 dark:text-gray-300">Password Requirements:</div>
                                                    <div className="grid gap-1 text-xs">
                                                        <div
                                                            className={`flex items-center gap-1 ${passwordValidation.length ? 'text-green-600' : 'text-red-600'}`}
                                                        >
                                                            {passwordValidation.length ? (
                                                                <CheckCircle className="h-3 w-3" />
                                                            ) : (
                                                                <div className="h-3 w-3 rounded-full border border-red-600" />
                                                            )}
                                                            At least 12 characters
                                                        </div>
                                                        <div
                                                            className={`flex items-center gap-1 ${passwordValidation.uppercase ? 'text-green-600' : 'text-red-600'}`}
                                                        >
                                                            {passwordValidation.uppercase ? (
                                                                <CheckCircle className="h-3 w-3" />
                                                            ) : (
                                                                <div className="h-3 w-3 rounded-full border border-red-600" />
                                                            )}
                                                            One uppercase letter
                                                        </div>
                                                        <div
                                                            className={`flex items-center gap-1 ${passwordValidation.lowercase ? 'text-green-600' : 'text-red-600'}`}
                                                        >
                                                            {passwordValidation.lowercase ? (
                                                                <CheckCircle className="h-3 w-3" />
                                                            ) : (
                                                                <div className="h-3 w-3 rounded-full border border-red-600" />
                                                            )}
                                                            One lowercase letter
                                                        </div>
                                                        <div
                                                            className={`flex items-center gap-1 ${passwordValidation.number ? 'text-green-600' : 'text-red-600'}`}
                                                        >
                                                            {passwordValidation.number ? (
                                                                <CheckCircle className="h-3 w-3" />
                                                            ) : (
                                                                <div className="h-3 w-3 rounded-full border border-red-600" />
                                                            )}
                                                            One number
                                                        </div>
                                                        <div
                                                            className={`flex items-center gap-1 ${passwordValidation.special ? 'text-green-600' : 'text-red-600'}`}
                                                        >
                                                            {passwordValidation.special ? (
                                                                <CheckCircle className="h-3 w-3" />
                                                            ) : (
                                                                <div className="h-3 w-3 rounded-full border border-red-600" />
                                                            )}
                                                            One special character (@$!%*?&)
                                                        </div>
                                                        <div
                                                            className={`flex items-center gap-1 ${passwordValidation.noName ? 'text-green-600' : 'text-red-600'}`}
                                                        >
                                                            {passwordValidation.noName ? (
                                                                <CheckCircle className="h-3 w-3" />
                                                            ) : (
                                                                <div className="h-3 w-3 rounded-full border border-red-600" />
                                                            )}
                                                            Does not contain your name
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor="admin_password_confirmation"
                                                className="text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Confirm Password
                                            </Label>
                                            <Input
                                                id="admin_password_confirmation"
                                                type="password"
                                                value={adminForm.password_confirmation}
                                                onChange={(e) => updateAdminForm('password_confirmation', e.target.value)}
                                                placeholder="Confirm password"
                                                className={`mt-1 ${
                                                    adminForm.password_confirmation && !passwordValidation.confirmed
                                                        ? 'border-red-300 focus:border-red-500'
                                                        : ''
                                                }`}
                                            />
                                            {adminForm.password_confirmation && (
                                                <div className="mt-2">
                                                    <div
                                                        className={`flex items-center gap-1 text-xs ${passwordValidation.confirmed ? 'text-green-600' : 'text-red-600'}`}
                                                    >
                                                        {passwordValidation.confirmed ? (
                                                            <CheckCircle className="h-3 w-3" />
                                                        ) : (
                                                            <div className="h-3 w-3 rounded-full border border-red-600" />
                                                        )}
                                                        Passwords match
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Button
                            onClick={() => handleStepAction('admin')}
                            disabled={
                                loading === 'admin' ||
                                !adminForm.helpdesk_name ||
                                !adminForm.helpdesk_url ||
                                !adminForm.name ||
                                !adminForm.email ||
                                !adminForm.password ||
                                !Object.values(passwordValidation).every(Boolean)
                            }
                            className="w-full"
                            size="lg"
                        >
                            {loading === 'admin' ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Creating administrator account...
                                </>
                            ) : (
                                <>
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    Complete Setup
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </>
                            )}
                        </Button>

                        {steps.admin_created && (
                            <div className="space-y-4">
                                <div className="rounded-lg border border-green-200 bg-green-50/50 p-4 dark:border-green-800 dark:bg-green-900/10">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                                        <span className="font-medium text-green-900 dark:text-green-100">Administrator Created Successfully</span>
                                    </div>
                                </div>

                                <div className="rounded-lg border border-indigo-200 bg-indigo-50/50 p-6 dark:border-indigo-800 dark:bg-indigo-900/10">
                                    <h4 className="flex items-center gap-2 text-lg font-semibold text-indigo-900 dark:text-indigo-100">
                                        <Lock className="h-5 w-5" />
                                        Security Hardening in Progress
                                    </h4>
                                    <p className="mt-2 text-sm text-indigo-800 dark:text-indigo-200">
                                        Implementing security measures and finalizing installation...
                                    </p>

                                    <div className="mt-4 space-y-2">
                                        {[
                                            'Creating security lock file to prevent re-installation',
                                            'Setting configuration files to read-only mode',
                                            'Clearing temporary setup data and caches',
                                            'Enabling production security settings',
                                            'Activating audit logging and monitoring',
                                        ].map((task, idx) => (
                                            <div key={idx} className="flex items-center gap-2 text-xs text-indigo-700 dark:text-indigo-300">
                                                <CheckCircle className="h-3 w-3" />
                                                {task}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                );

            default:
                return (
                    <div className="space-y-6">
                        <div className="rounded-lg border border-gray-200 bg-gray-50/50 p-6 dark:border-gray-700 dark:bg-gray-800/50">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Setup Step</h3>
                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Complete this setup step to continue with the installation process.
                            </p>
                        </div>
                    </div>
                );
        }
    };

    const handleStepNavigation = (stepKey: string) => {
        const stepRoutes: Record<string, string> = {
            prerequisites: '/setup/prerequisites',
            database: '/setup/database',
            roles_seeded: '/setup/roles-seeded',
            app_settings: '/setup/app-settings',
        };

        const route = stepRoutes[stepKey];

        if (route) {
            router.visit(route);
        } else {
            toast.error(`No route found for step: ${stepKey}`);
        }
    };

    const handleStepAction = async (stepKey: string) => {
        setLoading(stepKey);

        try {
            switch (stepKey) {
                case 'prerequisites':
                    router.reload({
                        onSuccess: () => {
                            toast.success('System requirements verified successfully!');
                            setTimeout(() => router.visit('/setup/database'), 1500);
                        },
                    });
                    return;

                case 'database': {
                    router.post('/setup/database', dbForm, {
                        onSuccess: () => {
                            toast.success('Database connection successful!');
                            setTimeout(() => router.reload(), 1000);
                        },
                        onError: (errors) => {
                            const errorMessage = typeof errors === 'string' ? errors : Object.values(errors).join(', ');
                            toast.error(errorMessage);
                        },
                    });
                    return;
                }

                case 'migrate': {
                    router.post(
                        '/setup/migrate',
                        {},
                        {
                            onSuccess: () => {
                                toast.success('Database migrations completed successfully!');
                                setTimeout(() => router.visit('/setup/roles-seeded'), 1000);
                            },
                            onError: (errors) => {
                                const errorMessage = typeof errors === 'string' ? errors : Object.values(errors).join(', ');
                                toast.error(errorMessage);
                            },
                        },
                    );
                    return;
                }

                case 'seed': {
                    router.post(
                        '/setup/seed',
                        {},
                        {
                            onSuccess: () => {
                                toast.success('Roles and permissions created successfully!');
                                setTimeout(() => router.visit('/setup/app-settings'), 1000);
                            },
                            onError: (errors) => {
                                const errorMessage = typeof errors === 'string' ? errors : Object.values(errors).join(', ');
                                toast.error(errorMessage);
                            },
                        },
                    );
                    return;
                }

                case 'admin': {
                    router.post('/setup/admin', adminForm, {
                        onSuccess: () => {
                            toast.success('Administrator account created successfully!');
                            setTimeout(() => completeSetup(), 1000);
                        },
                        onError: (errors) => {
                            const errorMessage = typeof errors === 'string' ? errors : Object.values(errors).join(', ');
                            toast.error(errorMessage);
                        },
                    });
                    return;
                }

                case 'next':
                    // Navigate to the next step based on current step
                    switch (currentStep) {
                        case 'database':
                            router.visit('/setup/roles-seeded');
                            break;
                        case 'roles_seeded':
                            router.visit('/setup/app-settings');
                            break;
                        default:
                            router.reload();
                    }
                    return;
            }

            // This code is only reached for cases that don't use Inertia (like 'prerequisites' and 'next')
            // All other cases now return early after calling router.post
        } catch (error) {
            toast.error(`Network error occurred: ${error instanceof Error ? error.message : 'Unknown error'}`);
        } finally {
            setLoading(null);
        }
    };

    const completeSetup = () => {
        router.post(
            '/setup/complete',
            {},
            {
                onStart: () => setFinishing(true),
                onSuccess: () => {
                    toast.success('Setup completed successfully! Redirecting to login...');
                },
                onError: () => {
                    toast.error('Failed to complete setup');
                    setFinishing(false);
                },
            },
        );
    };

    const isComplete = Object.values(steps).every(Boolean);

    return (
        <>
            <Head title="Support Center Setup" />

            <div className="relative min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 transition-colors dark:from-gray-900 dark:to-gray-800">
                {/* Reset button */}
                <Button variant="outline" size="sm" onClick={handleReset} className="absolute top-4 right-4">
                    Reset Setup
                </Button>

                <div className="flex min-h-screen items-center justify-center p-4">
                    <div className="w-full max-w-4xl">
                        {/* Header */}
                        <div className="mb-8 text-center">
                            <h1 className="mb-2 text-4xl font-bold text-gray-900 transition-colors dark:text-white">Welcome to Support Center</h1>
                            <p className="text-xl text-gray-600 transition-colors dark:text-gray-400">Let's set up your support control panel</p>
                        </div>

                        {/* Main Setup Layout - Responsive */}
                        <div className="grid gap-8 lg:grid-cols-[300px_1fr]">
                            {/* Stepper - Left side on desktop, top on mobile */}
                            <div className="order-1 lg:order-1">
                                <Stepper
                                    steps={stepperSteps.map((step) => {
                                        const mappedStep = {
                                            ...step,
                                            completed: step.completed,
                                            disabled: false,
                                            loading: loading === step.key,
                                            onClick: step.completed ? () => handleStepNavigation(step.key) : undefined,
                                        };
                                        return mappedStep;
                                    })}
                                    currentStep={currentStep}
                                    orientation="horizontal"
                                    showConnectors={true}
                                    variant="default"
                                    className="lg:hidden" // Mobile horizontal stepper
                                />
                                <Stepper
                                    steps={stepperSteps.map((step) => ({
                                        ...step,
                                        completed: step.completed,
                                        disabled: false,
                                        loading: loading === step.key,
                                        onClick: step.completed ? () => handleStepNavigation(step.key) : undefined,
                                    }))}
                                    currentStep={currentStep}
                                    orientation="vertical"
                                    showConnectors={true}
                                    variant="default"
                                    className="hidden lg:block" // Desktop vertical stepper
                                />
                            </div>

                            {/* Step Content - Right side on desktop, bottom on mobile */}
                            <div className="order-2 lg:order-2">
                                {currentStep && (
                                    <Card className="transition-all duration-300">
                                        <CardHeader>
                                            <h2 className="text-2xl font-bold text-gray-900 dark:text-white">{getStepTitle(currentStep)}</h2>
                                            <p className="text-gray-600 dark:text-gray-400">{getStepDescription(currentStep)}</p>
                                        </CardHeader>
                                        <CardContent className="space-y-6">{renderStepContent(currentStep)}</CardContent>
                                    </Card>
                                )}
                            </div>
                        </div>

                        {/* Completion Card */}
                        {isComplete && (
                            <Card className="mt-6 border-emerald-200 bg-emerald-50/50 transition-colors dark:border-emerald-800 dark:bg-emerald-900/10">
                                <CardContent className="pt-6">
                                    <div className="text-center">
                                        <CheckCircle className="mx-auto mb-4 h-12 w-12 text-emerald-600 dark:text-emerald-400" />
                                        <h3 className="mb-2 text-lg font-semibold text-emerald-800 dark:text-emerald-200">Setup Complete!</h3>
                                        <p className="mb-4 text-emerald-700 dark:text-emerald-300">
                                            Your Support Center is ready to use. You can now log in with your administrator account.
                                        </p>
                                        <Button onClick={() => router.visit('/login')} size="lg">
                                            Go to Login
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {finishing && (
                            <div className="fixed inset-0 z-50 flex items-center justify-center bg-white/70 backdrop-blur-sm dark:bg-gray-900/70">
                                <div className="flex flex-col items-center gap-4">
                                    <Loader2 className="text-primary h-8 w-8 animate-spin" />
                                    <p className="text-sm font-medium text-gray-800 dark:text-gray-200">Finishing installation please wait</p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
