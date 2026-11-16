<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Общие операции хранения; доменные условия выборок остаются в конкретных репозиториях.
 * Блокировки удерживает транзакция вызывающей операции, а не отдельная транзакция чтения.
 *
 * @template TModel of Model
 */
abstract readonly class EloquentRepository
{
    /** @return class-string<TModel> */
    abstract protected function model(): string;

    /** @return Builder<TModel> */
    protected function query(): Builder
    {
        return ($this->model())::query();
    }

    /** @return TModel|null */
    public function find(int|string $id): ?Model
    {
        return $this->query()->find($id);
    }

    /** @return TModel */
    public function findOrFail(int|string $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    /** @return TModel|null */
    public function findLocked(int|string $id): ?Model
    {
        return $this->query()->lockForUpdate()->find($id);
    }

    /** @return TModel */
    public function findLockedOrFail(int|string $id): Model
    {
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param TModel $model
     * @return TModel
     */
    public function refresh(Model $model): Model
    {
        return $model->refresh();
    }

    /**
     * @param TModel $model
     * @return TModel|null
     */
    public function fresh(Model $model): ?Model
    {
        return $model->fresh();
    }

    /** @param TModel $model */
    public function save(Model $model): void
    {
        $model->save();
    }

    /**
     * @param TModel $model
     * @param array<string, mixed> $attributes Атрибуты на границе Eloquent, не транспорт сценария.
     */
    public function update(Model $model, array $attributes): bool
    {
        return $model->update($attributes);
    }

    /** @param TModel $model */
    public function delete(Model $model): bool
    {
        return $model->delete() === true;
    }
}
