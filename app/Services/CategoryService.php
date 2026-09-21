<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryService
{
    public function getUserCategories(int $userId): Collection
    {
        return Category::where('is_archived', false)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere(function ($q) {
                        $q->where('is_default', true)->whereNull('user_id');
                    });
            })
            ->orderBy('sort_order')
            ->get();
    }

    public function createCategory(int $userId, array $data): Category
    {
        return Category::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'expense',
            'parent_id' => $data['parent_id'] ?? null,
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'is_default' => false,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        if ($category->is_default) {
            throw new \Exception('Cannot modify a default category.');
        }

        $category->update($data);
        return $category;
    }

    public function archiveCategory(Category $category): void
    {
        if ($category->is_default) {
            throw new \Exception('Cannot archive a default category.');
        }

        $category->update(['is_archived' => true]);
    }
}
