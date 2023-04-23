<?php

namespace App\Models\ORM;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class ORM extends Model
{
  protected int $id = 0;

  public static function getTableName(): string
  {
    return with(new static)->getTable();
  }

  /**
   * Load object (get last result)
   *
   * @return static|object|null
   */
  public static function loadBy(?int $value)
  {
    if(!$value) {
      return null;
    }

    $className = static::class;
    $object = $className::find($value);

    return $object;
  }

  public function id(): ?int
  {
    return $this->attributes['id'] ?? null;
  }

  public static function loadByOrDie(?string $value)
  {
    $object = self::loadBy($value);

    $msg = 'Object ' . self::getTableName() . ' not loaded: id' . $value;
    abort_if(!$object, Response::HTTP_INTERNAL_SERVER_ERROR, $msg);

    return $object;
  }

  // Disable Laravel time fields
  public $timestamps = false;


  public function save_mr(bool $flushAffectedCaches = true): ?int
  {
    if(method_exists($this, 'beforeSave')) {
      $this->beforeSave();
    }

    $this->save();

    if(method_exists($this, 'afterSave')) {
      $this->afterSave();
    }

    if($flushAffectedCaches && method_exists($this, 'flushAffectedCaches')) {
      $this->flushAffectedCaches();
    }

    if(method_exists($this, 'selfFlush')) {
      $this->selfFlush();
    }

    return $this->id();
  }

  public function delete_mr(bool $skipAffectedCache = true): bool
  {
    if(method_exists($this, 'beforeDelete')) {
      $this->beforeDelete();
    }

    $results = $this->delete();
    abort_if(!$results, Response::HTTP_INTERNAL_SERVER_ERROR, 'Object was not deleted');

    if($skipAffectedCache && method_exists($this, 'afterDelete')) {
      $this->afterDelete();
    }

    return true;
  }
}
