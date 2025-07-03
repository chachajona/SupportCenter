<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Department extends Model
{
    use HasFactory;

    /**
     * The attributes that are guarded from mass assignment.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'path', // Managed internally through boot callbacks
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent department.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Get the child departments.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    /**
     * Get active child departments.
     */
    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    /**
     * Get the department manager.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get users in this department.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get active users in this department.
     */
    public function activeUsers(): HasMany
    {
        return $this->users()->whereNotNull('email_verified_at');
    }

    /**
     * Get tickets belonging to this department.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get all ancestor departments using materialized path.
     */
    public function getAncestors(): Collection
    {
        if (! $this->path) {
            return collect();
        }

        $paths = array_filter(explode('/', trim($this->path, '/')));

        if (empty($paths)) {
            return collect();
        }

        // Cast all elements to integers to prevent SQL injection
        $sanitizedPaths = array_map('intval', $paths);

        // Fetch ancestors without database-specific ordering
        $ancestors = static::whereIn('id', $sanitizedPaths)->get();

        // Sort in PHP to maintain correct hierarchy order (database-agnostic)
        return $ancestors->sortBy(function ($ancestor) use ($sanitizedPaths) {
            return array_search($ancestor->id, $sanitizedPaths);
        })->values();
    }

    /**
     * Get all descendant departments using materialized path.
     */
    public function getDescendants(): Collection
    {
        if (! $this->path) {
            return collect();
        }

        return static::where('path', 'LIKE', $this->path.$this->id.'/%')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get all descendant department IDs including self.
     */
    public function getDescendantIds(): array
    {
        $descendants = $this->getDescendants();
        $ids = $descendants->pluck('id')->toArray();
        $ids[] = $this->id;

        return $ids;
    }

    /**
     * Check if this department is an ancestor of another department.
     */
    public function isAncestorOf(Department $department): bool
    {
        if (! $department->path) {
            return false;
        }

        return str_contains($department->path, '/'.$this->id.'/');
    }

    /**
     * Check if this department is a descendant of another department.
     */
    public function isDescendantOf(Department $department): bool
    {
        return $department->isAncestorOf($this);
    }

    /**
     * Get the full department hierarchy path as names.
     */
    public function getHierarchyPath(): string
    {
        $ancestors = $this->getAncestors();
        $names = $ancestors->pluck('name')->toArray();
        $names[] = $this->name;

        return implode(' > ', $names);
    }

    /**
     * Scope to active departments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to root departments (no parent).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Boot the model to handle path updates.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($department) {
            if ($department->parent_id) {
                $parent = static::find($department->parent_id);
                if ($parent) {
                    $department->path = $parent->path.$parent->id.'/';
                } else {
                    // If parent doesn't exist, set path to empty to maintain consistency
                    $department->path = '/';
                }
            } else {
                $department->path = '/';
            }
        });

        static::updating(function ($department) {
            if ($department->isDirty('parent_id')) {
                // Prevent circular references: check if new parent is a descendant
                if ($department->parent_id) {
                    $newParent = static::find($department->parent_id);

                    if (! $newParent) {
                        // Parent doesn't exist, set to root
                        $department->path = '/';
                    } else {
                        // Check for circular reference
                        if ($department->exists && $newParent->isDescendantOf($department)) {
                            throw new \InvalidArgumentException(
                                "Cannot set department '{$newParent->name}' as parent: it would create a circular reference. ".
                                'The selected parent is a descendant of the current department.'
                            );
                        }

                        $department->path = $newParent->path.$newParent->id.'/';
                    }
                } else {
                    $department->path = '/';
                }

                // Update all descendants' paths
                $department->updateDescendantPaths();
            }
        });
    }

    /**
     * Update paths for all descendant departments.
     */
    protected function updateDescendantPaths(): void
    {
        $descendants = static::where('path', 'LIKE', $this->getOriginal('path').$this->id.'/%')->get();

        foreach ($descendants as $descendant) {
            $oldPath = $this->getOriginal('path').$this->id.'/';
            $newPath = $this->path.$this->id.'/';
            $descendant->path = str_replace($oldPath, $newPath, $descendant->path);
            $descendant->saveQuietly();
        }
    }
}
