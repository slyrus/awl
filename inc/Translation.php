<?php
     if ( !function_exists("i18n") ) {
  /**
  * Mark a string as being internationalized.  This is a semaphore method; it
  * does nothing but it allows us to easily identify strings that require
  * translation.  Generally this is used to mark strings that will be stored
  * in the database (like descriptions of permissions).
  *
  * AWL uses GNU gettext for internationalization (i18n) and localization (l10n) of
  * text presented to the user. Gettext needs to know about all places involving strings,
  * that must be translated. Mark any place, where localization at runtime shall take place
  * by using the function translate().
  *
  * E.g. instead of:
  *   print 'TEST to be displayed in different languages';
  * use:
  *   print translate('TEST to be displayed in different languages');
  * and you are all set for pure literals. The translation teams will receive that literal
  * string as a job to translate and will translate it (when the message is clear enough).
  * At runtime the message is then localized when printed.
  * The input string can contain a hint to assist translators:
  *   print translate('TT <!-- abbreviation for Translation Test -->');
  * The hint portion of the string will not be printed.
  *
  * But consider this case:
  *   $message_to_be_localized = 'TEST to be displayed in different languages';
  *   print translate($message_to_be_localized);
  *
  * The translate() function is called in the right place for runtime handling, but there
  * is no message at gettext preprocessing time to be given to the translation teams,
  * just a variable name. Translation of the variable name would break the code! So all
  * places potentially feeding this variable have to be marked to be given to translation
  * teams, but not translated at runtime!
  *
  * This method resolves all such cases. Simply mark the candidates:
  *   $message_to_be_localized = i18n('TEST to be displayed in different languages');
  *   print translate($message_to_be_localized);
  *
  * @param string the value
  * @return string the same value
  */
  function i18n($value) {
    return $value;  /* Just pass the value through */
  }
}


if ( !function_exists("translate") ) {
  /**
  * Convert a string in English to whatever this user's locale is
  */
  function translate( $en ) {
    global $session, $c;
    if ( !isset($session) || !isset($session->locale) || $session->locale == 'en' ) return $en;
    $xl = $en;

    //  Do our translation...

    return $xl;
  }
}


if ( !function_exists("init_gettext") ) {
  /**
  * Initialise our use of Gettext
  */
  function init_gettext( $domain, $location ) {
    global $session, $c;
    if ( !isset($session) || !isset($session->locale) || $session->locale == 'en' ) return;

    setlocale( LC_ALL, $session->locale);
    bindtextdomain( $domain, $location );
    textdomain( $domain );
  }
}

?>