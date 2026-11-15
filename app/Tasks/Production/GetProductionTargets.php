<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProjectContentMode;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\Config;
use Illuminate\Validation\ValidationException;

readonly class GetProductionTargets
{
    public function __construct(#[Config('services.admin.email')] private string $ownerEmail) {}

    /** @return list<array{series: Series|null, actor: User}> */
    public function run(Project $project, ?int $seriesId, ?User $actor): array
    {
        $project->refresh();

        if (! $project->is_active) {
            if ($actor instanceof User) {
                throw ValidationException::withMessages(['project' => 'Enable this project before starting production.']);
            }

            return [];
        }

        if ($project->content_mode === ProjectContentMode::SHORT) {
            if ($seriesId !== null) {
                throw ValidationException::withMessages(['series_id' => 'Select a series project before requesting an episode.']);
            }

            $actor ??= User::query()->where('email', $this->ownerEmail)->first();

            return $actor === null ? [] : [['series' => null, 'actor' => $actor]];
        }

        $series = $project->series()->with('automationUser')->whereNull('archived_at');

        if ($seriesId !== null) {
            $selected = $series->findOrFail($seriesId);
            $items = collect([$selected]);
        } else {
            $items = $series->where('automation_enabled', true)->orderBy('id')->get();
        }

        return array_values($items->filter(fn(Series $series): bool => $series->automation_enabled)
            ->map(fn(Series $series): array => ['series' => $series, 'actor' => $actor ?? $series->automationUser])
            ->filter(fn(array $target): bool => $target['actor'] instanceof User)
            ->values()
            ->all());
    }
}
