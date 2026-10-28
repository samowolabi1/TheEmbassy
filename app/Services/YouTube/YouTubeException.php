<?php

namespace App\Services\YouTube;

use RuntimeException;

/**
 * Something YouTube or Google sign-in refused, with a message fit to show the media team.
 */
class YouTubeException extends RuntimeException {}
