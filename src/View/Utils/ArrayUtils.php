<?php

namespace AuroraLumina\View\Utils;

/**
 * Class ArrayUtils
 * 
 * Utility class for working with arrays.
 */
class ArrayUtils
{
    /**
     * Sorts variables by length and alphabetically.
     *
     * This method sorts the variables array in-place, first by length in descending order, and then alphabetically in ascending order.
     *
     * @param array &$variables The variables array to sort.
     * @return void
     */
    public static function sortVariables(array &$variables): void
    {
        usort($variables, function ($a, $b) {
            $lenA = strlen($a);
            $lenB = strlen($b);
            if ($lenA == $lenB) {
                if ($a == $b) {
                    return 0;
                }
                return ($a < $b)? 1 : -1;
            }
            return ($lenA < $lenB)? 1 : -1;
        });
    }

    /**
     * Adds the last variable if it's not already in the array.
     *
     * This method checks if the last variable is not already in the variables array or objects array, and if so, adds it to the variables array.
     *
     * @param string|null $variable The last variable to add.
     * @param array &$variables The variables array to update.
     * @param array &$objects The objects array to check against.
     * @return void
     */
    public static function addLastVariableIfNotInArray(?string $variable, array &$variables, array &$objects): void
    {
        $variablesAssoc = array_flip($variables);
        $objectsAssoc = array_flip($objects);
    
        if ($variable !== null && !isset($variablesAssoc[$variable]) && !isset($objectsAssoc[$variable]))
        {
            $variables[] = $variable;
        }
    }
}