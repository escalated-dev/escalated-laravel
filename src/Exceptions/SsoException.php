<?php

namespace Escalated\Laravel\Exceptions;

use RuntimeException;

/**
 * A refused single sign-on. The message says why, for the log; the user only
 * ever sees a generic failure.
 */
class SsoException extends RuntimeException {}
