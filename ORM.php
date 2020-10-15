<?php

namespace Myschik\ORM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ORM extends Model
{
  protected $id = 0;

  public static function getTableName(): string
  {
    return with(new static)->getTable();
  }

  /**
   * Fields relations
   *
   * @return mixed
   */
  public static function getMrRelation()
  {
    return with(new static)->within;
  }


  /**
   * Get object from cache by id or call
   *
   * @param int $id
   * @param string $table
   * @param callable $object
   * @return mixed
   */
  private static function GetCachedObject(int $id, string $table, callable $object): ?object
  {
    $cache_key = $table . '_' . $id;
    return Cache::rememberForever($cache_key, function () use ($object) {
      return $object();
    });
  }

  /**
   * Load object (get last result)
   *
   * @param string $value
   * @param string $field
   * @param bool $relation // Догрузить связи
   * @return static|object|null
   */
  public static function loadBy(?string $value, string $field = 'id', bool $relation = false)
  {
    $result = null;

    if (!$value)
    {
      return null;
    }

    $class_name = static::class;
    // If field 'id' -> can save in cache
    if ($field == 'id')
    {
      $result = self::GetCachedObject((int)$value, self::getTableName(), function () use ($class_name, $field, $value) {
        return $class_name::where($field, $value)->get()->last();
      });
    }
    else
    {
      $key_list = Cache::get(self::getTableName()) ?: array();

      $hash = hash('crc32', $field . '_' . $value);

      if (isset($key_list[$hash]))
      {
        $result = self::loadBy($key_list[$hash]);
      }
      else
      {
        $result = $class_name::where($field, $value)->get()->last();
        $key_list[$hash] = $result ? $result->id() : null;

        self::rewrite(self::getTableName(), $key_list);
      }
    }

    // Load relation models
    if ($relation)
    {
      $result->load($class_name::getMrRelation());
    }

    return $result;
  }

  /**
   * Загрузи или умри
   *
   * @param string|null $value
   * @param string $field
   * @param bool $relation
   * @return static|object
   */
  public static function loadByOrDie(?string $value, string $field = 'id', bool $relation = false)
  {
    if (!$object = self::loadBy($value, $field, $relation))
    {
      abort(response('Object ' . self::getTableName() . ' not loaded:"' . $value . '" by ' . $field, 500));
    }

    return $object;
  }

  public function self_flush()
  {
    $list = Cache::get(static::getTableName());
    $value = $this->attributes['id'];

    foreach ($list as $key => $item)
    {
      if ($value == $item)
      {
        unset($list[$key]);
      }
    }

    self::rewrite(static::getTableName(), $list);

    Cache::forget($this->GetCachedKey());
  }

  private static function rewrite(string $table_name, array $list): void
  {
    Cache::forget($table_name);
    Cache::add($table_name, $list);
  }

  /**
   * Object name for identify in a cache
   *
   * @return string
   */
  protected function GetCachedKey(): string
  {
    return (static::getTableName() . '_' . $this->attributes['id']);
  }

  public function id(): ?int
  {
    return $this->attributes['id'] ?? null;
  }

  /**
   * Reload with flush cache
   *
   * @return ORM|object|null
   */
  public function reload()
  {
    $this->self_flush();
    return self::loadBy($this->id());
  }

  // Disable Laravel time fields
  public $timestamps = false;


  public function save_mr(): ?int
  {
    if (method_exists($this, 'before_save'))
    {
      $this->before_save();
    }

    $this->save();

    if (method_exists($this, 'after_save'))
    {
      $this->after_save();
    }

    $this->flush();

    return $this->id();
  }

  /**
   * Has this object in cache or no
   *
   * @return bool
   */
  public function IsCached(): bool
  {
    return Cache::has($this->GetCachedKey());
  }

  /**
   * Get model from cache, can be different of original
   *
   * @return object|null
   */
  public function GetCachedModel(): ?object
  {
    return Cache::get($this->GetCachedKey());
  }

  public function delete_mr(): bool
  {
    if (method_exists($this, 'before_delete'))
    {
      $this->before_delete();
    }


    $this->delete();
    $this->self_flush();

    if (method_exists($this, 'after_delete'))
    {
      $this->after_delete();
    }

    return true;
  }
}