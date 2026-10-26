<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A deauthorize or data deletion request Meta sent for a Facebook user: what
 * was deleted and the code they can check it with. Platform-wide (a person's
 * login may be connected to several businesses); the user id only as a hash.
 */
class MetaDataDeletion extends Model
{
    protected $fillable = ['confirmation_code', 'kind', 'meta_user_hash', 'connections'];

    protected $casts = ['connections' => 'integer'];
}
