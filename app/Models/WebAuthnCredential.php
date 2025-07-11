<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebAuthnCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laragear\WebAuthn\Models\WebAuthnCredential as BaseWebAuthnCredential;

/**
 * @property string $id
 * @property string $authenticatable_type
 * @property int $authenticatable_id
 * @property string $user_id
 * @property string|null $alias
 * @property int|null $counter
 * @property string $rp_id
 * @property string $origin
 * @property array<array-key, mixed>|null $transports
 * @property string|null $aaguid
 * @property mixed $public_key
 * @property string $attestation_format
 * @property array<array-key, mixed>|null $certificates
 * @property int|null $disabled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable $authenticatable
 *
 * @method static \Database\Factories\WebAuthnCredentialFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereAaguid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereAttestationFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereAuthenticatableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereAuthenticatableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereCertificates($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereCounter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereCreatedAt($value)
 * @method static Builder<static>|WebAuthnCredential whereDisabled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereDisabledAt($value)
 * @method static Builder<static>|WebAuthnCredential whereEnabled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential wherePublicKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereRpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereTransports($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebAuthnCredential whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class WebAuthnCredential extends BaseWebAuthnCredential
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): WebAuthnCredentialFactory
    {
        return WebAuthnCredentialFactory::new();
    }
}
