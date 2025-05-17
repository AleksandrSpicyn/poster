<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperProject
 */
class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prompt',
    ];

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}
