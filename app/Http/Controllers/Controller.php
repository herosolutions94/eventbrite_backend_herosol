<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    public function findClosestNumber($number) {
        $series = [1, 2, 4, 8, 16, 32, 64, 128, 256,512,1024];

        // Check if the number is in the series
        if (in_array($number, $series)) {
            return intval($number) / 2;
        }

        // Find the closest smaller number in the series
        $closestSmaller = 1;
        foreach ($series as $value) {
            if ($value < $number) {
                $closestSmaller = $value;
            } else {
                break;
            }
        }

        // Calculate the result
        $result = $number - $closestSmaller;

        return $result;
    }
    public function isCommaSeparated($inputString) {
        // Use explode to split the string into an array
        $values = explode(',', $inputString);

        // Check if the resulting array has more than one element
        if (count($values) > 1) {
            return true; // It's comma-separated
        } else {
            return false; // It's a single value
        }
    }
    public function chooseTwoRandomNumbers($availableNumbers, &$chosenNumbers = []) {
        if (count($availableNumbers) >= 2) {
            shuffle($availableNumbers);
            $randomNumbers = array_slice($availableNumbers, 0, 2);
            $chosenNumbers = array_merge($chosenNumbers, $randomNumbers);
            $remainingNumbers = array_diff($availableNumbers, $randomNumbers);
            return ['chosen' => $randomNumbers, 'remaining' => $remainingNumbers];
        } else {
            return ['chosen' => [], 'remaining' => $availableNumbers];
        }
    }
}
