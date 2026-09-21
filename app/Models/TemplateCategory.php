<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateCategory extends Model
{
    use HasFactory;

    protected $fillable = ['template_id', 'category_id', 'allocated_amount', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}