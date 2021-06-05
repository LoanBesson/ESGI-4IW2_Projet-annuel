<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Phone extends Constraint
{
    /*
     * Any public properties become valid options for the annotation.
     * Then, use these in your validator class.
     */
    public $message = 'Le numéro de téléphone "{{ value }}" n\'est pas valide.';
    public $lenMessage = 'Le numéro de téléphone doit faire {{ lenght }} caractères.';
    public $lenght = 10;
}
