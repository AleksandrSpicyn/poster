<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\Billing\ProductionQuoteData;
use App\Enums\SeriesAudioMode;
use App\Models\EpisodeRevision;
use App\Models\Series;
use App\Repositories\ProductionBudgetRepository;
use App\Settings\GeneralSettings;
use Illuminate\Validation\ValidationException;

final readonly class ProductionQuoteCalculator
{
    public function __construct(private VideoGeneratorFactory $generators, private GeneralSettings $settings, private ProductionBudgetRepository $budgets) {}

    public function calculate(?Series $series, ?EpisodeRevision $revision): ProductionQuoteData
    {
        $duration = $series instanceof Series ? $series->specification['shot_duration_seconds'] ?? 15 : ($this->settings->duration);
        $audio = $series->specification['audio_mode'] ?? SeriesAudioMode::NATIVE->value;
        $provider = $this->generators->primary();
        $caps = $this->generators->make($provider)->capabilities();
        $video = [
            'provider' => $provider->value,
            'model' => $caps->model,
            'resolution' => $caps->defaultResolution,
            'aspect_ratio' => $caps->defaultAspectRatio,
            'generate_audio' => $audio === SeriesAudioMode::NATIVE->value && $caps->supportsAudioGeneration,
            'duration' => $duration,
        ];

        if (PilotShotQuote::seconds($video) === null) {
            throw ValidationException::withMessages(['video' => 'Выберите для производства Seedance Mini 480p или 720p, вертикальный кадр и нативный звук.']);
        }

        $shots = $revision instanceof EpisodeRevision ? $this->missingShots($this->budgets->unproducedShotDurations($revision), $video) : [];
        $seconds = $revision instanceof EpisodeRevision ? array_sum($shots) : ($series instanceof Series ? $series->specification['episode_duration_seconds'] ?? 15 : ($this->settings->duration));

        if (! is_int($seconds) || $seconds < 0) {
            throw ValidationException::withMessages(['balance' => 'Проверьте длительность эпизода.']);
        }

        return new ProductionQuoteData($seconds, $shots, $video, $revision?->id);
    }

    /**
     * @param array<int, int> $durations
     * @param array<string, mixed> $video
     * @return array<int, int>
     */
    private function missingShots(array $durations, array $video): array
    {
        $result = [];

        foreach ($durations as $shotId => $milliseconds) {
            $seconds = intdiv($milliseconds, 1000);

            if (PilotShotQuote::seconds([...$video, 'duration' => $seconds]) === null || $milliseconds !== $seconds * 1000) {
                throw ValidationException::withMessages(['video' => 'Длительность кадра не входит в оплачиваемый пресет.']);
            }

            $result[$shotId] = $seconds;
        }

        return $result;
    }
}
