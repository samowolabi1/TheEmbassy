<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'community_muted_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Church staff can open the admin dashboard.
     */
    public function isStaff(): bool
    {
        return $this->role !== null && array_key_exists($this->role, config('roles.roles'));
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Whether this person's role covers an admin area (see config/roles.php).
     */
    /**
     * Approved members (and staff) can open the community.
     */
    public function canJoinCommunity(): bool
    {
        return $this->isStaff() || $this->member?->status === Member::ACTIVE;
    }

    /**
     * Moderators remove messages, mute people and post in locked rooms.
     */
    public function moderatesCommunity(): bool
    {
        return $this->canManage('community');
    }

    public function isMutedInCommunity(): bool
    {
        return $this->community_muted_until?->isFuture() ?? false;
    }

    /**
     * The name others see in the community: the member's name, or the account name.
     */
    public function communityName(): string
    {
        return $this->member?->name ?: $this->name;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', preg_replace('/[^\pL\s]/u', '', $this->communityName())))
            ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: '?';
    }

    public function canManage(string $area): bool
    {
        $areas = $this->isStaff() ? config("roles.roles.{$this->role}.areas") : [];

        return in_array('*', $areas, true) || in_array($area, $areas, true);
    }

    public function roleName(): ?string
    {
        return $this->isStaff() ? config("roles.roles.{$this->role}.name") : null;
    }

    /**
     * Their record on the church register, if they're a member.
     *
     * @return HasOne<Member, $this>
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * The shop this user runs on the marketplace, if any.
     *
     * @return HasOne<Shop, $this>
     */
    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }
}
