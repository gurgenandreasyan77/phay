<?php

namespace App\Google;

/** Google no longer knows a calendar's sync token (HTTP 410): its events are read again in full. */
class GoogleSyncTokenExpired extends GoogleException {}
