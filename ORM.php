<?php

namespace App\Models\ORM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;

class ORM extends Model
{
  protected int $id = 0;

  public static function getTableName(): string
  {
    return with(new static)->getTable();
  }

  public static function loadBy(?int $value): ?static
  {
    return static::find((int)$value);
  }

  public function id(): ?int
  {
    return $this->attributes['id'] ?? null;
  }

  /**
   * @throws ModelNotFoundException
   */
  public static function loadByOrDie(int $value): static
  {
    return self::findOrFail($value);
  }

  // Disable Laravel time fields
  public $timestamps = false;


  public function save_mr(bool $skipFlushAffectedCaches = false): ?int
  {
    if (method_exists($this, 'beforeSave')) {
      $this->beforeSave();
    }

    $this->save();

    if (method_exists($this, 'afterSave')) {
      $this->afterSave();
    }

    if ($skipFlushAffectedCaches && method_exists($this, 'flushAffectedCaches')) {
      $this->flushAffectedCaches();
    }

    if (method_exists($this, 'flush')) {
      $this->flush();
    }

    return $this->id();
  }

  public function delete_mr(bool $skipFlushAffectedCaches = false): bool
  {
    if (method_exists($this, 'beforeDelete')) {
      $this->beforeDelete();
    }

    if (method_exists($this, 'flush')) {
      $this->flush();
    }

    $results = $this->delete();
    abort_if(!$results, Response::HTTP_INTERNAL_SERVER_ERROR, 'Object was not deleted');

    if ($skipFlushAffectedCaches && method_exists($this, 'afterDelete')) {
      $this->afterDelete();
    }

    return true;
  }
}
