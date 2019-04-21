<?php

namespace SouthCoast\Components;

class CacheError extends \Error
{
    const UNKOWN_ENV_TYPE = [
        'message' => 'Unknown Enviromenet Type!, Allowed: \'production\' or \'devlopment\'.',
        'code' => 1
    ];

    const UNCALLABLE_FUNCTION = [
        'message' => 'The provided function was not callable!',
        'code' => 2,
    ];

    const NO_DIRECTORY = [
        'message' => 'There was no Cache Directory provided! Use: Cache::setDirecotry(\'Path/to/directory\') to set the path.',
        'code' => 10
    ];

    public function __construct($error_type)
    {
        parent::__construct($error_type['message'], $error_type['code']);
    }
}
