<?php

/**
 * A simple Memcached wrapper supporting namespacing of stored values.
 *  
 * @author Andrew McMillan
 * @license LGPL v2 or later
 */

class AwlCache {
  private $m;
  private $servers;
  private $working;

  /**
   * Initialise the cache connection. We use getpid() to give us a persistent connection.
   */
  function __construct() {
    global $c;

    $this->working = false;
    if ( isset($c->memcache_servers) && class_exists('Memcached') ) {
      dbg_error_log('Cache', 'Using Memcached interface connection');
      $this->servers = $c->memcache_servers;
      $this->m = new Memcached();
      foreach( $this->servers AS $v ) {
        dbg_error_log('Cache', 'Adding server '.$v);
        $server = explode(',',$v);
        if ( isset($server[2]) )
          $this->m->addServer($server[0],$server[1],$server[2]);
        else
          $this->m->addServer($server[0],$server[1]);
      }
      $this->working = true;
      // Hack to allow the regression tests to flush the cache at start
      if ( isset($_SERVER['HTTP_X_DAVICAL_FLUSH_CACHE'])) $this->flush();
    }
    else {
      dbg_error_log('Cache', 'Using NoCache dummy interface');
    }
  }

  /**
   * So we can find out if we are actually using the cache.
   */
  function isActive() {
    return $this->working;
  }

  /**
   * Construct a string from the namespace & key
   * @param unknown_type $namespace
   * @param unknown_type $key
   */
  private function nskey( $namespace, $key ) {
    return str_replace(' ', '%20', $namespace . (isset($key) ? '~~' . $key: '')); // for now.
  }

  /**
   * get a value from the specified namespace / key
   * @param $namespace
   * @param $key
   */
  function get( $namespace, $key ) {
    if ( !$this->working ) return false;
    $ourkey = self::nskey($namespace,$key);
    $value = $this->m->get($ourkey);
//    var_dump($value);
//    if ( $value !== false ) dbg_error_log('Cache', 'Got value for cache key "'.$ourkey.'" - '.strlen(serialize($value)).' bytes');
    return $value;
  }

  /**
   * Set a value for the specified namespace/key, perhaps with an expiry (default 10 days)
   * @param $namespace
   * @param $key
   * @param $value
   * @param $expiry
   */
  function set( $namespace, $key, $value, $expiry=864000 ) {
    if ( !$this->working ) return false;
    $ourkey = self::nskey($namespace,$key);
    $nskey = self::nskey($namespace,null);
    $keylist = $this->m->get( $nskey, null, $cas_token );
    if ( isset($keylist) && is_array($keylist) ) {
      if ( !isset($keylist[$ourkey]) ) {
        $keylist[$ourkey] = 1;
        $success = $this->m->cas( $cas_token, $nskey, $keylist );
        $i=0;
        while( !$success && $i++ < 10 && $this->m->getResultCode() == Memcached::RES_DATA_EXISTS ) {
          $keylist = $this->m->get( $nskey, null, $cas_token );
          if ( $keylist === false ) return false;
          if ( isset($keylist[$ourkey]) ) break;
          $keylist[$ourkey] = 1;
          $success = $this->m->cas( $cas_token, $nskey, $keylist );
        }
        if ( !$success ) return false;
      }
    } 
    else {
      $keylist = array( $ourkey => 1 );      
      $this->m->set( $nskey, $keylist );
    }
//    var_dump($value);
//    dbg_error_log('Cache', 'Setting value for cache key "'.$ourkey.'" - '.strlen(serialize($value)).' bytes');
    return $this->m->set( $ourkey, $value, $expiry );
  }

  /**
   * Delete a value from a namespace/key, or for everything in a namespace if a 'null' key is supplied.
   * @param $namespace
   * @param $key
   */
  function delete( $namespace, $key ) {
    if ( !$this->working ) return false;
    $nskey = self::nskey($namespace,$key);
    dbg_error_log('Cache', 'Deleting from cache key "'.$nskey.'"');
    if ( isset($key) ) {
      $this->m->delete( $nskey );
    }
    else {
      $keylist = $this->m->get( $nskey, null, $cas_token );
      if ( isset($keylist) ) {
      $this->m->delete( $nskey );
        if ( is_array($keylist) ) {
          foreach( $keylist AS $k => $v ) $this->m->delete( $k );
        } 
      }
    }
  }

  /**
   * Flush the entire cache
   */
  function flush( ) {
    if ( !$this->working ) return false;
    dbg_error_log('Cache', 'Flushing cache');
    $this->m->flush();
  }
}


function getCacheInstance() {
  static $ourCacheInstance;

  if ( !isset($ourCacheInstance) ) $ourCacheInstance = new AWLCache('Memcached'); 

  return $ourCacheInstance;
}
