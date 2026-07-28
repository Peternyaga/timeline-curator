<?php

namespace App\Support;

use App\Models\ProductUpdateRead;
use App\Models\User;
use Illuminate\Support\Collection;

class ProductUpdateService
{
    /** @return Collection<int, array<string, mixed>> */
    public function allFor(User $user): Collection
    {
        $readIds = ProductUpdateRead::query()
            ->where('user_id', $user->id)
            ->pluck('update_id')
            ->all();

        return collect(config('product_updates.items', []))
            ->map(fn (array $update): array => [
                ...$update,
                'read' => in_array($update['id'], $readIds, true),
            ])
            ->sortByDesc('published_at')
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function unreadFor(User $user): Collection
    {
        return $this->allFor($user)->where('read', false)->values();
    }

    public function exists(string $updateId): bool
    {
        return collect(config('product_updates.items', []))->contains(
            fn (array $update): bool => $update['id'] === $updateId,
        );
    }

    public function markRead(User $user, string $updateId): void
    {
        ProductUpdateRead::query()->updateOrCreate(
            ['user_id' => $user->id, 'update_id' => $updateId],
            ['read_at' => now()],
        );
    }

    public function markAllRead(User $user): void
    {
        foreach (config('product_updates.items', []) as $update) {
            $this->markRead($user, $update['id']);
        }
    }
}
