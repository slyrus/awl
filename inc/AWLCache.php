<?php

/**
 * Wrapper for different memory caching subsystems.
 *  
 * @author Andrew McMillan
 * @license LGPL v2 or later
 */

class AWLCache {
  private $m;
  private $servers;
  private $working;
  
  function __construct() {
    global $c;

    $this->working = false;
    if ( isset($c->memcache_servers) && class_exists('Memcached') ) {
      dbg_error_log('Cache', 'Using Memcached interface');
      $this->servers = $c->memcache_servers;
      $this->m = new Memcached(posix_getpid());
      foreach( $this->servers AS $v ) {
        dbg_error_log('Cache', 'Adding server '.$v);
        $server = explode(',',$v);
        if ( isset($server[2]) )
          $this->m->addServer($server[0],$server[1],$server[2]);
        else
          $this->m->addServer($server[0],$server[1]);
      }
      $this->working = true;
    }
    else {
      dbg_error_log('Cache', 'Using NoCache dummy interface');
    }
  }


  private function nskey( $namespace, $key ) {
    return $namespace . '~~' . $key; // for now.
  }

  function get( $namespace, $key ) {
    if ( !$this->working ) return false;
    $ourkey = self::nskey($namespace,$key);
    return $this->m->get($ourkey);
  }
  
  function set( $namespace, $key, $value, $expiry=864000 ) {
    if ( !$this->working ) return false;
    $ourkey = self::nskey($namespace,$key);
    $keylist = $this->m->get( $namespace, null, $cas_token );
    if ( !isset($keylist) || !is_array($keylist) ) $keylist = array();
    $this->m->cas( $cas_token, $namespace, $keylist );
    $this->m->set( $ourkey, $value, $expiry );
  }
  
  function delete( $namespace, $key ) {
    if ( !$this->working ) return false;
    if ( isset($key) ) {
      $this->m->delete( self::nskey($namespace,$key) );
    }
    else {
      $keylist = $this->m->get( $namespace, null, $cas_token );
      if ( isset($keylist) ) {
      $this->m->delete( $namespace );
        if ( is_array($keylist) ) {
          foreach( $keylist AS $v ) $this->m->delete( $v );
        } 
      }
    }
  }
}


function getCacheInstance() {
  static $ourCacheInstance;

  if ( !isset($ourCacheInstance) ) $ourCacheInstance = new AWLCache('Memcached'); 

  return $ourCacheInstance;
}
