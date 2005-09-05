<?php
/////////////////////////////////////////////////////////////
//   C L A S S   F O R   S E N D I N G   E - M A I L       //
/////////////////////////////////////////////////////////////
class EMail
{
  var $To;         // To:
  var $From;       // etc...
  var $Cc;
  var $Bcc;
  var $ErrorsTo;
  var $ReplyTo;
  var $Sender;
  var $Subject;
  var $Body;

  function EMail( $subject = "", $to = "" ) {
    // Initialise with some defaults
    $this->From    = "";
    $this->Subject = $subject;
    $this->To      = $to;
    $this->Cc      = "";
    $this->Bcc     = "";
    $this->ErrorsTo = "";
    $this->ReplyTo = "";
    $this->Sender  = "";
    $this->Body    = "";
    return true;
  }

  function AppendDelimited( &$onto, $extra ) {
    if ( !isset($extra) || $extra == "" ) return false;
    if ( $onto != "" ) $onto .= ", ";
    $onto .= $extra;
    return $onto;
  }

  function AddTo( $recipient ) {
    return $this->AppendDelimited($this->To, $recipient);
  }
  function AddCc( $recipient ) {
    return $this->AppendDelimited($this->Cc, $recipient);
  }
  function AddBcc( $recipient ) {
    return $this->AppendDelimited($this->Bcc, $recipient);
  }
  function AddReplyTo( $recipient ) {
    return $this->AppendDelimited($this->ReplyTo, $recipient);
  }
  function AddErrorsTo( $recipient ) {
    return $this->AppendDelimited($this->ErrorsTo, $recipient);
  }

  function SetFrom( $sender ) {
    $this->From = $sender;
    return $sender;
  }

  function SetSender( $sender ) {
    $this->Sender = $sender;
    return $sender;
  }

  function SetSubject( $subject ) {
    $this->Subject = $subject;
    return $subject;
  }

  function SetBody( $body ) {
    $this->Body = $body;
    return $body;
  }

  function Send() {
    $additional_headers = "";
    if ( "$this->From" != "" )     $additional_headers .= "From: $this->From\r\n";
    if ( "$this->Cc" != "" )       $additional_headers .= "Cc: $this->Cc\r\n";
    if ( "$this->Bcc" != "" )      $additional_headers .= "Bcc: $this->Bcc\r\n";
    if ( "$this->ReplyTo" != "" )  $additional_headers .= "Reply-To: $this->ReplyTo\r\n";
    if ( "$this->ErrorsTo" != "" ) $additional_headers .= "Errors-To: $this->ErrorsTo\r\n";

    $additional_parameters = "";
    if ( "$this->Sender" != "" ) $additional_parameters = "-f$this->Sender";
    mail( $this->To, $this->Subject, $this->Body, $additional_headers, $additional_parameters );
  }
}
?>