<?php

namespace App\Support;

use App\Models\ProductUpdateRead;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductUpdateService
{
    /** @return Collection<int, array<string, mixed>> */
    public function allFor(User $user): Collection
    {
        $readIds = Cache::remember(
            $this->readCacheKey($user),
            now()->addMinutes(5),
            fn (): array => ProductUpdateRead::query()
                ->where('user_id', $user->id)
                ->pluck('update_id')
                ->all(),
        );

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
        Cache::forget($this->readCacheKey($user));
    }

    public function markAllRead(User $user): void
    {
        $timestamp = now();
        $rows = collect(config('product_updates.items', []))
            ->map(fn (array $update): array => [
                'user_id' => $user->id,
                'update_id' => $update['id'],
                'read_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->all();

        if ($rows !== []) {
            ProductUpdateRead::query()->upsert(
                $rows,
                ['user_id', 'update_id'],
                ['read_at', 'updated_at'],
            );
        }

        Cache::forget($this->readCacheKey($user));
    }

    private function readCacheKey(User $user): string
    {
        return 'product-updates.read.'.$user->getKey();
    }
}
