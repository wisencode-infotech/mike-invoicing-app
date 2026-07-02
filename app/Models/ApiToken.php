<?php

namespace App\Models;

use App\Policies\ApiTokenPolicy;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'token_hash', 'abilities_json', 'last_used_at', 'active'])]
#[Hidden(['token_hash'])]
#[UsePolicy(ApiTokenPolicy::class)]
class ApiToken extends Model
{
    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'abilities_json' => 'array',
            'last_used_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }
}
