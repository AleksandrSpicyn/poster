<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;

/** @extends EloquentRepository<OpusClip> */
final readonly class OpusClipRepository extends EloquentRepository
{
    protected function model(): string
    {
        return OpusClip::class;
    }

    /** @param iterable<ImportedClipData> $clips */
    public function import(OpusSourceData $source, iterable $clips): Project
    {
        return DB::transaction(function () use ($source, $clips): Project {
            $project = Project::query()->where('clipping_workspace', Project::OPUS_CLIP_WORKSPACE)->lockForUpdate()->first();

            if (! $project instanceof Project) {
                throw new RuntimeException('Select a Poster project with opus:configure before importing clips.');
            }

            $origin = $project->opusSources()->firstOrNew(['external_id' => $source->id]);
            $oldName = $this->sourceProjectName($origin);

            if (filled($source->title)) {
                $origin->title = $source->title;
            }

            $origin->game = $source->game ?? $origin->game;
            $origin->save();

            $name = $this->sourceProjectName($origin);

            if ($origin->wasRecentlyCreated) {
                $card = new Project(['name' => $name, 'prompt' => '', 'is_active' => $project->is_active]);
                $card->opus_source_id = $origin->id;
                $card->save();
            } elseif ($oldName !== $name) {
                Project::query()->where('opus_source_id', $origin->id)->where('name', $oldName)->update(['name' => $name]);
            }

            foreach ($clips as $clip) {
                $record = $this->query()->firstOrNew(['opus_source_id' => $origin->id, 'clip_id' => $clip->id]);
                $oldLinks = collect($record->publications ?? [])
                    ->filter(fn(array $post): bool => $post['url'] !== null)
                    ->whereNotIn('url', array_column($clip->publications, 'url'));

                $record->fill([
                    'title' => $clip->title ?? $record->title,
                    'duration_ms' => $clip->durationMs ?? $record->duration_ms,
                    'score' => $clip->score ?? $record->score,
                    'publications' => [...$clip->publications, ...$oldLinks->values()->all()],
                ])->save();
            }

            return $project;
        }, attempts: 3);
    }

    private function sourceProjectName(OpusSource $source): string
    {
        $title = trim((string) $source->title);

        return mb_substr($title !== '' ? $title : ($source->game ?? 'OpusClip ' . $source->external_id), 0, 255);
    }

    /** @return LengthAwarePaginator<int, OpusClip> */
    public function paginate(Project $project, int $perPage, int $page): LengthAwarePaginator
    {
        $clips = $this->query();

        if ($project->opus_source_id !== null) {
            $clips->where('opus_source_id', $project->opus_source_id);
        } else {
            $clips->whereRelation('source', 'project_id', $project->id);
        }

        return $clips->with(['source','latestMetrics'])
            ->orderByDesc('id')->paginate($perPage, page: $page)->withQueryString();
    }

    /** @return LazyCollection<int, OpusClip> */
    public function forMetrics(Project $project): LazyCollection
    {
        return $this->query()->whereRelation('source', 'project_id', $project->id)->lazyById();
    }
}
