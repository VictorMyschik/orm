<?php

namespace App\Models\ORM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ORM extends Model
{
  protected int $id = 0;

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

  public static array $objects_loaded_list = array();

  /**
   * Get object from cache by id or call
   *
   * @param int $id
   * @param string $table
   * @param callable $object
   * @return mixed
   */
  private static function GetCachedObject(int $id, string $table, callable $object)
  {
    $cache_key = $table . '_' . $id;

    // return Cache::remember($cache_key, 300, function () use ($object) {
    return $object();
    //});
  }

  /**
   * Load object (get last result)
   *
   * @param string|null $value
   * @param string $field
   * @param bool $relation // Догрузить связи
   * @return static|object|null
   */
  public static function loadBy(?string $value, string $field = 'id', bool $relation = false): ?object
  {
    $result = null;

    if(!$value)
    {
      return null;
    }

    $object = null;
    $class_name = static::class;

    // local cache
    $local_hash_key = hash('crc32', $class_name . '_' . $field . '_' . $value);

    if(isset(self::$objects_loaded_list[$local_hash_key]))
    {
      return self::$objects_loaded_list[$local_hash_key];
    }

    // If field 'id' -> can save in cache
    if($field == 'id')
    {
      $object = $class_name::find($value);
    }
    else
    {
      $redis_keys_list = Cache::get($class_name::getTableName()) ?: array();
      $redis_key_hash = hash('crc32', $field . '_' . $value);

      if(isset($redis_keys_list[$redis_key_hash]))
      {
        $object = self::loadBy($redis_keys_list[$redis_key_hash]);// загрузка по id
      }
      else
      {
        if($result_data = DB::table(self::getTableName())->select(['*'])->where($field, $value)->first())
        {
          foreach($result_data as $key => $value)
          {
            if(!is_null($value))
            {
              $properties[$key] = $value;
            }
          }

          $object = new $class_name();
          $object->exists = true;
          $object->attributes = $properties;
          $object->original = $properties;

          $redis_keys_list[$redis_key_hash] = $object->id();
          self::rewrite(self::getTableName(), $redis_keys_list);
        }
      }
    }

    self::$objects_loaded_list[$local_hash_key] = $object;

    return $object;
  }

  public function id(): ?int
  {
    return $this->attributes['id'] ?? null;
  }

  public static function GetLocalCache()
  {
    return self::$objects_loaded_list;
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
    if(!$object = self::loadBy($value, $field, $relation))
    {
      abort(response('Object ' . self::getTableName() . ' not loaded:"' . $value . '" by ' . $field, 500));
    }

    return $object;
  }

  public function self_flush()
  {
    $list = Cache::get(static::getTableName()) ?: array();
    $value = $this->attributes['id'];

    foreach($list as $key => $item)
    {
      if($value == $item)
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


  public function save_mr(bool $flushAffectedCaches = true): ?int
  {
    if(method_exists($this, 'before_save'))
    {
      $this->before_save();
    }

    $this->save();

    if(method_exists($this, 'after_save'))
    {
      $this->after_save();
    }

    if($flushAffectedCaches && method_exists($this, 'flushAffectedCaches'))
    {
      $this->flushAffectedCaches();
    }

    $this->self_flush();

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

  public function delete_mr(bool $skipAffectedCache = true): bool
  {
    if(method_exists($this, 'before_delete'))
    {
      $this->before_delete();
    }

    $this->delete();

    $this->self_flush();

    if($skipAffectedCache && method_exists($this, 'after_delete'))
    {
      $this->after_delete();
    }

    return true;
  }
}
