<?php
namespace App;

use Bow\Database\Barry\Model;

class User extends Model
{
    /**
     * Le nom de la table.
     *
     * @var string
     */
    protected $table = "users";
}