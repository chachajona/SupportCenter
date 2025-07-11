<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $step
 * @property bool $completed
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property array<array-key, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus whereCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus whereStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SetupStatus whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SetupStatus extends Model
{
    use HasFactory;

    protected $table = 'setup_status';

    protected $fillable = [
        'step',
        'completed',
        'completed_at',
        'data',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'completed_at' => 'datetime',
        'data' => 'array',
    ];

    /**
     * Mark a setup step as completed with optional data
     */
    public static function markCompleted(string $step, array $data = []): void
    {
        static::updateOrCreate(
            ['step' => $step],
            [
                'completed' => true,
                'completed_at' => now(),
                'data' => $data,
            ]
        );
    }

    /**
     * Check if a setup step is completed
     */
    public static function isCompleted(string $step): bool
    {
        return static::where('step', $step)
            ->where('completed', true)
            ->exists();
    }

    /**
     * Get all setup steps with their completion status
     */
    public static function getAllSteps(): array
    {
        return static::orderBy('id')->get()->mapWithKeys(function ($step) {
            return [$step->step => $step->completed];
        })->toArray();
    }

    /**
     * Check if the entire setup process is completed
     */
    public static function isSetupCompleted(): bool
    {
        return static::isCompleted('setup_completed');
    }

    /**
     * Get setup progress percentage
     */
    public static function getProgress(): float
    {
        $steps = ['database_migration', 'roles_seeded', 'permissions_seeded', 'admin_created'];
        $totalSteps = count($steps);
        $completedSteps = static::whereIn('step', $steps)
            ->where('completed', true)
            ->count();

        return $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;
    }

    /**
     * Reset all setup steps (for emergency reset)
     */
    public static function resetSetup(): void
    {
        static::query()->update([
            'completed' => false,
            'completed_at' => null,
            'data' => null,
        ]);
    }

    /**
     * Get the current step that needs to be completed
     */
    public static function getCurrentStep(): ?string
    {
        $steps = ['database_migration', 'roles_seeded', 'permissions_seeded', 'admin_created'];

        foreach ($steps as $step) {
            if (! static::isCompleted($step)) {
                return $step;
            }
        }

        if (! static::isCompleted('setup_completed')) {
            return 'setup_completed';
        }

        return null;
    }
}
