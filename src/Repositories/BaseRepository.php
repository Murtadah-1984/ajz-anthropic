<?php

namespace Ajz\Anthropic\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;

abstract class BaseRepository
{
    /**
     * The database connection.
     *
     * @var ConnectionInterface
     */
    protected ConnectionInterface $connection;

    /**
     * The model instance.
     *
     * @var Model
     */
    protected Model $model;

    /**
     * Create a new repository instance.
     *
     * @param ConnectionInterface $connection
     * @param Model $model
     */
    public function __construct(ConnectionInterface $connection, Model $model)
    {
        $this->connection = $connection;
        $this->model = $model;
    }

    /**
     * Get all records.
     *
     * @param array $columns
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    /**
     * Get paginated records.
     *
     * @param int $perPage
     * @param array $columns
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->paginate($perPage, $columns);
    }

    /**
     * Find a record by ID.
     *
     * @param int $id
     * @return Model|null
     */
    public function find(int $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * Create a new record.
     *
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update a record.
     *
     * @param int $id
     * @param array $data
     * @return Model
     */
    public function update(int $id, array $data): Model
    {
        $model = $this->find($id);
        if (!$model) {
            throw new \RuntimeException("Model with ID {$id} not found");
        }

        $model->update($data);
        return $model->fresh();
    }

    /**
     * Delete a record.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $model = $this->find($id);
        if (!$model) {
            return false;
        }

        return $model->delete();
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    protected function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    /**
     * Commit a database transaction.
     *
     * @return void
     */
    protected function commit(): void
    {
        $this->connection->commit();
    }

    /**
     * Rollback a database transaction.
     *
     * @return void
     */
    protected function rollback(): void
    {
        $this->connection->rollBack();
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param callable $callback
     * @return mixed
     * @throws \Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    /**
     * Get a new query builder instance.
     *
     * @return Builder
     */
    protected function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Get records by a field value.
     *
     * @param string $field
     * @param mixed $value
     * @return Collection
     */
    protected function getBy(string $field, mixed $value): Collection
    {
        return $this->model->where($field, $value)->get();
    }

    /**
     * Get records by multiple field values.
     *
     * @param array $criteria
     * @return Collection
     */
    protected function getByCriteria(array $criteria): Collection
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->get();
    }

    /**
     * Get the model instance.
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Set the model instance.
     *
     * @param Model $model
     * @return void
     */
    public function setModel(Model $model): void
    {
        $this->model = $model;
    }

    /**
     * Get the database connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}
