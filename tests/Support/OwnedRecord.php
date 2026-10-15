<?php

namespace Tests\Support;

use App\Traits\OwnedByUserOrOrganization;
use Illuminate\Database\Eloquent\Model;

class OwnedRecord extends Model
{
    use OwnedByUserOrOrganization;

    protected $table = 'owned_records';

    protected $guarded = [];

    public $timestamps = false;
}
